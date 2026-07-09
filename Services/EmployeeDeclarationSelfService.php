<?php

namespace App\Modules\Declarations\Services;

use App\Modules\Declarations\Entities\DeclarationInvitation;
use App\Modules\Declarations\Entities\DeclarationPacket;
use App\Modules\Declarations\Entities\DeclarationPacketItem;
use App\Modules\Declarations\Entities\DeclarationTemplate;
use App\Modules\Declarations\Entities\EmploymentRelation;
use App\Modules\Declarations\Models\DeclarationAuditLogModel;
use App\Modules\Declarations\Models\DeclarationInvitationModel;
use App\Modules\Declarations\Models\DeclarationPacketItemModel;
use App\Modules\Declarations\Models\DeclarationPacketModel;
use App\Modules\Declarations\Models\DeclarationTemplateModel;
use App\Modules\Declarations\Models\EmploymentRelationModel;
use App\Modules\Declarations\Models\PersonModel;
use App\Modules\Declarations\Services\DeclarationForms\DeclarationFormRegistry;
use DateTime;
use RuntimeException;

class EmployeeDeclarationSelfService
{
    private const PERSONAL_DATA_TEMPLATE_CODE = 'personal_data_statement';
    private const BANK_ACCOUNT_TEMPLATE_CODE = 'bank_account_statement';

    protected PersonModel $personModel;
    protected EmploymentRelationModel $relationModel;
    protected DeclarationTemplateModel $templateModel;
    protected DeclarationPacketModel $packetModel;
    protected DeclarationPacketItemModel $itemModel;
    protected DeclarationInvitationModel $invitationModel;
    protected InvitationTokenService $tokenService;
    protected DeclarationFormRegistry $formRegistry;
    protected DeclarationAuditLogModel $auditLogModel;

    public function __construct()
    {
        $this->personModel = new PersonModel();
        $this->relationModel = new EmploymentRelationModel();
        $this->templateModel = new DeclarationTemplateModel();
        $this->packetModel = new DeclarationPacketModel();
        $this->itemModel = new DeclarationPacketItemModel();
        $this->invitationModel = new DeclarationInvitationModel();
        $this->tokenService = new InvitationTokenService();
        $this->formRegistry = new DeclarationFormRegistry();
        $this->auditLogModel = new DeclarationAuditLogModel();
    }

    /**
     * @return array{person:object,relations:array,templates:array,packets:array,defaultTaxYear:int}
     */
    public function dashboardForUser(int $userId): array
    {
        $person = $this->personForUser($userId);
        $relations = $this->openRelationsForPerson((int) $person->id, $userId);
        $defaultTaxYear = $this->defaultTaxYear();

        return [
            'person' => $person,
            'relations' => $relations,
            'templates' => $this->availableTemplates($defaultTaxYear),
            'packets' => $this->packetModel->findByPersonId((int) $person->id),
            'defaultTaxYear' => $defaultTaxYear,
        ];
    }

    /**
     * @return array{packet_id:int,invitation_id:int,url:string,expires_at:string}
     */
    public function startForEmployee(int $userId, int $relationId, int $taxYear, array $templateIds): array
    {
        $person = $this->personForUser($userId);
        $relation = $this->relationForUser($relationId, (int) $person->id, $userId);
        $taxYear = $this->normalizeTaxYear($taxYear);
        $templates = $this->selectedTemplates($taxYear, $templateIds);

        if ($templates === []) {
            throw new RuntimeException('Legalább egy indítható nyilatkozatot ki kell választani.');
        }

        $this->assertNoDuplicateOpenPacketItems((int) $relation->id, $taxYear, array_map(static fn($template): int => (int) $template->id, $templates));

        $candidateEmail = trim((string) ($person->email ?? ''));

        if ($candidateEmail === '') {
            throw new RuntimeException('A személyhez nincs e-mail cím rögzítve, ezért nem küldhető ki kitöltési link.');
        }

        if (trim((string) ($person->antra_id ?? '')) === '') {
            throw new RuntimeException('A személyhez nincs Antra azonosító rögzítve, ezért nem indítható azonosított kitöltési link.');
        }

        $plainToken = $this->tokenService->generatePlainToken();
        $tokenHash = $this->tokenService->hashToken($plainToken);
        $expiresAt = (new DateTime('+14 days'))->format('Y-m-d H:i:s');

        $db = db_connect();
        $db->transBegin();

        try {
            $packetId = $this->packetModel->insert([
                'person_id' => (int) $person->id,
                'employment_relation_id' => (int) $relation->id,
                'company_id' => (int) $relation->company_id,
                'status' => DeclarationPacket::STATUS_SENT,
                'tax_year' => $taxYear,
                'created_by_user_id' => $userId,
                'sent_at' => date('Y-m-d H:i:s'),
            ], true);

            if (!$packetId) {
                $errors = $this->packetModel->errors();
                throw new RuntimeException(!empty($errors) ? implode(' ', $errors) : 'A nyilatkozatcsomag létrehozása sikertelen.');
            }

            $sortOrder = 10;

            foreach ($templates as $template) {
                $itemId = $this->itemModel->insert([
                    'packet_id' => (int) $packetId,
                    'template_id' => (int) $template->id,
                    'status' => DeclarationPacketItem::STATUS_PENDING,
                    'sort_order' => $sortOrder,
                ], true);

                if (!$itemId) {
                    $errors = $this->itemModel->errors();
                    throw new RuntimeException(!empty($errors) ? implode(' ', $errors) : 'A nyilatkozatcsomag elemeinek létrehozása sikertelen.');
                }

                $sortOrder += 10;
            }

            $invitationId = $this->invitationModel->insert([
                'person_id' => (int) $person->id,
                'employment_relation_id' => (int) $relation->id,
                'packet_id' => (int) $packetId,
                'email' => $candidateEmail,
                'token_hash' => $tokenHash,
                'status' => DeclarationInvitation::STATUS_SENT,
                'sent_at' => date('Y-m-d H:i:s'),
                'expires_at' => $expiresAt,
            ], true);

            if (!$invitationId) {
                $errors = $this->invitationModel->errors();
                throw new RuntimeException(!empty($errors) ? implode(' ', $errors) : 'A kitöltési link létrehozása sikertelen.');
            }

            $this->auditLogModel->logAction(
                'employee_self_service_packet_created',
                'declaration_packet',
                (int) $packetId,
                (int) $packetId,
                null,
                null,
                DeclarationPacket::STATUS_SENT,
                'Munkavállaló saját indítású nyilatkozatcsomagot hozott létre.',
                [
                    'actor_type' => 'employee',
                    'actor_user_id' => $userId,
                    'person_id' => (int) $person->id,
                    'employment_relation_id' => (int) $relation->id,
                    'tax_year' => $taxYear,
                    'template_ids' => array_map(static fn($template): int => (int) $template->id, $templates),
                    'invitation_id' => (int) $invitationId,
                    'email' => $candidateEmail,
                ]
            );

            if ($db->transStatus() === false) {
                throw new RuntimeException('A saját nyilatkozatcsomag indítása sikertelen.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        $config = config(\App\Modules\Declarations\Config\Declarations::class);

        return [
            'packet_id' => (int) $packetId,
            'invitation_id' => (int) $invitationId,
            'url' => rtrim((string) $config->publicBaseUrl, '/') . '/start/' . $plainToken,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @return list<object>
     */
    public function availableTemplates(int $taxYear): array
    {
        $templates = $this->templateModel->findActiveForYear($this->normalizeTaxYear($taxYear));

        return array_values(array_filter($templates, function ($template): bool {
            return $this->isEmployeeSelectableTemplate($template)
                && $this->formRegistry->hasConcreteHandlerForTemplate($template);
        }));
    }

    private function personForUser(int $userId): object
    {
        if ($userId <= 0) {
            throw new RuntimeException('A saját nyilatkozatok használatához bejelentkezés szükséges.');
        }

        $person = $this->personModel->where('intranet_user_id', $userId)->first();

        if (!$person) {
            throw new RuntimeException('A bejelentkezett felhasználóhoz nincs összekapcsolt nyilatkozati személy rekord.');
        }

        return $person;
    }

    /**
     * @return list<object>
     */
    private function openRelationsForPerson(int $personId, int $userId): array
    {
        $relations = $this->relationModel
            ->where('person_id', $personId)
            ->whereNotIn('status', [
                EmploymentRelation::STATUS_CLOSED,
                EmploymentRelation::STATUS_CANCELLED,
            ])
            ->orderBy('start_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();

        return array_values(array_filter($relations, static function ($relation) use ($userId): bool {
            $relationUserId = (int) ($relation->intranet_user_id ?? 0);

            return $relationUserId === 0 || $relationUserId === $userId;
        }));
    }

    private function relationForUser(int $relationId, int $personId, int $userId): object
    {
        $relation = $this->relationModel->find($relationId);
        $relationUserId = $relation ? (int) ($relation->intranet_user_id ?? 0) : 0;

        if (!$relation
            || (int) $relation->person_id !== $personId
            || ($relationUserId > 0 && $relationUserId !== $userId)
            || !$relation->isOpen()
        ) {
            throw new RuntimeException('A kiválasztott jogviszony nem használható saját nyilatkozat indításához.');
        }

        return $relation;
    }

    /**
     * @return list<object>
     */
    private function selectedTemplates(int $taxYear, array $templateIds): array
    {
        $allowed = [];

        foreach ($this->availableTemplates($taxYear) as $template) {
            $allowed[(int) $template->id] = $template;
        }

        $selected = [];

        foreach (array_values(array_unique(array_filter(array_map('intval', $templateIds)))) as $templateId) {
            if (!isset($allowed[$templateId])) {
                throw new RuntimeException('A kiválasztott nyilatkozat nem indítható munkavállalói felületről.');
            }

            $selected[] = $allowed[$templateId];
        }

        return $selected;
    }

    private function isEmployeeSelectableTemplate(object $template): bool
    {
        $code = (string) ($template->code ?? '');
        $group = (string) ($template->declaration_group ?? '');

        if ($group === DeclarationTemplate::GROUP_TAX && (int) ($template->is_candidate_selectable ?? 0) === 1) {
            return true;
        }

        return in_array($code, [
            self::BANK_ACCOUNT_TEMPLATE_CODE,
            self::PERSONAL_DATA_TEMPLATE_CODE,
        ], true);
    }

    private function assertNoDuplicateOpenPacketItems(int $relationId, int $taxYear, array $templateIds): void
    {
        if ($templateIds === []) {
            return;
        }

        $row = db_connect()
            ->table('declaration_packet_items dpi')
            ->select('dp.id AS packet_id, dt.name AS template_name')
            ->join('declaration_packets dp', 'dp.id = dpi.packet_id', 'inner')
            ->join('declaration_templates dt', 'dt.id = dpi.template_id', 'left')
            ->where('dp.employment_relation_id', $relationId)
            ->where('dp.tax_year', $taxYear)
            ->whereIn('dpi.template_id', $templateIds)
            ->whereNotIn('dp.status', [
                DeclarationPacket::STATUS_CLOSED,
                DeclarationPacket::STATUS_CANCELLED,
            ])
            ->orderBy('dp.id', 'DESC')
            ->get()
            ->getRow();

        if ($row) {
            throw new RuntimeException(
                'Erre az adóévre ebből a nyilatkozatból már van folyamatban vagy lezárt csomag: '
                . ($row->template_name ?: ('#' . $row->packet_id))
            );
        }
    }

    private function defaultTaxYear(): int
    {
        $currentYear = (int) date('Y');

        foreach ([$currentYear, $currentYear + 1] as $taxYear) {
            foreach ($this->templateModel->findCandidateSelectableTaxTemplates($taxYear) as $template) {
                if ($this->formRegistry->hasConcreteHandlerForTemplate($template)) {
                    return $taxYear;
                }
            }
        }

        return $currentYear;
    }

    private function normalizeTaxYear(int $taxYear): int
    {
        if ($taxYear < 2020 || $taxYear > ((int) date('Y') + 2)) {
            throw new RuntimeException('Érvénytelen adóév.');
        }

        return $taxYear;
    }
}
