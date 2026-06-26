<?php

namespace App\Modules\Declarations\Services;

use App\Modules\Declarations\Entities\DeclarationPacket;
use App\Modules\Declarations\Entities\DeclarationPacketItem;
use App\Modules\Declarations\Entities\DeclarationTemplate;
use App\Modules\Declarations\Models\DeclarationPacketItemModel;
use App\Modules\Declarations\Models\DeclarationPacketModel;
use App\Modules\Declarations\Models\DeclarationTemplateModel;
use App\Modules\Declarations\Models\EmploymentRelationModel;
use App\Modules\Declarations\Models\PersonModel;
use App\Models\BasicdataModel;
use App\Modules\Declarations\Models\DeclarationInvitationModel;
use App\Modules\Declarations\Services\InvitationTokenService;
use App\Modules\Declarations\Services\RecruiterService;
use App\Modules\Declarations\Entities\EmploymentRelation;
use App\Modules\Declarations\Models\DeclarationSubmissionModel;
use App\Modules\Declarations\Models\DeclarationAuditLogModel;
use App\Modules\Declarations\Presenters\Submissions\SubmissionPresenterRegistry;
use App\Modules\Declarations\Services\PacketReviewAuthorizationService;
use App\Modules\Declarations\Services\DeclarationForms\DeclarationFormRegistry;

use DateTime;
use RuntimeException;

class DeclarationPacketService
{
    private const PERSONAL_DATA_TEMPLATE_CODE = 'personal_data_statement';

    protected DeclarationPacketModel $packetModel;
    protected DeclarationPacketItemModel $itemModel;
    protected DeclarationTemplateModel $templateModel;
    protected EmploymentRelationModel $relationModel;
    protected PersonModel $personModel;
    protected BasicdataModel $basicdataModel;
    protected DeclarationInvitationModel $invitationModel;
    protected InvitationTokenService $tokenService;
    protected RecruiterService $recruiterService;
    protected DeclarationSubmissionModel $submissionModel;
    protected SubmissionPresenterRegistry $submissionPresenterRegistry;
    protected PacketReviewAuthorizationService $reviewAuthorizationService;
    protected DeclarationAuditLogModel $auditLogModel;
    protected DeclarationFormRegistry $formRegistry;

    public function __construct()
    {
        $this->packetModel = new DeclarationPacketModel();
        $this->itemModel = new DeclarationPacketItemModel();
        $this->templateModel = new DeclarationTemplateModel();
        $this->relationModel = new EmploymentRelationModel();
        $this->personModel = new PersonModel();
        $this->basicdataModel = new BasicdataModel();
        $this->invitationModel = new DeclarationInvitationModel();
        $this->tokenService = new InvitationTokenService();
        $this->recruiterService = new RecruiterService();
        $this->submissionModel = new DeclarationSubmissionModel();
        $this->submissionPresenterRegistry = new SubmissionPresenterRegistry();
        $this->reviewAuthorizationService = new PacketReviewAuthorizationService();
        $this->auditLogModel = new DeclarationAuditLogModel();
        $this->formRegistry = new DeclarationFormRegistry();
    }

    public function getAvailableTemplates(?int $taxYear = null): array
    {
        return $this->templateModel->findActiveForYear($taxYear);
    }

    public function findPacketsByPersonId(int $personId): array
    {
        return $this->packetModel->findByPersonId($personId);
    }

    public function createDefaultOnboardingForRelation(int $relationId, ?int $taxYear = null): int
    {
        $templates = $this->templateModel->findDefaultOnboardingTemplates($taxYear);
        $templates = array_values(array_filter($templates, function ($template): bool {
            return $this->formRegistry->hasConcreteHandlerForCode((string) ($template->code ?? ''));
        }));

        if (empty($templates)) {
            throw new RuntimeException('Nincs aktív online kitölthető alap beléptetési nyilatkozat sablon.');
        }

        $templateIds = array_map(static fn($template): int => (int) $template->id, $templates);

        return $this->createForRelation($relationId, $templateIds, $taxYear);
    }

    public function getCandidateSelectableTaxTemplates(?int $taxYear = null): array
    {
        return $this->templateModel->findCandidateSelectableTaxTemplates($taxYear);
    }

    public function createForRelation(int $relationId, array $templateIds, ?int $taxYear = null): int
    {
        $relation = $this->relationModel->find($relationId);

        if (!$relation) {
            throw new RuntimeException('A jogviszony nem található.');
        }

        if (!$relation->isOpen()) {
            throw new RuntimeException('Lezárt vagy törölt jogviszonyhoz nem indítható nyilatkozatcsomag.');
        }

        $taxYear = $this->normalizeTaxYear($taxYear);
        $this->assertNoBlockingPacketForRelation($relation, $taxYear);

        $templateIds = array_values(array_unique(array_filter(array_map('intval', $templateIds))));

        if (empty($templateIds)) {
            throw new RuntimeException('Legalább egy nyilatkozatot ki kell választani.');
        }

        $templateIds = $this->withPersonalDataTemplate($templateIds);

        $templates = [];
        foreach ($templateIds as $templateId) {
            $template = $this->templateModel->find($templateId);

            if (!$template || !$template->isActive()) {
                throw new RuntimeException('A kiválasztott nyilatkozat nem található vagy nem aktív.');
            }

            if (!$this->formRegistry->hasConcreteHandlerForCode((string) $template->code)) {
                throw new RuntimeException('A kiválasztott nyilatkozat még nem tölthető ki online: ' . ($template->name ?: $template->code));
            }

            $templates[] = $template;
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $packetId = $this->packetModel->insert([
                'person_id' => $relation->person_id,
                'employment_relation_id' => $relation->id,
                'company_id' => $relation->company_id,
                'status' => DeclarationPacket::STATUS_DRAFT,
                'tax_year' => $taxYear,
                'created_by_user_id' => function_exists('logged') ? logged('id') : null,
            ], true);

            if (!$packetId) {
                $errors = $this->packetModel->errors();
                throw new RuntimeException(!empty($errors) ? implode(' ', $errors) : 'A nyilatkozatcsomag létrehozása sikertelen.');
            }

            $sortOrder = 10;

            foreach ($templates as $template) {
                $itemId = $this->itemModel->insert([
                    'packet_id' => $packetId,
                    'template_id' => $template->id,
                    'status' => DeclarationPacketItem::STATUS_PENDING,
                    'sort_order' => $sortOrder,
                ], true);

                if (!$itemId) {
                    $errors = $this->itemModel->errors();
                    throw new RuntimeException(!empty($errors) ? implode(' ', $errors) : 'A nyilatkozatcsomag elemeinek létrehozása sikertelen.');
                }

                $this->auditLogModel->logAction(
                    'packet_item_created',
                    'declaration_packet_item',
                    (int) $itemId,
                    (int) $packetId,
                    (int) $itemId,
                    null,
                    DeclarationPacketItem::STATUS_PENDING,
                    'Nyilatkozatcsomag elem létrehozva.',
                    [
                        'person_id' => (int) $relation->person_id,
                        'employment_relation_id' => (int) $relation->id,
                        'template_id' => (int) $template->id,
                        'template_code' => $template->code ?? null,
                        'template_name' => $template->name ?? null,
                    ]
                );

                $sortOrder += 10;
            }

            $this->auditLogModel->logAction(
                DeclarationAuditLogModel::ACTION_PACKET_CREATED,
                'declaration_packet',
                (int) $packetId,
                (int) $packetId,
                null,
                null,
                DeclarationPacket::STATUS_DRAFT,
                null,
                [
                    'employment_relation_id' => (int) $relation->id,
                    'person_id' => (int) $relation->person_id,
                    'company_id' => (int) $relation->company_id,
                    'tax_year' => $taxYear,
                    'template_ids' => $templateIds,
                ]
            );

            if ($db->transStatus() === false) {
                throw new RuntimeException('A nyilatkozatcsomag létrehozása sikertelen.');
            }

            $db->transCommit();

            return (int) $packetId;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function findPacket(int $packetId)
    {
        $packet = $this->packetModel->find($packetId);

        if (!$packet) {
            throw new RuntimeException('A nyilatkozatcsomag nem található.');
        }

        return $packet;
    }

    public function findPacketDetails(int $packetId): array
    {
        $packet = $this->findPacket($packetId);

        $person = $this->personModel->find($packet->person_id);
        $relation = $this->relationModel->find($packet->employment_relation_id);
        $items = $this->itemModel->findWithTemplatesByPacketId($packet->id);

        $company = null;
        $recruiter = null;
        $recruiterDisplayName = null;

        if ($relation && !empty($relation->primary_recruiter_user_id)) {
            $recruiter = $this->recruiterService->findRecruiterById((int) $relation->primary_recruiter_user_id);

            if ($recruiter) {
                $antraid = $this->recruiterService->getAntraId($recruiter);
                $recruiterDisplayName = $this->recruiterService->getDisplayName($recruiter) . ($antraid !== '' ? ' (' . $antraid . ')' : '');
            }
        }

        if (!empty($packet->company_id)) {
            $company = $this->basicdataModel
                ->where('type', 'division')
                ->where('id', $packet->company_id)
                ->first();
        }

        $latestInvitation = $this->invitationModel->findLatestByPacketId((int) $packet->id);
        $activeInvitation = $this->invitationModel->findActiveByPacketId((int) $packet->id);

        return [
            'packet' => $packet,
            'person' => $person,
            'relation' => $relation,
            'company' => $company,
            'recruiter' => $recruiter,
            'recruiterDisplayName' => $recruiterDisplayName,
            'items' => $items,
            'latestInvitation' => $latestInvitation,
            'activeInvitation' => $activeInvitation,
        ];
    }

    public function createNewInvitationLink(int $packetId): array
    {
        $packet = $this->findPacket($packetId);

        $person = $this->personModel->find($packet->person_id);
        $relation = $this->relationModel->find($packet->employment_relation_id);

        if (!$person) {
            throw new RuntimeException('A személy nem található.');
        }

        if (!$relation) {
            throw new RuntimeException('A beléptetési folyamat nem található.');
        }

        $this->assertNoBlockingPacketForRelation(
            $relation,
            $this->normalizeTaxYear(!empty($packet->tax_year) ? (int) $packet->tax_year : null),
            (int) $packet->id
        );

        $this->assertPacketContainsPersonalDataItem((int) $packet->id);

        $candidateEmail = trim((string) ($person->email ?? ''));

        if ($candidateEmail === '') {
            throw new RuntimeException('A személyhez nincs e-mail cím rögzítve.');
        }

        $plainToken = $this->tokenService->generatePlainToken();
        $tokenHash = $this->tokenService->hashToken($plainToken);
        $expiresAt = (new DateTime('+14 days'))->format('Y-m-d H:i:s');

        $activeInvitationsBeforeRevoke = $this->invitationModel
            ->where('packet_id', (int) $packet->id)
            ->whereIn('status', [
                \App\Modules\Declarations\Entities\DeclarationInvitation::STATUS_CREATED,
                \App\Modules\Declarations\Entities\DeclarationInvitation::STATUS_SENT,
                \App\Modules\Declarations\Entities\DeclarationInvitation::STATUS_OPENED,
            ])
            ->countAllResults();

        $oldPacketStatus = (string) $packet->status;
        $oldRelationStatus = (string) $relation->status;

        $db = db_connect();
        $db->transBegin();

        try {
            if ($activeInvitationsBeforeRevoke > 0) {
                $this->invitationModel->revokeActiveByPacketId((int) $packet->id);

                $this->auditLogModel->logAction(
                    DeclarationAuditLogModel::ACTION_INVITATION_REVOKED,
                    'declaration_invitation',
                    null,
                    (int) $packet->id,
                    null,
                    null,
                    \App\Modules\Declarations\Entities\DeclarationInvitation::STATUS_REVOKED,
                    'Új meghívó link küldése miatt a korábbi aktív linkek visszavonásra kerültek.',
                    [
                        'person_id' => (int) $packet->person_id,
                        'employment_relation_id' => (int) $packet->employment_relation_id,
                        'revoked_active_invitations' => $activeInvitationsBeforeRevoke,
                    ]
                );
            }

            $invitationId = $this->invitationModel->insert([
                'person_id' => $packet->person_id,
                'employment_relation_id' => $packet->employment_relation_id,
                'packet_id' => $packet->id,
                'email' => $candidateEmail,
                'token_hash' => $tokenHash,
                'status' => \App\Modules\Declarations\Entities\DeclarationInvitation::STATUS_SENT,
                'sent_at' => date('Y-m-d H:i:s'),
                'expires_at' => $expiresAt,
            ], true);

            if (!$invitationId) {
                $errors = $this->invitationModel->errors();

                throw new RuntimeException(
                    !empty($errors) ? implode(' ', $errors) : 'Az új meghívó link létrehozása sikertelen.'
                );
            }

            if (in_array($oldPacketStatus, [
                DeclarationPacket::STATUS_DRAFT,
                DeclarationPacket::STATUS_SENT,
            ], true)) {
                $this->packetModel->markAsSent((int) $packet->id);
            }

            if (in_array($oldRelationStatus, [
                EmploymentRelation::STATUS_DRAFT,
                EmploymentRelation::STATUS_ONBOARDING,
                EmploymentRelation::STATUS_INVITED,
            ], true)) {
                $this->relationModel->updateStatus(
                    (int) $packet->employment_relation_id,
                    EmploymentRelation::STATUS_INVITED
                );
            }

            $this->auditLogModel->logAction(
                $activeInvitationsBeforeRevoke > 0
                    ? DeclarationAuditLogModel::ACTION_INVITATION_REGENERATED
                    : DeclarationAuditLogModel::ACTION_INVITATION_CREATED,
                'declaration_invitation',
                (int) $invitationId,
                (int) $packet->id,
                null,
                null,
                \App\Modules\Declarations\Entities\DeclarationInvitation::STATUS_SENT,
                $activeInvitationsBeforeRevoke > 0 ? 'Új meghívó link létrehozva.' : 'Meghívó link létrehozva.',
                [
                    'person_id' => (int) $packet->person_id,
                    'employment_relation_id' => (int) $packet->employment_relation_id,
                    'invitation_id' => (int) $invitationId,
                    'email' => $candidateEmail,
                    'expires_at' => $expiresAt,
                    'old_packet_status' => $oldPacketStatus,
                    'current_packet_status' => in_array($oldPacketStatus, [DeclarationPacket::STATUS_DRAFT, DeclarationPacket::STATUS_SENT], true)
                        ? DeclarationPacket::STATUS_SENT
                        : $oldPacketStatus,
                    'old_relation_status' => $oldRelationStatus,
                ]
            );

            if ($db->transStatus() === false) {
                throw new RuntimeException('Az új meghívó link létrehozása sikertelen.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        $config = config(\App\Modules\Declarations\Config\Declarations::class);

        return [
            'invitation_id' => (int) $invitationId,
            'plain_token' => $plainToken,
            'url' => rtrim($config->publicBaseUrl, '/') . '/start/' . $plainToken,
            'expires_at' => $expiresAt,
        ];
    }


    public function closePacket(int $packetId): void
    {
        $packet = $this->findPacket($packetId);

        if (!hasPermissions('declarations_admin_override')) {
            throw new RuntimeException('Nincs jogosultságod a nyilatkozatcsomag lezárásához.');
        }

        if (!in_array((string) $packet->status, [
            DeclarationPacket::STATUS_APPROVED,
            DeclarationPacket::STATUS_COMPLETED,
        ], true)) {
            throw new RuntimeException('Csak elfogadott nyilatkozatcsomag zárható le.');
        }

        $oldPacketStatus = (string) $packet->status;

        if (!$this->packetModel->markAsClosed($packetId)) {
            throw new RuntimeException('A nyilatkozatcsomag lezárása sikertelen.');
        }

        $this->auditLogModel->logAction(
            DeclarationAuditLogModel::ACTION_PACKET_CLOSED,
            'declaration_packet',
            $packetId,
            $packetId,
            null,
            $oldPacketStatus,
            DeclarationPacket::STATUS_CLOSED,
            'A nyilatkozatcsomagot adminisztratívan lezárták.',
            [
                'person_id' => (int) $packet->person_id,
                'employment_relation_id' => (int) $packet->employment_relation_id,
            ]
        );
    }

    public function findPacketReviewDetails(int $packetId): array
    {
        $details = $this->findPacketDetails($packetId);

        $packet = $details['packet'];
        $items = $details['items'];

        $submissionsByItemId = $this->submissionModel->findByPacketIdIndexedByItemId((int) $packet->id);

        $reviewItems = [];

        foreach ($items as $item) {
            $submission = $submissionsByItemId[(int) $item->id] ?? null;

            $reviewItems[] = [
                'item' => $item,
                'submission' => $submission,
                'display_rows' => $this->submissionPresenterRegistry->rowsFor(
                    (string) ($item->template_code ?? ''),
                    $submission
                ),
                'can_review' => (string) $packet->status === DeclarationPacket::STATUS_SUBMITTED
                    && $this->reviewAuthorizationService->canReviewItem(
                        $packet,
                        $details['relation'] ?? null,
                        $item
                    ),
            ];
        }

        $details['reviewItems'] = $reviewItems;
        $details['auditLogs'] = $this->auditLogModel->findByPacketId((int) $packet->id, 30);
        $details['canEditPacketItems'] = $this->canEditPacketItems($packet);
        $details['editableTemplates'] = $details['canEditPacketItems']
            ? $this->getEditableTemplatesForPacket((int) $packet->id)
            : [];
        $details['batchRejectItems'] = array_values(array_filter($reviewItems, static function (array $reviewItem): bool {
            $item = $reviewItem['item'] ?? null;

            return (bool) ($reviewItem['can_review'] ?? false)
                && !empty($reviewItem['submission'])
                && $item
                && (string) $item->status === DeclarationPacketItem::STATUS_COMPLETED;
        }));

        return $details;
    }

    public function canEditPacketItems(object $packet): bool
    {
        return in_array((string) $packet->status, [
            DeclarationPacket::STATUS_DRAFT,
            DeclarationPacket::STATUS_SENT,
            DeclarationPacket::STATUS_IN_PROGRESS,
        ], true);
    }

    public function getEditableTemplatesForPacket(int $packetId): array
    {
        $packet = $this->findPacket($packetId);

        if (!$this->canEditPacketItems($packet)) {
            return [];
        }

        $taxYear = !empty($packet->tax_year) ? (int) $packet->tax_year : null;
        $templates = $this->templateModel->findActiveForYear($taxYear);
        $items = $this->itemModel->findByPacketId((int) $packet->id);
        $existingTemplateIds = [];

        foreach ($items as $item) {
            $existingTemplateIds[(int) $item->template_id] = true;
        }

        return array_values(array_filter($templates, function ($template) use ($existingTemplateIds): bool {
            return empty($existingTemplateIds[(int) $template->id])
                && $this->formRegistry->hasConcreteHandlerForCode((string) ($template->code ?? ''));
        }));
    }

    public function addTemplateToPacket(int $packetId, int $templateId): int
    {
        $packet = $this->findPacket($packetId);

        if (!$this->canEditPacketItems($packet)) {
            throw new RuntimeException('A nyilatkozatcsomag már be lett küldve, ezért nem szerkeszthető.');
        }

        $template = $this->templateModel->find($templateId);

        if (!$template || !$template->isActive()) {
            throw new RuntimeException('A kiválasztott nyilatkozat nem található vagy nem aktív.');
        }

        if (!$this->formRegistry->hasConcreteHandlerForCode((string) $template->code)) {
            throw new RuntimeException('A kiválasztott nyilatkozat még nem tölthető ki online: ' . ($template->name ?: $template->code));
        }

        if (!empty($packet->tax_year) && !empty($template->tax_year) && (int) $packet->tax_year !== (int) $template->tax_year) {
            throw new RuntimeException('A kiválasztott nyilatkozat adóéve nem egyezik a csomag adóévével.');
        }

        $existingItem = $this->itemModel->findByPacketAndTemplateId((int) $packet->id, (int) $template->id);

        if ($existingItem) {
            return (int) $existingItem->id;
        }

        $itemId = $this->itemModel->insert([
            'packet_id' => (int) $packet->id,
            'template_id' => (int) $template->id,
            'status' => DeclarationPacketItem::STATUS_PENDING,
            'sort_order' => $this->itemModel->nextSortOrderForPacket((int) $packet->id),
        ], true);

        if (!$itemId) {
            $errors = $this->itemModel->errors();

            throw new RuntimeException(!empty($errors) ? implode(' ', $errors) : 'A nyilatkozat hozzáadása sikertelen.');
        }

        $this->auditLogModel->logAction(
            'packet_item_added_by_admin',
            'declaration_packet_item',
            (int) $itemId,
            (int) $packet->id,
            (int) $itemId,
            null,
            DeclarationPacketItem::STATUS_PENDING,
            'Nyilatkozat hozzáadva a csomaghoz.',
            [
                'person_id' => (int) $packet->person_id,
                'employment_relation_id' => (int) $packet->employment_relation_id,
                'template_id' => (int) $template->id,
                'template_code' => $template->code ?? null,
                'template_name' => $template->name ?? null,
            ]
        );

        return (int) $itemId;
    }


    public function getCandidateSelectableTaxTemplatesForPacket(int $packetId): array
    {
        $packet = $this->findPacket($packetId);

        if (in_array((string) $packet->status, [
            DeclarationPacket::STATUS_APPROVED,
            DeclarationPacket::STATUS_SUBMITTED,
            DeclarationPacket::STATUS_CLOSED,
            DeclarationPacket::STATUS_COMPLETED,
            DeclarationPacket::STATUS_CANCELLED,
        ], true)) {
            return [];
        }

        $taxYear = !empty($packet->tax_year) ? (int) $packet->tax_year : (int) date('Y');
        $templates = $this->templateModel->findCandidateSelectableTaxTemplates($taxYear);
        $items = $this->itemModel->findByPacketId((int) $packet->id);
        $existingTemplateIds = [];

        foreach ($items as $item) {
            $existingTemplateIds[(int) $item->template_id] = true;
        }

        return array_values(array_filter($templates, static function ($template) use ($existingTemplateIds): bool {
            return empty($existingTemplateIds[(int) $template->id]);
        }));
    }

    public function addCandidateSelectedTemplate(int $packetId, int $templateId): int
    {
        $packet = $this->findPacket($packetId);

        if (in_array((string) $packet->status, [
            DeclarationPacket::STATUS_APPROVED,
            DeclarationPacket::STATUS_SUBMITTED,
            DeclarationPacket::STATUS_CLOSED,
            DeclarationPacket::STATUS_COMPLETED,
            DeclarationPacket::STATUS_CANCELLED,
        ], true)) {
            throw new RuntimeException('Lezárt vagy elfogadott csomaghoz már nem adható hozzá új nyilatkozat.');
        }

        $template = $this->templateModel->find($templateId);

        if (!$template || !$template->isActive()) {
            throw new RuntimeException('A kiválasztott nyilatkozat nem található vagy nem aktív.');
        }

        if ((string) $template->declaration_group !== DeclarationTemplate::GROUP_TAX || (int) $template->is_candidate_selectable !== 1) {
            throw new RuntimeException('Ez a nyilatkozat nem választható a beálló által.');
        }

        if (!empty($packet->tax_year) && !empty($template->tax_year) && (int) $packet->tax_year !== (int) $template->tax_year) {
            throw new RuntimeException('A kiválasztott nyilatkozat adóéve nem egyezik a csomag adóévével.');
        }

        $existingItem = $this->itemModel->findByPacketAndTemplateId((int) $packet->id, (int) $template->id);

        if ($existingItem) {
            return (int) $existingItem->id;
        }

        $itemId = $this->itemModel->insert([
            'packet_id' => (int) $packet->id,
            'template_id' => (int) $template->id,
            'status' => DeclarationPacketItem::STATUS_PENDING,
            'sort_order' => $this->itemModel->nextSortOrderForPacket((int) $packet->id),
        ], true);

        if (!$itemId) {
            $errors = $this->itemModel->errors();

            throw new RuntimeException(!empty($errors) ? implode(' ', $errors) : 'A kiválasztott nyilatkozat hozzáadása sikertelen.');
        }

        $this->auditLogModel->logAction(
            DeclarationAuditLogModel::ACTION_OPTIONAL_TEMPLATE_ADDED,
            'declaration_packet_item',
            (int) $itemId,
            (int) $packet->id,
            (int) $itemId,
            null,
            DeclarationPacketItem::STATUS_PENDING,
            'Beálló által választható adóügyi nyilatkozat hozzáadva a csomaghoz.',
            [
                'template_id' => (int) $template->id,
                'template_code' => $template->code ?? null,
                'template_name' => $template->name ?? null,
                'tax_year' => $template->tax_year ?? null,
            ]
        );

        return (int) $itemId;
    }

    private function normalizeTaxYear(?int $taxYear): int
    {
        return $taxYear ?: (int) date('Y');
    }

    private function withPersonalDataTemplate(array $templateIds): array
    {
        $template = $this->templateModel->findByCode(self::PERSONAL_DATA_TEMPLATE_CODE);

        if (!$template || !$template->isActive()) {
            throw new RuntimeException('A személyes adatok nyilatkozat sablon nem található vagy nem aktív.');
        }

        $templateIds[] = (int) $template->id;

        return array_values(array_unique(array_map('intval', $templateIds)));
    }

    private function assertPacketContainsPersonalDataItem(int $packetId): void
    {
        foreach ($this->itemModel->findWithTemplatesByPacketId($packetId) as $item) {
            if ((string) ($item->template_code ?? '') === self::PERSONAL_DATA_TEMPLATE_CODE) {
                return;
            }
        }

        throw new RuntimeException('A csomagból hiányzik a személyes adatok nyilatkozata, ezért nem küldhető ki.');
    }

    private function assertNoBlockingPacketForRelation(EmploymentRelation $relation, int $taxYear, ?int $excludePacketId = null): void
    {
        $existingPacket = $this->packetModel->findBlockingByPersonCompanyAndTaxYearForOpenRelations(
            (int) $relation->person_id,
            (int) $relation->company_id,
            $taxYear,
            $excludePacketId
        );

        if (!$existingPacket) {
            return;
        }

        throw new RuntimeException(
            'Ehhez a nyitott jogviszonyhoz ennél a cégnél erre az adóévre már létezik nyilatkozatcsomag: #'
            . $existingPacket->id
        );
    }

}
