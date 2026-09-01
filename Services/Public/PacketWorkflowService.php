<?php

namespace App\Modules\Declarations\Services\Public;

use App\Modules\Declarations\Entities\DeclarationInvitation;
use App\Modules\Declarations\Entities\DeclarationPacket;
use App\Modules\Declarations\Entities\DeclarationPacketItem;
use App\Modules\Declarations\Models\DeclarationAuditLogModel;
use App\Modules\Declarations\Models\DeclarationInvitationModel;
use App\Modules\Declarations\Models\DeclarationPacketItemModel;
use App\Modules\Declarations\Models\DeclarationPacketModel;

class PacketWorkflowService
{
    protected DeclarationInvitationModel $invitationModel;
    protected DeclarationPacketModel $packetModel;
    protected DeclarationPacketItemModel $itemModel;
    protected DeclarationAuditLogModel $auditLogModel;

    public function __construct()
    {
        $this->invitationModel = new DeclarationInvitationModel();
        $this->packetModel = new DeclarationPacketModel();
        $this->itemModel = new DeclarationPacketItemModel();
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
                'A kitöltő megnyitotta a nyilatkozatkitöltő linket.',
                [
                    'actor_type' => 'candidate',
                    'actor_label' => $context->invitation->email ?? null,
                    'person_id' => (int) $context->packet->person_id,
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
                'A kitöltő megnyitotta a nyilatkozatkitöltő felületet.',
                [
                    'actor_type' => 'candidate',
                    'actor_label' => $context->invitation->email ?? null,
                    'person_id' => (int) $context->packet->person_id,
                    'invitation_id' => (int) $context->invitation->id,
                ]
            );
        }
    }

    public function completeItemAndClosePacketIfReady(InvitationContext $context, int $itemId): void
    {
        if (!$this->itemModel->resetReviewForResubmission($itemId)) {
            throw new \RuntimeException('A nyilatkozat státuszának frissítése sikertelen.');
        }
    }

    public function submitPacketIfReady(InvitationContext $context): bool
    {
        if (!$this->allPacketItemsSubmittedOrAccepted((int) $context->packet->id)) {
            throw new \RuntimeException('A végleges beküldéshez minden csomagban lévő nyilatkozatot ki kell tölteni, vagy a nem kért választható nyilatkozatot el kell távolítani.');
        }

        $oldPacketStatus = (string) $context->packet->status;

        if (in_array($oldPacketStatus, [
            DeclarationPacket::STATUS_SUBMITTED,
            DeclarationPacket::STATUS_APPROVED,
            DeclarationPacket::STATUS_CLOSED,
            DeclarationPacket::STATUS_COMPLETED,
        ], true)) {
            return false;
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
                'A kitöltő véglegesen beküldte a nyilatkozatcsomagot, a csomag ellenőrzésre vár.',
                [
                    'actor_type' => 'candidate',
                    'actor_label' => $context->invitation->email ?? null,
                    'person_id' => (int) $context->packet->person_id,
                    'invitation_id' => (int) $context->invitation->id,
                ]
            );
        }

        return true;
    }

    private function allPacketItemsSubmittedOrAccepted(int $packetId): bool
    {
        foreach ($this->itemModel->findWithTemplatesByPacketId($packetId) as $item) {
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
