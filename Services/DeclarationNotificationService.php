<?php

namespace App\Modules\Declarations\Services;

use App\Modules\Declarations\Entities\DeclarationSubmission;
use App\Modules\Declarations\Models\DeclarationPacketModel;
use App\Modules\Declarations\Models\PersonModel;
use App\Modules\Declarations\Models\DeclarationTemplateModel;
use App\Modules\Declarations\Models\DeclarationAuditLogModel;
use RuntimeException;

class DeclarationNotificationService
{
    protected PersonModel $personModel;
    protected DeclarationPacketModel $packetModel;
    protected DeclarationTemplateModel $templateModel;
    protected DeclarationAuditLogModel $auditLogModel;

    public function __construct()
    {
        $this->personModel = new PersonModel();
        $this->packetModel = new DeclarationPacketModel();
        $this->templateModel = new DeclarationTemplateModel();
        $this->auditLogModel = new DeclarationAuditLogModel();
    }

    public function notifyRejectedSubmission(object $item, DeclarationSubmission $submission, string $reviewNote): void
    {
        $packet = $this->packetModel->find((int) $submission->packet_id);

        if (!$packet) {
            throw new RuntimeException('A nyilatkozatcsomag nem található az értesítéshez.');
        }

        $person = $this->personModel->find((int) $submission->person_id);

        if (!$person || empty($person->email)) {
            throw new RuntimeException('A beálló e-mail címe nem található az értesítéshez.');
        }

        $personName = method_exists($person, 'fullName')
            ? $person->fullName()
            : trim(($person->lastname ?? '') . ' ' . ($person->firstname ?? ''));

        $template = null;

        if (!empty($item->template_id)) {
            $template = $this->templateModel->find((int) $item->template_id);
        }

        $declarationName = $template && !empty($template->name)
            ? $template->name
            : 'Nyilatkozat';

        $subject = 'Javítás szükséges egy nyilatkozathoz';

        $message = view('App\Modules\Declarations\Views\emails\rejected_submission', [
            'personName' => $personName,
            'declarationName' => $declarationName,
            'reviewNote' => $reviewNote,
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
            'declaration_packet_item',
            (int) $item->id,
            (int) $submission->packet_id,
            (int) $item->id,
            null,
            null,
            'Javítási értesítő e-mail kiküldve a beállónak.',
            [
                'person_id' => (int) $person->id,
                'email' => $person->email,
                'declaration_name' => $declarationName,
            ]
        );

        log_message('info', sprintf(
            'Rejected submission notification sent. Person ID: %d, Email: %s, Item ID: %d',
            (int) $person->id,
            $person->email,
            (int) $item->id
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