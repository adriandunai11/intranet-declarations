<?php

namespace App\Modules\Declarations\Services\Public;

use App\Modules\Declarations\Entities\DeclarationPacketItem;
use App\Modules\Declarations\Entities\DeclarationPacket;
use App\Modules\Declarations\Entities\DeclarationSubmission;
use App\Modules\Declarations\Models\DeclarationPacketItemModel;
use App\Modules\Declarations\Models\DeclarationSubmissionModel;
use App\Modules\Declarations\Models\DeclarationAuditLogModel;
use App\Modules\Declarations\Services\DeclarationForms\DeclarationFormHandlerInterface;
use App\Modules\Declarations\Services\Exceptions\DeclarationAlreadySubmittedException;
use App\Modules\Declarations\Services\Exceptions\FormValidationException;
use App\Modules\Declarations\Services\DeclarationNotificationService;
use App\Modules\Declarations\Services\PersonDataUpdateService;
use CodeIgniter\HTTP\IncomingRequest;

class DeclarationSubmissionService
{
    protected DeclarationPacketItemModel $itemModel;
    protected DeclarationSubmissionModel $submissionModel;
    protected PacketWorkflowService $workflowService;
    protected PersonDataUpdateService $personDataUpdateService;
    protected DeclarationAuditLogModel $auditLogModel;
    protected DeclarationNotificationService $notificationService;

    public function __construct()
    {
        $this->itemModel = new DeclarationPacketItemModel();
        $this->submissionModel = new DeclarationSubmissionModel();
        $this->workflowService = new PacketWorkflowService();
        $this->personDataUpdateService = new PersonDataUpdateService();
        $this->auditLogModel = new DeclarationAuditLogModel();
        $this->notificationService = new DeclarationNotificationService();
    }

    public function getItemForContext(InvitationContext $context, int $itemId): object
    {
        $items = $this->itemModel->findWithTemplatesByPacketId((int) $context->packet->id);

        foreach ($items as $item) {
            if ((int) $item->id === $itemId) {
                return $item;
            }
        }

        throw new \RuntimeException('A kiválasztott nyilatkozat nem tartozik ehhez a csomaghoz.');
    }

    public function getItemsForContext(InvitationContext $context): array
    {
        return $this->itemModel->findWithTemplatesByPacketId((int) $context->packet->id);
    }

    public function findSubmissionForItem(int $itemId)
    {
        return $this->submissionModel->findByPacketItemId($itemId);
    }

    public function isClosed(object $item, ?object $packet = null): bool
    {
        if ((string) $item->status === DeclarationPacketItem::STATUS_ACCEPTED) {
            return true;
        }

        if (
            (string) $item->status === DeclarationPacketItem::STATUS_COMPLETED
            && $packet
            && in_array((string) $packet->status, [
                DeclarationPacket::STATUS_DRAFT,
                DeclarationPacket::STATUS_SENT,
                DeclarationPacket::STATUS_IN_PROGRESS,
            ], true)
        ) {
            return false;
        }

        return in_array((string) $item->status, [
            DeclarationPacketItem::STATUS_COMPLETED,
        ], true);
    }

    public function submissionsByItemId(int $packetId): array
    {
        return $this->submissionModel->findByPacketIdIndexedByItemId($packetId);
    }

    public function allPacketItemsCompleted(InvitationContext $context): bool
    {
        $items = $this->getItemsForContext($context);

        if ($items === []) {
            return false;
        }

        foreach ($items as $item) {
            if (!$this->isItemCompletedForFinalize($item)) {
                return false;
            }
        }

        return true;
    }

    public function canFinalize(InvitationContext $context): bool
    {
        return in_array((string) $context->packet->status, [
            DeclarationPacket::STATUS_DRAFT,
            DeclarationPacket::STATUS_SENT,
            DeclarationPacket::STATUS_IN_PROGRESS,
        ], true) && $this->allPacketItemsCompleted($context);
    }

    public function finalize(InvitationContext $context): void
    {
        if (!$this->canFinalize($context)) {
            throw new \RuntimeException('A végleges beküldéshez minden csomagban lévő nyilatkozatot ki kell tölteni, vagy a nem kért választható nyilatkozatot el kell távolítani.');
        }

        $submittedNow = $this->workflowService->submitPacketIfReady($context);

        if ($submittedNow) {
            try {
                $this->notificationService->notifyPacketSubmittedForReview((int) $context->packet->id);
            } catch (\Throwable $e) {
                log_message('error', 'Packet review notification failed: ' . $e->getMessage());
                log_message('error', $e->getTraceAsString());
            }
        }
    }

    public function removeCandidateSelectedItem(InvitationContext $context, int $itemId): void
    {
        $item = $this->getItemForContext($context, $itemId);

        if (!$this->canRemoveCandidateSelectedItem($context, $item)) {
            throw new \RuntimeException('Ez a nyilatkozat nem távolítható el a kitöltő által.');
        }

        $submission = $this->findSubmissionForItem($itemId);
        $db = db_connect();
        $db->transBegin();

        try {
            if ($submission) {
                $this->submissionModel->delete((int) $submission->id);
            }

            $this->itemModel->delete($itemId);

            $this->auditLogModel->logAction(
                DeclarationAuditLogModel::ACTION_OPTIONAL_TEMPLATE_REMOVED,
                'declaration_packet_item',
                $itemId,
                (int) $context->packet->id,
                $itemId,
                (string) $item->status,
                null,
                'Kitöltő által választható nyilatkozat eltávolítva a csomagból.',
                [
                    'actor_type' => 'candidate',
                    'actor_label' => $context->invitation->email ?? null,
                    'person_id' => (int) $context->packet->person_id,
                    'submission_id' => $submission ? (int) $submission->id : null,
                    'template_id' => (int) $item->template_id,
                    'template_code' => $item->template_code ?? null,
                    'template_name' => $item->template_name ?? null,
                ]
            );

            if ($db->transStatus() === false) {
                throw new \RuntimeException('A nyilatkozat eltávolítása sikertelen.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function submit(
        InvitationContext $context,
        object $item,
        DeclarationFormHandlerInterface $handler,
        IncomingRequest $request
    ): void {
        if ($this->isClosed($item, $context->packet)) {
            throw new DeclarationAlreadySubmittedException('Ezt a nyilatkozatot már beküldte.');
        }

        if ($handler instanceof \App\Modules\Declarations\Services\DeclarationForms\UnsupportedDeclarationHandler) {
            throw new \RuntimeException('Ez a nyilatkozat még nem küldhető be online.');
        }

        $input = $request->getPost() ?? [];
        $validation = service('validation');
        $validation->reset();
        $validation->setRules($handler->rules());

        if (!$validation->run($input)) {
            throw new FormValidationException($this->localizedValidationErrors($validation->getErrors()));
        }

        $data = $handler->normalize($input);

        try {
            $handler->validateNormalized($data);
        } catch (\RuntimeException $e) {
            throw new FormValidationException([$e->getMessage()]);
        }

        $submissionDataJson = $this->encodeSubmissionData($data);
        $submissionHash = hash('sha256', $submissionDataJson);
        $submissionEvidence = $this->submissionEvidence($context, $request, $submissionHash);

        $db = db_connect();
        $db->transBegin();

        try {
            $existingSubmission = $this->findSubmissionForItem((int) $item->id);
            $wasResubmission = $existingSubmission && (string) $item->status === DeclarationPacketItem::STATUS_REJECTED;
            $submissionId = $existingSubmission ? (int) $existingSubmission->id : null;
            $oldSubmissionStatus = $existingSubmission ? (string) $existingSubmission->status : null;
            $oldItemStatus = (string) $item->status;

            if (
                $existingSubmission && in_array((string) $item->status, [
                    DeclarationPacketItem::STATUS_ACCEPTED,
                ], true)
            ) {
                throw new DeclarationAlreadySubmittedException('Ezt a nyilatkozatot már beküldte.');
            }

            if ($existingSubmission && in_array((string) $item->status, [
                DeclarationPacketItem::STATUS_REJECTED,
                DeclarationPacketItem::STATUS_COMPLETED,
            ], true)) {
                if (!$this->submissionModel->markAsSubmittedAgain((int) $existingSubmission->id, $submissionDataJson, $submissionEvidence)) {
                    $errors = $this->submissionModel->errors();

                    throw new \RuntimeException(
                        !empty($errors) ? implode(' ', $errors) : 'A nyilatkozat újrabeküldése sikertelen.'
                    );
                }
            } else {
                $submissionId = $this->submissionModel->insert([
                    'packet_id' => (int) $context->packet->id,
                    'packet_item_id' => (int) $item->id,
                    'template_id' => (int) $item->template_id,
                    'person_id' => (int) $context->packet->person_id,
                    'status' => DeclarationSubmission::STATUS_SUBMITTED,
                    'data_json' => $submissionDataJson,
                    'submitted_at' => date('Y-m-d H:i:s'),
                ] + $submissionEvidence, true);

                if (!$submissionId) {
                    $errors = $this->submissionModel->errors();

                    throw new \RuntimeException(
                        !empty($errors) ? implode(' ', $errors) : 'A nyilatkozat mentése sikertelen.'
                    );
                }
            }

            if ((string) ($item->template_code ?? '') === 'personal_data_statement') {
                $this->personDataUpdateService->updateFromPersonalDataDeclaration((int) $context->packet->person_id, $data);

                $this->auditLogModel->logAction(
                    DeclarationAuditLogModel::ACTION_PERSON_DATA_UPDATED,
                    'declaration_person',
                    (int) $context->packet->person_id,
                    (int) $context->packet->id,
                    (int) $item->id,
                    null,
                    null,
                    'A kitöltő személyes adatai frissültek a beküldött nyilatkozat alapján.',
                    [
                        'actor_type' => $submissionEvidence['submitter_type'] ?? 'candidate',
                        'actor_user_id' => $submissionEvidence['submitter_user_id'] ?? null,
                        'actor_label' => $submissionEvidence['submitter_label'] ?? ($context->invitation->email ?? null),
                        'person_id' => (int) $context->packet->person_id,
                        'submission_id' => $submissionId,
                        'template_id' => (int) $item->template_id,
                        'template_code' => $item->template_code ?? null,
                        'updated_fields' => array_keys($data),
                        'submission_hash' => $submissionHash,
                        'hash_algorithm' => 'sha256',
                    ]
                );
            }

            $this->auditLogModel->logAction(
                $wasResubmission ? DeclarationAuditLogModel::ACTION_ITEM_RESUBMITTED : DeclarationAuditLogModel::ACTION_ITEM_SUBMITTED,
                'declaration_packet_item',
                (int) $item->id,
                (int) $context->packet->id,
                (int) $item->id,
                $oldItemStatus,
                DeclarationPacketItem::STATUS_COMPLETED,
                $wasResubmission ? 'A kitöltő javítás után újra beküldte a nyilatkozatot.' : 'A kitöltő beküldte a nyilatkozatot.',
                [
                    'actor_type' => $submissionEvidence['submitter_type'] ?? 'candidate',
                    'actor_user_id' => $submissionEvidence['submitter_user_id'] ?? null,
                    'actor_label' => $submissionEvidence['submitter_label'] ?? ($context->invitation->email ?? null),
                    'person_id' => (int) $context->packet->person_id,
                    'submission_id' => $submissionId,
                    'old_submission_status' => $oldSubmissionStatus,
                    'new_submission_status' => DeclarationSubmission::STATUS_SUBMITTED,
                    'template_id' => (int) $item->template_id,
                    'template_code' => $item->template_code ?? null,
                    'template_name' => $item->template_name ?? null,
                    'submitter_email' => $submissionEvidence['submitter_email'] ?? null,
                    'submitter_user_id' => $submissionEvidence['submitter_user_id'] ?? null,
                    'submission_hash' => $submissionHash,
                    'hash_algorithm' => 'sha256',
                ]
            );

            $this->workflowService->completeItemAndClosePacketIfReady($context, (int) $item->id);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('A nyilatkozat mentése sikertelen.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function canRemoveCandidateSelectedItem(InvitationContext $context, object $item): bool
    {
        return in_array((string) $context->packet->status, [
            DeclarationPacket::STATUS_DRAFT,
            DeclarationPacket::STATUS_SENT,
            DeclarationPacket::STATUS_IN_PROGRESS,
        ], true)
            && (string) ($item->selection_source ?? '') === DeclarationPacketItem::SOURCE_CANDIDATE_SELECTED
            && (string) $item->status !== DeclarationPacketItem::STATUS_ACCEPTED;
    }

    private function encodeSubmissionData(array $data): string
    {
        $json = json_encode(
            $this->sortForStableHash($data),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new \RuntimeException('A nyilatkozat adatai nem kódolhatók mentéshez.');
        }

        return $json;
    }

    private function sortForStableHash($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($this->isListArray($value)) {
            return array_map(fn($item) => $this->sortForStableHash($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortForStableHash($item);
        }

        return $value;
    }

    private function isListArray(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    private function submissionEvidence(InvitationContext $context, IncomingRequest $request, string $submissionHash): array
    {
        $submitterType = 'candidate';
        $submitterUserId = null;
        $email = trim((string) ($context->invitation->email ?? ''));
        $label = $email !== '' ? $email : 'Kitöltő';

        if (
            $context->person
            && !empty($context->person->intranet_user_id)
            && (int) ($context->packet->created_by_user_id ?? 0) === (int) $context->person->intranet_user_id
        ) {
            $submitterType = 'employee';
            $submitterUserId = (int) $context->person->intranet_user_id;
            $personName = method_exists($context->person, 'fullName') ? $context->person->fullName() : '';
            $label = trim($personName . ($email !== '' ? ' (' . $email . ')' : '')) ?: $label;
        }

        return [
            'submitter_type' => $submitterType,
            'submitter_user_id' => $submitterUserId,
            'submitter_label' => $label,
            'submitter_email' => $email !== '' ? $email : null,
            'submitter_ip_address' => $request->getIPAddress(),
            'submitter_user_agent' => substr((string) $request->getUserAgent(), 0, 255),
            'submission_hash' => $submissionHash,
            'hash_algorithm' => 'sha256',
        ];
    }

    private function isItemCompletedForFinalize(object $item): bool
    {
        return in_array((string) $item->status, [
            DeclarationPacketItem::STATUS_COMPLETED,
            DeclarationPacketItem::STATUS_ACCEPTED,
        ], true);
    }

    private function localizedValidationErrors(array $errors): array
    {
        $messages = [
            'birth_name' => 'A születési név megadása kötelező, legalább 3 karakterrel.',
            'mother_name' => 'Az anyja neve megadása kötelező, legalább 3 karakterrel.',
            'birth_place' => 'A születési hely megadása kötelező.',
            'birth_date' => 'A születési dátum megadása kötelező, év-hónap-nap formátumban.',
            'tax_number' => 'Az adóazonosító jelet pontosan, számjegyekkel adja meg.',
            'taj_number' => 'A TAJ számot pontosan, számjegyekkel adja meg.',
            'phone' => 'A telefonszám megadása kötelező.',
            'account_holder' => 'A számlatulajdonos nevének megadása kötelező.',
            'bank_name' => 'A bank nevét akkor kell megadni, ha a bankszámlaszám elejéből nem ismerhető fel automatikusan.',
            'bank_account_number' => 'A bankszámlaszámot 16 vagy 24 számjeggyel adja meg.',
            'confirm_truth' => 'A beküldéshez el kell fogadni a valóságtartalomról szóló nyilatkozatot.',
        ];

        foreach ($errors as $field => $message) {
            if (isset($messages[$field])) {
                $errors[$field] = $messages[$field];
            }
        }

        return $errors;
    }
}
