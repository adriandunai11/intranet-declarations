<?php

namespace App\Modules\Declarations\Services;

use App\Modules\Declarations\Entities\DeclarationInvitation;
use App\Modules\Declarations\Entities\DeclarationPacket;
use App\Modules\Declarations\Entities\DeclarationPacketItem;
use App\Modules\Declarations\Entities\DeclarationTemplate;
use App\Modules\Declarations\Models\DeclarationPacketItemModel;
use App\Modules\Declarations\Models\DeclarationPacketModel;
use App\Modules\Declarations\Models\DeclarationTemplateModel;
use App\Modules\Declarations\Models\PersonModel;
use App\Models\BasicdataModel;
use App\Modules\Declarations\Models\DeclarationInvitationModel;
use App\Modules\Declarations\Services\InvitationTokenService;
use App\Modules\Declarations\Services\RecruiterService;
use App\Modules\Declarations\Models\DeclarationSubmissionModel;
use App\Modules\Declarations\Models\DeclarationAuditLogModel;
use App\Modules\Declarations\Presenters\Submissions\SubmissionPresenterRegistry;
use App\Modules\Declarations\Services\PacketReviewAuthorizationService;
use App\Modules\Declarations\Services\DeclarationForms\DeclarationFormRegistry;

use DateTime;
use RuntimeException;

class DeclarationPacketService
{
    private const PERSONAL_DATA_TEMPLATE_CODE = DeclarationTemplate::CODE_PERSONAL_DATA;

    protected DeclarationPacketModel $packetModel;
    protected DeclarationPacketItemModel $itemModel;
    protected DeclarationTemplateModel $templateModel;
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
        $templates = array_values(array_filter(
            $this->templateModel->findActiveForYear($taxYear),
            fn($template): bool => $this->formRegistry->hasConcreteHandlerForTemplate($template)
        ));

        $this->sortTemplates($templates);

        return $templates;
    }

    public function findPacketsByPersonId(int $personId): array
    {
        return $this->packetModel->findByPersonId($personId);
    }

    public function createForPersonProcess(int $personId, array $data): int
    {
        $person = $this->personModel->find($personId);

        if (!$person) {
            throw new RuntimeException('A személy nem található.');
        }

        $processType = (string) ($data['process_type'] ?? '');

        if (!in_array($processType, ['onboarding', 'other'], true)) {
            throw new RuntimeException('Válaszd ki, hogy belépési vagy egyéb nyilatkozatcsomagot indítasz.');
        }

        $companyId = (int) ($data['company_id'] ?? 0);

        if ($companyId <= 0) {
            throw new RuntimeException('A cég megadása kötelező.');
        }

        $company = $this->basicdataModel
            ->where('type', 'division')
            ->where('status', 1)
            ->where('id', $companyId)
            ->first();

        if (!$company) {
            throw new RuntimeException('A kiválasztott cég nem található vagy nem aktív.');
        }

        $dateField = $processType === 'onboarding' ? 'start_date' : 'request_date';
        $processDate = $this->normalizeProcessDate($data[$dateField] ?? null);
        $primaryRecruiterUserId = (int) ($data['primary_recruiter_user_id'] ?? 0);

        if ($primaryRecruiterUserId <= 0) {
            $loggedUserId = $this->currentUserId();

            if ($loggedUserId !== null && $this->recruiterService->isRecruiter($loggedUserId)) {
                $primaryRecruiterUserId = $loggedUserId;
            }
        }

        if ($primaryRecruiterUserId <= 0) {
            throw new RuntimeException('Az elsődleges toborzó megadása kötelező.');
        }

        $templateIds = array_values(array_unique(array_filter(array_map(
            'intval',
            is_array($data['template_ids'] ?? null) ? $data['template_ids'] : []
        ))));

        if (empty($templateIds)) {
            throw new RuntimeException('Legalább egy nyilatkozatot ki kell választani.');
        }

        $taxYear = array_key_exists('tax_year', $data) && $data['tax_year'] !== ''
            ? (int) $data['tax_year']
            : null;
        $flowType = $processType === 'onboarding'
            ? DeclarationPacket::FLOW_ONBOARDING
            : DeclarationPacket::FLOW_ADMIN_MANUAL;

        return $this->createForPerson(
            $personId,
            $companyId,
            $primaryRecruiterUserId,
            $templateIds,
            $taxYear,
            DeclarationPacketItem::SOURCE_ADMIN_SELECTED,
            $flowType,
            $processDate
        );
    }

    public function getCandidateSelectableTaxTemplates(?int $taxYear = null): array
    {
        return $this->templateModel->findCandidateSelectableTaxTemplates($taxYear);
    }

    public function createForPerson(
        int $personId,
        int $companyId,
        ?int $primaryRecruiterUserId,
        array $templateIds,
        ?int $taxYear = null,
        string $selectionSource = DeclarationPacketItem::SOURCE_ADMIN_SELECTED,
        string $flowType = DeclarationPacket::FLOW_ADMIN_MANUAL,
        ?string $processDate = null
    ): int
    {
        $person = $this->personModel->find($personId);

        if (!$person) {
            throw new RuntimeException('A személy nem található.');
        }

        $company = $this->basicdataModel
            ->where('type', 'division')
            ->where('status', 1)
            ->where('id', $companyId)
            ->first();

        if (!$company) {
            throw new RuntimeException('A kiválasztott cég nem található vagy nem aktív.');
        }

        if (!empty($primaryRecruiterUserId)) {
            $this->recruiterService->ensureRecruiterExists((int) $primaryRecruiterUserId);
        }

        $taxYear = $this->normalizeTaxYear($taxYear);
        $this->assertNoBlockingPacketForPerson($personId);

        $templateIds = array_values(array_unique(array_filter(array_map('intval', $templateIds))));

        if (empty($templateIds)) {
            throw new RuntimeException('Legalább egy nyilatkozatot ki kell választani.');
        }

        if ($this->packetRequiresPersonalDataTemplate($flowType)) {
            $templateIds = $this->withPersonalDataTemplate($templateIds);
        }

        $templates = [];
        foreach ($templateIds as $templateId) {
            $template = $this->templateModel->find($templateId);

            if (!$template || !$template->isActive()) {
                throw new RuntimeException('A kiválasztott nyilatkozat nem található vagy nem aktív.');
            }

            if (!$this->formRegistry->hasConcreteHandlerForTemplate($template)) {
                throw new RuntimeException('A kiválasztott nyilatkozat még nem tölthető ki online: ' . ($template->name ?: $template->code));
            }

            $templates[] = $template;
        }

        $this->sortTemplates($templates);

        $db = db_connect();
        $db->transBegin();

        try {
            $packetId = $this->packetModel->insert([
                'person_id' => $personId,
                'company_id' => $companyId,
                'primary_recruiter_user_id' => $primaryRecruiterUserId ?: null,
                'status' => DeclarationPacket::STATUS_DRAFT,
                'flow_type' => $flowType,
                'tax_year' => $taxYear,
                'process_date' => $processDate,
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
                    'selection_source' => $selectionSource,
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
                        'person_id' => $personId,
                        'template_id' => (int) $template->id,
                        'template_code' => $template->code ?? null,
                        'template_name' => $template->name ?? null,
                        'selection_source' => $selectionSource,
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
                    'person_id' => $personId,
                    'company_id' => $companyId,
                    'primary_recruiter_user_id' => $primaryRecruiterUserId ?: null,
                    'tax_year' => $taxYear,
                    'process_date' => $processDate,
                    'flow_type' => $flowType,
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
        $items = $this->itemModel->findWithTemplatesByPacketId($packet->id);

        $company = null;
        $recruiter = null;
        $recruiterDisplayName = null;

        if (!empty($packet->primary_recruiter_user_id)) {
            $recruiter = $this->recruiterService->findRecruiterById((int) $packet->primary_recruiter_user_id);

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
            'relation' => null,
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

        if (!$person) {
            throw new RuntimeException('A személy nem található.');
        }

        if (in_array((string) $packet->status, [
            DeclarationPacket::STATUS_CLOSED,
            DeclarationPacket::STATUS_CANCELLED,
        ], true)) {
            throw new RuntimeException('Lezárt vagy törölt nyilatkozatcsomaghoz nem küldhető új meghívó link.');
        }

        $this->assertNoBlockingPacketForPerson((int) $packet->person_id, (int) $packet->id);

        if ($this->packetRequiresPersonalDataTemplate((string) ($packet->flow_type ?? ''))) {
            $this->assertPacketContainsPersonalDataItem((int) $packet->id);
        }

        $candidateEmail = trim((string) ($person->email ?? ''));

        if ($candidateEmail === '') {
            throw new RuntimeException('A személyhez nincs e-mail cím rögzítve.');
        }

        $plainToken = $this->tokenService->generatePlainToken();
        $tokenHash = $this->tokenService->hashToken($plainToken);
        $expiresAt = (new DateTime('+14 days'))->format('Y-m-d H:i:s');

        $activeInvitationsBeforeRevoke = $this->invitationModel->countActiveByPacketId((int) $packet->id);

        $oldPacketStatus = (string) $packet->status;

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
                    DeclarationInvitation::STATUS_REVOKED,
                    'Új meghívó link küldése miatt a korábbi aktív linkek visszavonásra kerültek.',
                    [
                        'person_id' => (int) $packet->person_id,
                        'revoked_active_invitations' => $activeInvitationsBeforeRevoke,
                    ]
                );
            }

            $invitationId = $this->invitationModel->insert([
                'person_id' => $packet->person_id,
                'packet_id' => $packet->id,
                'email' => $candidateEmail,
                'token_hash' => $tokenHash,
                'status' => DeclarationInvitation::STATUS_SENT,
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

            $this->auditLogModel->logAction(
                $activeInvitationsBeforeRevoke > 0
                    ? DeclarationAuditLogModel::ACTION_INVITATION_REGENERATED
                    : DeclarationAuditLogModel::ACTION_INVITATION_CREATED,
                'declaration_invitation',
                (int) $invitationId,
                (int) $packet->id,
                null,
                null,
                DeclarationInvitation::STATUS_SENT,
                $activeInvitationsBeforeRevoke > 0 ? 'Új meghívó link létrehozva.' : 'Meghívó link létrehozva.',
                [
                    'person_id' => (int) $packet->person_id,
                    'invitation_id' => (int) $invitationId,
                    'email' => $candidateEmail,
                    'expires_at' => $expiresAt,
                    'old_packet_status' => $oldPacketStatus,
                    'current_packet_status' => in_array($oldPacketStatus, [DeclarationPacket::STATUS_DRAFT, DeclarationPacket::STATUS_SENT], true)
                        ? DeclarationPacket::STATUS_SENT
                        : $oldPacketStatus,
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
        $activeInvitationsBeforeRevoke = $this->invitationModel->countActiveByPacketId((int) $packet->id);

        $db = db_connect();
        $db->transBegin();

        try {
            if (!$this->packetModel->markAsClosed($packetId)) {
                throw new RuntimeException('A nyilatkozatcsomag lezárása sikertelen.');
            }

            if ($activeInvitationsBeforeRevoke > 0) {
                if (!$this->invitationModel->revokeActiveByPacketId((int) $packet->id)) {
                    throw new RuntimeException('A meghívó link visszavonása sikertelen, ezért a nyilatkozatcsomag nem került lezárásra.');
                }

                $this->auditLogModel->logAction(
                    DeclarationAuditLogModel::ACTION_INVITATION_REVOKED,
                    'declaration_invitation',
                    null,
                    $packetId,
                    null,
                    null,
                    DeclarationInvitation::STATUS_REVOKED,
                    'A nyilatkozatcsomag lezárása miatt az aktív meghívó linkek visszavonásra kerültek.',
                    [
                        'person_id' => (int) $packet->person_id,
                        'revoked_active_invitations' => $activeInvitationsBeforeRevoke,
                    ]
                );
            }

            $this->auditLogModel->logAction(
                DeclarationAuditLogModel::ACTION_PACKET_CLOSED,
                'declaration_packet',
                $packetId,
                $packetId,
                null,
                $oldPacketStatus,
                DeclarationPacket::STATUS_CLOSED,
                'A nyilatkozatcsomagot munkaügyi lezárással véglegesítették.',
                [
                    'person_id' => (int) $packet->person_id,
                    'revoked_active_invitations' => $activeInvitationsBeforeRevoke,
                ]
            );

            if ($db->transStatus() === false) {
                throw new RuntimeException('A nyilatkozatcsomag lezárása sikertelen.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
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
                'display_tables' => $this->submissionPresenterRegistry->tablesFor(
                    (string) ($item->template_code ?? ''),
                    $submission
                ),
                'can_review' => (string) $packet->status === DeclarationPacket::STATUS_SUBMITTED
                    && $this->reviewAuthorizationService->canReviewItem(
                        $packet,
                        $item
                    ),
            ];
        }

        $details['reviewItems'] = $reviewItems;
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
                && $this->formRegistry->hasConcreteHandlerForTemplate($template);
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

        if (!$this->formRegistry->hasConcreteHandlerForTemplate($template)) {
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
            'selection_source' => DeclarationPacketItem::SOURCE_ADMIN_SELECTED,
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
                'template_id' => (int) $template->id,
                'template_code' => $template->code ?? null,
                'template_name' => $template->name ?? null,
                'selection_source' => DeclarationPacketItem::SOURCE_ADMIN_SELECTED,
            ]
        );

        return (int) $itemId;
    }


    public function getCandidateSelectableTaxTemplatesForPacket(int $packetId): array
    {
        $packet = $this->findPacket($packetId);

        if (!$this->canCandidateSelectTaxTemplates($packet)) {
            return [];
        }

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

        return array_values(array_filter($templates, function ($template) use ($existingTemplateIds): bool {
            return empty($existingTemplateIds[(int) $template->id])
                && $this->formRegistry->hasConcreteHandlerForTemplate($template);
        }));
    }

    public function addCandidateSelectedTemplate(int $packetId, int $templateId): int
    {
        $packet = $this->findPacket($packetId);

        if (!$this->canCandidateSelectTaxTemplates($packet)) {
            throw new RuntimeException('Ehhez a csomaghoz már nem adható hozzá új nyilatkozat.');
        }

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
            throw new RuntimeException('Ez a nyilatkozat nem választható a kitöltő által.');
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
            'selection_source' => DeclarationPacketItem::SOURCE_CANDIDATE_SELECTED,
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
            'Kitöltő által választható adóügyi nyilatkozat hozzáadva a csomaghoz.',
            [
                'person_id' => (int) $packet->person_id,
                'template_id' => (int) $template->id,
                'template_code' => $template->code ?? null,
                'template_name' => $template->name ?? null,
                'selection_source' => DeclarationPacketItem::SOURCE_CANDIDATE_SELECTED,
                'tax_year' => $template->tax_year ?? null,
            ]
        );

        return (int) $itemId;
    }

    private function canCandidateSelectTaxTemplates(object $packet): bool
    {
        return !in_array((string) $packet->status, [
            DeclarationPacket::STATUS_APPROVED,
            DeclarationPacket::STATUS_SUBMITTED,
            DeclarationPacket::STATUS_CLOSED,
            DeclarationPacket::STATUS_COMPLETED,
            DeclarationPacket::STATUS_CANCELLED,
        ], true);
    }

    private function normalizeTaxYear(?int $taxYear): int
    {
        return $taxYear ?: (int) date('Y');
    }

    private function packetRequiresPersonalDataTemplate(string $flowType): bool
    {
        return $flowType === DeclarationPacket::FLOW_ONBOARDING;
    }

    private function withPersonalDataTemplate(array $templateIds): array
    {
        $template = $this->templateModel->findByCode(self::PERSONAL_DATA_TEMPLATE_CODE);

        if (!$template || !$template->isActive()) {
            throw new RuntimeException('A személyes adatok nyilatkozat nem található vagy nem aktív.');
        }

        $templateIds[] = (int) $template->id;

        return array_values(array_unique(array_map('intval', $templateIds)));
    }

    private function sortTemplates(array &$templates): void
    {
        $originalOrder = [];

        foreach ($templates as $index => $template) {
            $originalOrder[(int) ($template->id ?? 0)] = $index;
        }

        usort($templates, function ($left, $right) use ($originalOrder): int {
            $leftCode = (string) ($left->code ?? '');
            $rightCode = (string) ($right->code ?? '');
            $leftPriority = DeclarationTemplate::displayPriorityForCode($leftCode);
            $rightPriority = DeclarationTemplate::displayPriorityForCode($rightCode);

            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            return ($originalOrder[(int) ($left->id ?? 0)] ?? 0)
                <=> ($originalOrder[(int) ($right->id ?? 0)] ?? 0);
        });
    }

    private function assertCreatableTemplateIds(array $templateIds, string $flowType): void
    {
        if ($this->packetRequiresPersonalDataTemplate($flowType)) {
            $templateIds = $this->withPersonalDataTemplate($templateIds);
        }

        foreach ($templateIds as $templateId) {
            $template = $this->templateModel->find((int) $templateId);

            if (!$template || !$template->isActive()) {
                throw new RuntimeException('A kiválasztott nyilatkozat nem található vagy nem aktív.');
            }

            if (!$this->formRegistry->hasConcreteHandlerForTemplate($template)) {
                throw new RuntimeException('A kiválasztott nyilatkozat még nem tölthető ki online: ' . ($template->name ?: $template->code));
            }
        }
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

    private function assertNoBlockingPacketForPerson(int $personId, ?int $excludePacketId = null): void
    {
        $existingPacket = $this->packetModel->findOpenBlockingByPerson($personId, $excludePacketId);

        if (!$existingPacket) {
            return;
        }

        throw new RuntimeException(
            'Ehhez a személyhez már van nyitott nyilatkozatcsomag: #'
            . $existingPacket->id
            . '. Új csomag akkor indítható, ha a korábbi csomagot lezárták vagy törölték.'
        );
    }

    private function normalizeProcessDate($value): string
    {
        $date = trim((string) ($value ?? ''));

        if ($date === '' || strtotime($date) === false) {
            throw new RuntimeException('A dátum megadása kötelező.');
        }

        return date('Y-m-d', strtotime($date));
    }

    private function currentUserId(): ?int
    {
        if (!function_exists('logged')) {
            return null;
        }

        $id = logged('id');

        return $id ? (int) $id : null;
    }

}
