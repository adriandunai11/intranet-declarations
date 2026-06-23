<?php

namespace App\Modules\Declarations\Services\Public;

use App\Modules\Declarations\Entities\DeclarationPacketItem;
use App\Modules\Declarations\Entities\DeclarationPacket;
use App\Modules\Declarations\Entities\DeclarationSubmission;
use App\Modules\Declarations\Entities\DeclarationTemplate;
use App\Modules\Declarations\Models\DeclarationPacketItemModel;
use App\Modules\Declarations\Models\DeclarationSubmissionModel;
use App\Modules\Declarations\Models\DeclarationAuditLogModel;
use App\Modules\Declarations\Services\DeclarationForms\DeclarationFormHandlerInterface;
use App\Modules\Declarations\Services\Exceptions\DeclarationAlreadySubmittedException;
use App\Modules\Declarations\Services\Exceptions\FormValidationException;
use App\Modules\Declarations\Services\PersonDataUpdateService;
use CodeIgniter\HTTP\IncomingRequest;

class DeclarationSubmissionService
{
    protected DeclarationPacketItemModel $itemModel;
    protected DeclarationSubmissionModel $submissionModel;
    protected PacketWorkflowService $workflowService;
    protected PersonDataUpdateService $personDataUpdateService;
    protected DeclarationAuditLogModel $auditLogModel;

    public function __construct()
    {
        $this->itemModel = new DeclarationPacketItemModel();
        $this->submissionModel = new DeclarationSubmissionModel();
        $this->workflowService = new PacketWorkflowService();
        $this->personDataUpdateService = new PersonDataUpdateService();
        $this->auditLogModel = new DeclarationAuditLogModel();
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

    public function allRequiredItemsCompleted(InvitationContext $context): bool
    {
        foreach ($this->getItemsForContext($context) as $item) {
            $requiredPolicy = (string) ($item->template_required_policy ?? '');
            $isCandidateSelectable = (int) ($item->template_is_candidate_selectable ?? 0) === 1;

            if ($requiredPolicy === DeclarationTemplate::REQUIRED_OPTIONAL || $isCandidateSelectable) {
                continue;
            }

            if (!in_array((string) $item->status, [
                DeclarationPacketItem::STATUS_COMPLETED,
                DeclarationPacketItem::STATUS_ACCEPTED,
            ], true)) {
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
        ], true) && $this->allRequiredItemsCompleted($context);
    }

    public function finalize(InvitationContext $context): void
    {
        if (!$this->canFinalize($context)) {
            throw new \RuntimeException('A végleges beküldéshez minden kötelező dokumentumot ki kell tölteni.');
        }

        $this->workflowService->submitPacketIfReady($context);
    }

    public function submit(
        InvitationContext $context,
        object $item,
        DeclarationFormHandlerInterface $handler,
        IncomingRequest $request
    ): void {
        if ($this->isClosed($item, $context->packet)) {
            throw new DeclarationAlreadySubmittedException('Ezt a nyilatkozatot már beküldted.');
        }

        if ($handler instanceof \App\Modules\Declarations\Services\DeclarationForms\UnsupportedDeclarationHandler) {
            throw new \RuntimeException('Ez a nyilatkozat még nem küldhető be online.');
        }

        $input = $request->getPost() ?? [];
        $validation = service('validation');
        $validation->reset();
        $validation->setRules($handler->rules());

        if (!$validation->run($input)) {
            throw new FormValidationException($validation->getErrors());
        }

        $data = $handler->normalize($input);
        $handler->validateNormalized($data);

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
                throw new DeclarationAlreadySubmittedException('Ezt a nyilatkozatot már beküldted.');
            }

            if ($existingSubmission && in_array((string) $item->status, [
                DeclarationPacketItem::STATUS_REJECTED,
                DeclarationPacketItem::STATUS_COMPLETED,
            ], true)) {
                if (!$this->submissionModel->markAsSubmittedAgain((int) $existingSubmission->id, $data)) {
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
                    'employment_relation_id' => (int) $context->packet->employment_relation_id,
                    'status' => DeclarationSubmission::STATUS_SUBMITTED,
                    'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'submitted_at' => date('Y-m-d H:i:s'),
                ], true);

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
                    'A beálló személyes adatai frissültek a beküldött nyilatkozat alapján.',
                    [
                        'actor_type' => 'candidate',
                        'actor_label' => $context->invitation->email ?? null,
                        'person_id' => (int) $context->packet->person_id,
                        'employment_relation_id' => (int) $context->packet->employment_relation_id,
                        'submission_id' => $submissionId,
                        'template_id' => (int) $item->template_id,
                        'template_code' => $item->template_code ?? null,
                        'updated_fields' => array_keys($data),
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
                $wasResubmission ? 'A beálló javítás után újra beküldte a dokumentumot.' : 'A beálló beküldte a dokumentumot.',
                [
                    'actor_type' => 'candidate',
                    'actor_label' => $context->invitation->email ?? null,
                    'person_id' => (int) $context->packet->person_id,
                    'employment_relation_id' => (int) $context->packet->employment_relation_id,
                    'submission_id' => $submissionId,
                    'old_submission_status' => $oldSubmissionStatus,
                    'new_submission_status' => DeclarationSubmission::STATUS_SUBMITTED,
                    'template_id' => (int) $item->template_id,
                    'template_code' => $item->template_code ?? null,
                    'template_name' => $item->template_name ?? null,
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
}
