<?php

namespace App\Modules\Declarations\Services\Public;

use App\Modules\Declarations\Entities\DeclarationInvitation;
use App\Modules\Declarations\Entities\DeclarationPacket;
use App\Modules\Declarations\Entities\DeclarationPacketItem;
use App\Modules\Declarations\Entities\DeclarationTemplate;
use App\Modules\Declarations\Entities\EmploymentRelation;
use App\Modules\Declarations\Models\DeclarationAuditLogModel;
use App\Modules\Declarations\Models\DeclarationInvitationModel;
use App\Modules\Declarations\Models\DeclarationPacketItemModel;
use App\Modules\Declarations\Models\DeclarationPacketModel;
use App\Modules\Declarations\Models\EmploymentRelationModel;

class PacketWorkflowService
{
    protected DeclarationInvitationModel $invitationModel;
    protected DeclarationPacketModel $packetModel;
    protected DeclarationPacketItemModel $itemModel;
    protected EmploymentRelationModel $relationModel;
    protected DeclarationAuditLogModel $auditLogModel;

    public function __construct()
    {
        $this->invitationModel = new DeclarationInvitationModel();
        $this->packetModel = new DeclarationPacketModel();
        $this->itemModel = new DeclarationPacketItemModel();
        $this->relationModel = new EmploymentRelationModel();
        $this->auditLogModel = new DeclarationAuditLogModel();
    }

    public function markInvitationOpened(InvitationContext $context): void
    {
        $oldInvitationStatus = (string) $context->invitation->status;

        if (in_array($context->invitation->status, [
            DeclarationInvitation::STATUS_CREATED,
            DeclarationInvitation::STATUS_SENT,
        ], true)) {
            $this->invitationModel->update((int) $context->invitation->id, [
                'status' => DeclarationInvitation::STATUS_OPENED,
                'opened_at' => date('Y-m-d H:i:s'),
            ]);

            $this->auditLogModel->logAction(
                DeclarationAuditLogModel::ACTION_INVITATION_OPENED,
                'declaration_invitation',
                (int) $context->invitation->id,
                (int) $context->packet->id,
                null,
                $oldInvitationStatus,
                DeclarationInvitation::STATUS_OPENED,
                'A beálló megnyitotta a dokumentumkitöltő linket.',
                [
                    'actor_type' => 'candidate',
                    'actor_label' => $context->invitation->email ?? null,
                    'person_id' => (int) $context->packet->person_id,
                    'employment_relation_id' => (int) $context->packet->employment_relation_id,
                    'invitation_id' => (int) $context->invitation->id,
                ]
            );
        }

        if (in_array($context->packet->status, [
            DeclarationPacket::STATUS_SENT,
            DeclarationPacket::STATUS_DRAFT,
        ], true)) {
            $this->packetModel->markAsInProgress((int) $context->packet->id);

            $this->auditLogModel->logAction(
                DeclarationAuditLogModel::ACTION_PACKET_STATUS_CHANGED,
                'declaration_packet',
                (int) $context->packet->id,
                (int) $context->packet->id,
                null,
                (string) $context->packet->status,
                DeclarationPacket::STATUS_IN_PROGRESS,
                'A beálló megnyitotta a dokumentumkitöltő felületet.',
                [
                    'actor_type' => 'candidate',
                    'actor_label' => $context->invitation->email ?? null,
                    'person_id' => (int) $context->packet->person_id,
                    'employment_relation_id' => (int) $context->packet->employment_relation_id,
                    'invitation_id' => (int) $context->invitation->id,
                ]
            );
        }

        if (!empty($context->packet->employment_relation_id)) {
            $relation = $this->relationModel->find((int) $context->packet->employment_relation_id);

            if ($relation && in_array($relation->status, [
                EmploymentRelation::STATUS_INVITED,
                EmploymentRelation::STATUS_ONBOARDING,
            ], true)) {
                $oldRelationStatus = (string) $relation->status;
                $this->relationModel->updateStatus((int) $relation->id, EmploymentRelation::STATUS_IN_PROGRESS);

                $this->auditLogModel->logAction(
                    DeclarationAuditLogModel::ACTION_RELATION_STATUS_CHANGED,
                    'declaration_employment_relation',
                    (int) $relation->id,
                    (int) $context->packet->id,
                    null,
                    $oldRelationStatus,
                    EmploymentRelation::STATUS_IN_PROGRESS,
                    'A beálló megnyitotta a dokumentumkitöltő felületet.',
                    [
                        'actor_type' => 'candidate',
                        'actor_label' => $context->invitation->email ?? null,
                        'person_id' => (int) $context->packet->person_id,
                        'employment_relation_id' => (int) $relation->id,
                        'invitation_id' => (int) $context->invitation->id,
                    ]
                );
            }
        }
    }

    public function completeItemAndClosePacketIfReady(InvitationContext $context, int $itemId): void
    {
        if (!$this->itemModel->resetReviewForResubmission($itemId)) {
            throw new \RuntimeException('A nyilatkozat státuszának frissítése sikertelen.');
        }
    }

    public function submitPacketIfReady(InvitationContext $context): void
    {
        if (!$this->allRequiredItemsSubmittedOrAccepted((int) $context->packet->id)) {
            throw new \RuntimeException('A végleges beküldéshez minden kötelező dokumentumot ki kell tölteni.');
        }

        $oldPacketStatus = (string) $context->packet->status;

        if (in_array($oldPacketStatus, [
            DeclarationPacket::STATUS_SUBMITTED,
            DeclarationPacket::STATUS_APPROVED,
            DeclarationPacket::STATUS_CLOSED,
            DeclarationPacket::STATUS_COMPLETED,
        ], true)) {
            return;
        }

        if ($oldPacketStatus !== DeclarationPacket::STATUS_SUBMITTED) {
            $this->packetModel->markAsSubmitted((int) $context->packet->id);

            $this->auditLogModel->logAction(
                DeclarationAuditLogModel::ACTION_PACKET_SUBMITTED,
                'declaration_packet',
                (int) $context->packet->id,
                (int) $context->packet->id,
                null,
                $oldPacketStatus,
                DeclarationPacket::STATUS_SUBMITTED,
                'A beálló minden kötelező dokumentumot beküldött, a csomag ellenőrzésre vár.',
                [
                    'actor_type' => 'candidate',
                    'actor_label' => $context->invitation->email ?? null,
                    'person_id' => (int) $context->packet->person_id,
                    'employment_relation_id' => (int) $context->packet->employment_relation_id,
                    'invitation_id' => (int) $context->invitation->id,
                ]
            );
        }
    }

    private function allRequiredItemsSubmittedOrAccepted(int $packetId): bool
    {
        foreach ($this->itemModel->findWithTemplatesByPacketId($packetId) as $item) {
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
}
