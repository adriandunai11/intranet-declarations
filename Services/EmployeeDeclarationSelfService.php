<?php

namespace App\Modules\Declarations\Services;

use App\Models\BasicdataModel;
use App\Modules\Declarations\Entities\DeclarationInvitation;
use App\Modules\Declarations\Entities\DeclarationPacket;
use App\Modules\Declarations\Entities\DeclarationPacketItem;
use App\Modules\Declarations\Entities\DeclarationTemplate;
use App\Modules\Declarations\Models\DeclarationAuditLogModel;
use App\Modules\Declarations\Models\DeclarationInvitationModel;
use App\Modules\Declarations\Models\DeclarationPacketItemModel;
use App\Modules\Declarations\Models\DeclarationPacketModel;
use App\Modules\Declarations\Models\DeclarationSubmissionModel;
use App\Modules\Declarations\Models\DeclarationTemplateModel;
use App\Modules\Declarations\Models\PersonModel;
use App\Modules\Declarations\Presenters\Submissions\SubmissionPresenterRegistry;
use App\Modules\Declarations\Services\DeclarationForms\DeclarationFormRegistry;
use DateTime;
use RuntimeException;

class EmployeeDeclarationSelfService
{
    private const PERSONAL_DATA_TEMPLATE_CODE = 'personal_data_statement';
    private const BANK_ACCOUNT_CHANGE_TEMPLATE_CODE = 'bank_account_change_statement';
    private const CHILD_EXTRA_LEAVE_TEMPLATE_CODE = 'child_extra_leave_statement';
    private const UNDER_3_CHILD_WORK_SCHEDULE_TEMPLATE_CODE = 'under_3_child_work_schedule_statement';

    protected PersonModel $personModel;
    protected DeclarationTemplateModel $templateModel;
    protected DeclarationPacketModel $packetModel;
    protected DeclarationPacketItemModel $itemModel;
    protected DeclarationSubmissionModel $submissionModel;
    protected DeclarationInvitationModel $invitationModel;
    protected BasicdataModel $basicdataModel;
    protected InvitationTokenService $tokenService;
    protected DeclarationFormRegistry $formRegistry;
    protected SubmissionPresenterRegistry $submissionPresenterRegistry;
    protected DeclarationAuditLogModel $auditLogModel;
    protected IntranetUserLinkService $intranetUserLinkService;

    public function __construct()
    {
        $this->personModel = new PersonModel();
        $this->templateModel = new DeclarationTemplateModel();
        $this->packetModel = new DeclarationPacketModel();
        $this->itemModel = new DeclarationPacketItemModel();
        $this->submissionModel = new DeclarationSubmissionModel();
        $this->invitationModel = new DeclarationInvitationModel();
        $this->basicdataModel = new BasicdataModel();
        $this->tokenService = new InvitationTokenService();
        $this->formRegistry = new DeclarationFormRegistry();
        $this->submissionPresenterRegistry = new SubmissionPresenterRegistry();
        $this->auditLogModel = new DeclarationAuditLogModel();
        $this->intranetUserLinkService = new IntranetUserLinkService();
    }

    /**
     * @return array{person:object,companies:array,templates:array,packets:array,defaultTaxYear:int}
     */
    public function dashboardForUser(int $userId): array
    {
        $person = $this->personForUser($userId);
        $defaultTaxYear = $this->defaultTaxYear();
        $packets = $this->packetModel->findByPersonId((int) $person->id);

        return [
            'person' => $person,
            'companies' => $this->activeCompanies(),
            'templates' => $this->availableTemplates($defaultTaxYear),
            'packets' => $packets,
            'defaultTaxYear' => $defaultTaxYear,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function packetDetailsForUser(int $userId, int $packetId): array
    {
        $person = $this->personForUser($userId);
        $packet = $this->packetForPerson($packetId, (int) $person->id);
        $company = !empty($packet->company_id)
            ? $this->basicdataModel->where('type', 'division')->where('id', (int) $packet->company_id)->first()
            : null;
        $items = $this->itemModel->findWithTemplatesByPacketId((int) $packet->id);
        $submissionsByItemId = $this->submissionModel->findByPacketIdIndexedByItemId((int) $packet->id);
        $itemDetails = [];

        foreach ($items as $item) {
            $submission = $submissionsByItemId[(int) $item->id] ?? null;

            $itemDetails[] = [
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
            ];
        }

        return [
            'person' => $person,
            'packet' => $packet,
            'company' => $company,
            'items' => $items,
            'itemDetails' => $itemDetails,
        ];
    }

    public function assertCanViewPacketItemForUser(int $userId, int $packetId, int $itemId): void
    {
        $person = $this->personForUser($userId);
        $packet = $this->packetForPerson($packetId, (int) $person->id);

        foreach ($this->itemModel->findByPacketId((int) $packet->id) as $item) {
            if ((int) $item->id === $itemId) {
                return;
            }
        }

        throw new RuntimeException('A kiválasztott nyilatkozat nem tartozik ehhez a csomaghoz.');
    }

    /**
     * @return array{packet_id:int,invitation_id:int,url:string,expires_at:string}
     */
    public function startForEmployee(int $userId, int $companyId, int $taxYear, array $templateIds): array
    {
        $person = $this->personForUser($userId);
        $company = $this->companyForSelfService($companyId);
        $taxYear = $this->normalizeTaxYear($taxYear);

        $this->assertNoOpenPacketForPerson((int) $person->id);
        $this->assertInitialPacketCanBeSelfStarted($person);

        $templates = $this->selectedTemplates($taxYear, $templateIds);
        if ($templates === []) {
            throw new RuntimeException('Legalább egy indítható nyilatkozatot ki kell választani.');
        }

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
        $processDate = date('Y-m-d');

        $db = db_connect();
        $db->transBegin();

        try {
            $packetId = $this->packetModel->insert([
                'person_id' => (int) $person->id,
                'company_id' => (int) $company->id,
                'primary_recruiter_user_id' => null,
                'status' => DeclarationPacket::STATUS_SENT,
                'flow_type' => DeclarationPacket::FLOW_SELF_SERVICE,
                'tax_year' => $taxYear,
                'process_date' => $processDate,
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
                    'selection_source' => DeclarationPacketItem::SOURCE_SELF_SERVICE_PRIMARY,
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
                    'company_id' => (int) $company->id,
                    'tax_year' => $taxYear,
                    'process_date' => $processDate,
                    'flow_type' => DeclarationPacket::FLOW_SELF_SERVICE,
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
            $person = $this->intranetUserLinkService->linkMatchingPersonForUser($userId);
        }

        if (!$person) {
            throw new RuntimeException('A bejelentkezett felhasználóhoz nincs összekapcsolt nyilatkozati személy rekord. Ha már van személy adatlapod, munkaügy tudja összekapcsolni az intranet felhasználóddal.');
        }

        return $person;
    }

    /**
     * @return list<object>
     */
    private function activeCompanies(): array
    {
        return $this->basicdataModel
            ->where('type', 'division')
            ->where('status', 1)
            ->orderBy('name', 'ASC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    private function companyForSelfService(int $companyId): object
    {
        $company = $this->basicdataModel
            ->where('type', 'division')
            ->where('status', 1)
            ->where('id', $companyId)
            ->first();

        if (!$company) {
            throw new RuntimeException('A kiválasztott cég nem található vagy nem aktív.');
        }

        return $company;
    }

    private function packetForPerson(int $packetId, int $personId): object
    {
        $packet = $this->packetModel->find($packetId);

        if (!$packet || (int) $packet->person_id !== $personId) {
            throw new RuntimeException('A nyilatkozatcsomag nem található a saját nyilatkozataid között.');
        }

        return $packet;
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
            self::BANK_ACCOUNT_CHANGE_TEMPLATE_CODE,
            self::PERSONAL_DATA_TEMPLATE_CODE,
            self::CHILD_EXTRA_LEAVE_TEMPLATE_CODE,
            self::UNDER_3_CHILD_WORK_SCHEDULE_TEMPLATE_CODE,
        ], true);
    }

    private function assertInitialPacketCanBeSelfStarted(object $person): void
    {
        if ($this->hasPreviousNonCancelledPacket((int) $person->id)) {
            return;
        }

        throw new RuntimeException(
            'Az első belépési nyilatkozatcsomagot a toborzó vagy munkaügy küldi ki. '
            . 'Saját indítás akkor használható, ha korábban már volt nyilatkozatcsomagod.'
        );
    }

    private function hasPreviousNonCancelledPacket(int $personId): bool
    {
        return $this->packetModel
            ->where('person_id', $personId)
            ->where('status !=', DeclarationPacket::STATUS_CANCELLED)
            ->countAllResults() > 0;
    }

    private function assertNoOpenPacketForPerson(int $personId): void
    {
        $packet = $this->packetModel
            ->where('person_id', $personId)
            ->whereNotIn('status', [
                DeclarationPacket::STATUS_CLOSED,
                DeclarationPacket::STATUS_CANCELLED,
            ])
            ->orderBy('id', 'DESC')
            ->first();

        if (!$packet) {
            return;
        }

        throw new RuntimeException(
            'Már van nyitott nyilatkozatcsomagod (#' . (int) $packet->id . ', '
            . $this->packetStatusLabel((string) ($packet->status ?? ''))
            . '). Amíg ez nincs lezárva vagy törölve, nem indítható új csomag.'
        );
    }

    private function packetStatusLabel(string $status): string
    {
        return match ($status) {
            DeclarationPacket::STATUS_DRAFT => 'előkészítés alatt',
            DeclarationPacket::STATUS_SENT => 'kiküldve',
            DeclarationPacket::STATUS_IN_PROGRESS => 'kitöltés alatt',
            DeclarationPacket::STATUS_SUBMITTED => 'ellenőrzésre vár',
            DeclarationPacket::STATUS_APPROVED,
            DeclarationPacket::STATUS_COMPLETED => 'elfogadva, lezárásra vár',
            DeclarationPacket::STATUS_CLOSED => 'lezárva',
            DeclarationPacket::STATUS_CANCELLED => 'törölve',
            default => $status !== '' ? $status : 'ismeretlen',
        };
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
