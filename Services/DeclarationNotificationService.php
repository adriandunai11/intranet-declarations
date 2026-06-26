<?php

namespace App\Modules\Declarations\Services;

use App\Modules\Declarations\Entities\DeclarationSubmission;
use App\Modules\Declarations\Models\DeclarationPacketModel;
use App\Modules\Declarations\Models\DeclarationPacketItemModel;
use App\Modules\Declarations\Models\EmploymentRelationModel;
use App\Modules\Declarations\Models\PersonModel;
use App\Modules\Declarations\Models\DeclarationAuditLogModel;
use App\Models\BasicdataModel;
use RuntimeException;

class DeclarationNotificationService
{
    protected PersonModel $personModel;
    protected DeclarationPacketModel $packetModel;
    protected DeclarationPacketItemModel $itemModel;
    protected EmploymentRelationModel $relationModel;
    protected DeclarationAuditLogModel $auditLogModel;
    protected BasicdataModel $basicdataModel;
    protected RecruiterService $recruiterService;

    public function __construct()
    {
        $this->personModel = new PersonModel();
        $this->packetModel = new DeclarationPacketModel();
        $this->itemModel = new DeclarationPacketItemModel();
        $this->relationModel = new EmploymentRelationModel();
        $this->auditLogModel = new DeclarationAuditLogModel();
        $this->basicdataModel = new BasicdataModel();
        $this->recruiterService = new RecruiterService();
    }

    public function notifyRejectedSubmission(object $item, DeclarationSubmission $submission, string $reviewNote): void
    {
        $this->notifyRejectedPacketItems((int) $submission->packet_id);
    }

    public function notifyRejectedPacketItems(int $packetId): void
    {
        $packet = $this->packetModel->find($packetId);

        if (!$packet) {
            throw new RuntimeException('A nyilatkozatcsomag nem található az értesítéshez.');
        }

        $person = $this->personModel->find((int) $packet->person_id);

        if (!$person || empty($person->email)) {
            throw new RuntimeException('A beálló e-mail címe nem található az értesítéshez.');
        }

        $personName = method_exists($person, 'fullName')
            ? $person->fullName()
            : trim(($person->lastname ?? '') . ' ' . ($person->firstname ?? ''));

        $rejectedItems = [];

        foreach ($this->itemModel->findWithTemplatesByPacketId((int) $packet->id) as $item) {
            if ((string) $item->status !== 'rejected') {
                continue;
            }

            $rejectedItems[] = [
                'name' => $item->template_name ?: 'Nyilatkozat',
                'review_note' => (string) ($item->review_note ?? ''),
            ];
        }

        if ($rejectedItems === []) {
            throw new RuntimeException('Nincs elutasított nyilatkozat az értesítéshez.');
        }

        $subject = count($rejectedItems) === 1
            ? 'Javítás szükséges egy nyilatkozathoz'
            : 'Javítás szükséges több nyilatkozathoz';

        $message = view('App\Modules\Declarations\Views\emails\rejected_submission', [
            'personName' => $personName,
            'rejectedItems' => $rejectedItems,
            'declarationName' => $rejectedItems[0]['name'] ?? 'Nyilatkozat',
            'reviewNote' => $rejectedItems[0]['review_note'] ?? '',
        ]);

        $config = config(\App\Modules\Declarations\Config\Declarations::class);

        $email = service('email');
        $email->setFrom($config->mailFromEmail, $config->mailFromName);
        $email->setTo($person->email);
        $email->setSubject($subject);
        $email->setMessage($message);
        $email->setMailType('html');

        if (!$email->send()) {
            log_message('error', 'Rejected submission notification email failed: ' . print_r($email->printDebugger(['headers']), true));

            throw new RuntimeException('Az értesítő e-mail küldése sikertelen.');
        }

        $this->auditLogModel->logAction(
            DeclarationAuditLogModel::ACTION_REJECTION_EMAIL_SENT,
            'declaration_packet',
            (int) $packet->id,
            (int) $packet->id,
            null,
            null,
            null,
            'Javítási összesítő e-mail kiküldve a beállónak.',
            [
                'person_id' => (int) $person->id,
                'employment_relation_id' => (int) $packet->employment_relation_id,
                'email' => $person->email,
                'rejected_count' => count($rejectedItems),
                'rejected_items' => array_column($rejectedItems, 'name'),
            ]
        );

        log_message('info', sprintf(
            'Rejected submission notification sent. Person ID: %d, Email: %s, Packet ID: %d',
            (int) $person->id,
            $person->email,
            (int) $packet->id
        ));
    }

    public function notifyPacketSubmittedForReview(int $packetId): void
    {
        $packet = $this->packetModel->find($packetId);

        if (!$packet) {
            throw new RuntimeException('A nyilatkozatcsomag nem található az értesítéshez.');
        }

        $person = $this->personModel->find((int) $packet->person_id);

        if (!$person) {
            throw new RuntimeException('A személy nem található az értesítéshez.');
        }

        $relation = $this->relationModel->find((int) $packet->employment_relation_id);
        $company = null;

        if (!empty($packet->company_id)) {
            $company = $this->basicdataModel
                ->where('type', 'division')
                ->where('id', (int) $packet->company_id)
                ->first();
        }

        $config = config(\App\Modules\Declarations\Config\Declarations::class);
        $recipients = [];
        $recruiterName = '-';

        if ($relation && !empty($relation->primary_recruiter_user_id)) {
            $recruiter = $this->recruiterService->findRecruiterById((int) $relation->primary_recruiter_user_id);

            if ($recruiter) {
                $recruiterName = $this->recruiterService->getDisplayName($recruiter);
                $recruiterEmail = $this->recruiterService->getEmail($recruiter);

                if ($recruiterEmail !== '') {
                    $recipients[] = $recruiterEmail;
                }
            }
        }

        $recipients[] = $config->payrollReviewEmail;
        $recipients = array_values(array_unique(array_filter($recipients)));

        if ($recipients === []) {
            throw new RuntimeException('Nincs címzett a nyilatkozatcsomag ellenőrzési értesítéséhez.');
        }

        $personName = method_exists($person, 'fullName')
            ? $person->fullName()
            : trim(($person->lastname ?? '') . ' ' . ($person->firstname ?? ''));

        $message = view('App\Modules\Declarations\Views\emails\packet_submitted_for_review', [
            'personName' => $personName,
            'packet' => $packet,
            'companyName' => $company->name ?? ('#' . $packet->company_id),
            'recruiterName' => $recruiterName,
        ]);

        $email = service('email');
        $email->setFrom($config->mailFromEmail, $config->mailFromName);
        $email->setTo($recipients);
        $email->setSubject('Nyilatkozatcsomag ellenőrzésre vár');
        $email->setMessage($message);
        $email->setMailType('html');

        if (!$email->send()) {
            log_message('error', 'Packet review notification email failed: ' . print_r($email->printDebugger(['headers']), true));

            throw new RuntimeException('Az ellenőrzési értesítő e-mail küldése sikertelen.');
        }

        $this->auditLogModel->logAction(
            DeclarationAuditLogModel::ACTION_PACKET_REVIEW_EMAIL_SENT,
            'declaration_packet',
            (int) $packet->id,
            (int) $packet->id,
            null,
            null,
            null,
            'Nyilatkozatcsomag ellenőrzési értesítő kiküldve.',
            [
                'person_id' => (int) $packet->person_id,
                'employment_relation_id' => (int) $packet->employment_relation_id,
                'recipients' => $recipients,
            ]
        );

        log_message('info', sprintf(
            'Packet review notification sent. Packet ID: %d, Recipients: %s',
            (int) $packet->id,
            implode(', ', $recipients)
        ));
    }

    public function notifyInvitationLinkCreated(int $packetId, string $invitationUrl): void
    {
        $packet = $this->packetModel->find($packetId);

        if (!$packet) {
            throw new RuntimeException('A nyilatkozatcsomag nem található az értesítéshez.');
        }

        $person = $this->personModel->find((int) $packet->person_id);

        if (!$person || empty($person->email)) {
            throw new RuntimeException('A beálló e-mail címe nem található az értesítéshez.');
        }

        $personName = method_exists($person, 'fullName')
            ? $person->fullName()
            : trim(($person->lastname ?? '') . ' ' . ($person->firstname ?? ''));

        $message = view('App\Modules\Declarations\Views\emails\invitation', [
            'personName' => $personName,
            'invitationUrl' => $invitationUrl,
        ]);

        $config = config(\App\Modules\Declarations\Config\Declarations::class);

        $email = service('email');
        $email->setFrom($config->mailFromEmail, $config->mailFromName);
        $email->setTo($person->email);
        $email->setSubject('Belépéshez szükséges dokumentumok kitöltése');
        $email->setMessage($message);
        $email->setMailType('html');

        if (!$email->send()) {
            log_message('error', 'Invitation notification email failed: ' . print_r($email->printDebugger(['headers']), true));

            throw new RuntimeException('A meghívó e-mail kiküldése sikertelen.');
        }

        $this->auditLogModel->logAction(
            DeclarationAuditLogModel::ACTION_INVITATION_EMAIL_SENT,
            'declaration_packet',
            (int) $packet->id,
            (int) $packet->id,
            null,
            null,
            null,
            'Meghívó e-mail kiküldve a beállónak.',
            [
                'person_id' => (int) $person->id,
                'email' => $person->email,
            ]
        );

        log_message('info', sprintf(
            'Invitation notification sent. Packet ID: %d, Email: %s',
            (int) $packet->id,
            $person->email
        ));
    }

}
