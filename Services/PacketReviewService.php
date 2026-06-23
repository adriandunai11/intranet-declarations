<?php

namespace App\Modules\Declarations\Services;

use App\Modules\Declarations\Entities\DeclarationInvitation;
use App\Modules\Declarations\Entities\DeclarationPacket;
use App\Modules\Declarations\Entities\DeclarationPacketItem;
use App\Modules\Declarations\Entities\DeclarationSubmission;
use App\Modules\Declarations\Entities\DeclarationTemplate;
use App\Modules\Declarations\Entities\EmploymentRelation;
use App\Modules\Declarations\Models\DeclarationAuditLogModel;
use App\Modules\Declarations\Models\DeclarationInvitationModel;
use App\Modules\Declarations\Models\DeclarationPacketItemModel;
use App\Modules\Declarations\Models\DeclarationPacketModel;
use App\Modules\Declarations\Models\DeclarationSubmissionModel;
use App\Modules\Declarations\Models\EmploymentRelationModel;
use RuntimeException;

class PacketReviewService
{
    protected DeclarationPacketItemModel $itemModel;
    protected DeclarationSubmissionModel $submissionModel;
    protected DeclarationPacketModel $packetModel;
    protected EmploymentRelationModel $relationModel;
    protected DeclarationNotificationService $notificationService;
    protected DeclarationInvitationModel $invitationModel;
    protected PacketReviewAuthorizationService $reviewAuthorizationService;
    protected DeclarationAuditLogModel $auditLogModel;

    public function __construct()
    {
        $this->itemModel = new DeclarationPacketItemModel();
        $this->submissionModel = new DeclarationSubmissionModel();
        $this->packetModel = new DeclarationPacketModel();
        $this->relationModel = new EmploymentRelationModel();
        $this->notificationService = new DeclarationNotificationService();
        $this->invitationModel = new DeclarationInvitationModel();
        $this->reviewAuthorizationService = new PacketReviewAuthorizationService();
        $this->auditLogModel = new DeclarationAuditLogModel();
    }

    public function acceptItem(int $packetId, int $itemId): void
    {
        $item = $this->findItemForPacket($packetId, $itemId);
        $submission = $this->submissionModel->findByPacketItemId($itemId);

        if (!$submission) {
            throw new RuntimeException('Ehhez a nyilatkozathoz nincs beküldött adat.');
        }

        $packet = $this->packetModel->find($packetId);
        $relation = $packet ? $this->relationModel->find((int) $packet->employment_relation_id) : null;

        if (!$packet) {
            throw new RuntimeException('A nyilatkozatcsomag nem található.');
        }

        $this->reviewAuthorizationService->assertCanReviewItem($packet, $relation, $item);

        if ($item->status !== DeclarationPacketItem::STATUS_COMPLETED) {
            throw new RuntimeException('Csak beküldött, ellenőrzésre váró nyilatkozat fogadható el.');
        }

        $oldItemStatus = (string) $item->status;
        $oldSubmissionStatus = (string) $submission->status;
        $reviewedBy = function_exists('logged') ? (int) logged('id') : null;

        $db = db_connect();
        $db->transBegin();

        try {
            $this->itemModel->markAsAccepted($itemId, $reviewedBy);
            $this->submissionModel->markAsAccepted((int) $submission->id);

            $this->auditLogModel->logAction(
                DeclarationAuditLogModel::ACTION_ITEM_ACCEPTED,
                'declaration_packet_item',
                $itemId,
                $packetId,
                $itemId,
                $oldItemStatus,
                DeclarationPacketItem::STATUS_ACCEPTED,
                null,
                [
                    'submission_id' => (int) $submission->id,
                    'old_submission_status' => $oldSubmissionStatus,
                    'new_submission_status' => DeclarationSubmission::STATUS_ACCEPTED,
                    'template_id' => (int) $item->template_id,
                    'template_code' => $item->template_code ?? null,
                    'template_name' => $item->template_name ?? null,
                    'reviewed_by_user_id' => $reviewedBy,
                ]
            );

            $this->closePacketIfEveryItemAccepted($packetId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('A nyilatkozat elfogadása sikertelen.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function rejectItem(int $packetId, int $itemId, string $reviewNote): void
    {
        $reviewNote = trim($reviewNote);

        if ($reviewNote === '') {
            throw new RuntimeException('Elutasításkor kötelező megadni a javítás okát.');
        }

        $item = $this->findItemForPacket($packetId, $itemId);
        $submission = $this->submissionModel->findByPacketItemId($itemId);

        if (!$submission) {
            throw new RuntimeException('Ehhez a nyilatkozathoz nincs beküldött adat.');
        }

        $packet = $this->packetModel->find($packetId);
        $relation = $packet ? $this->relationModel->find((int) $packet->employment_relation_id) : null;

        if (!$packet) {
            throw new RuntimeException('A nyilatkozatcsomag nem található.');
        }

        $this->reviewAuthorizationService->assertCanReviewItem($packet, $relation, $item);

        if ($item->status !== DeclarationPacketItem::STATUS_COMPLETED) {
            throw new RuntimeException('Csak beküldött, ellenőrzésre váró nyilatkozat utasítható el.');
        }

        $oldItemStatus = (string) $item->status;
        $oldSubmissionStatus = (string) $submission->status;
        $oldPacketStatus = (string) $packet->status;
        $oldRelationStatus = $relation ? (string) $relation->status : null;
        $reviewedBy = function_exists('logged') ? (int) logged('id') : null;

        $db = db_connect();
        $db->transBegin();

        try {
            $this->itemModel->markAsRejected($itemId, $reviewNote, $reviewedBy);
            $this->submissionModel->markAsRejected((int) $submission->id);

            $this->packetModel->markAsInProgress($packetId);

            if ($oldPacketStatus !== DeclarationPacket::STATUS_IN_PROGRESS) {
                $this->auditLogModel->logAction(
                    DeclarationAuditLogModel::ACTION_PACKET_STATUS_CHANGED,
                    'declaration_packet',
                    $packetId,
                    $packetId,
                    null,
                    $oldPacketStatus,
                    DeclarationPacket::STATUS_IN_PROGRESS,
                    'Nyilatkozat elutasítása miatt a csomag újra folyamatban állapotba került.'
                );
            }

            if ($relation) {
                $this->relationModel->updateStatus((int) $relation->id, EmploymentRelation::STATUS_IN_PROGRESS);

                if ($oldRelationStatus !== EmploymentRelation::STATUS_IN_PROGRESS) {
                    $this->auditLogModel->logAction(
                        DeclarationAuditLogModel::ACTION_RELATION_STATUS_CHANGED,
                        'declaration_employment_relation',
                        (int) $relation->id,
                        $packetId,
                        null,
                        $oldRelationStatus,
                        EmploymentRelation::STATUS_IN_PROGRESS,
                        'Nyilatkozat elutasítása miatt a beléptetési folyamat újra folyamatban állapotba került.'
                    );
                }
            }

            $this->auditLogModel->logAction(
                DeclarationAuditLogModel::ACTION_ITEM_REJECTED,
                'declaration_packet_item',
                $itemId,
                $packetId,
                $itemId,
                $oldItemStatus,
                DeclarationPacketItem::STATUS_REJECTED,
                $reviewNote,
                [
                    'submission_id' => (int) $submission->id,
                    'old_submission_status' => $oldSubmissionStatus,
                    'new_submission_status' => DeclarationSubmission::STATUS_REJECTED,
                    'template_id' => (int) $item->template_id,
                    'template_code' => $item->template_code ?? null,
                    'template_name' => $item->template_name ?? null,
                    'reviewed_by_user_id' => $reviewedBy,
                ]
            );

            if ($db->transStatus() === false) {
                throw new RuntimeException('A nyilatkozat elutasítása sikertelen.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        try {
            $this->notificationService->notifyRejectedSubmission($item, $submission, $reviewNote);
        } catch (\Throwable $e) {
            log_message('error', 'Rejected submission notification failed: ' . $e->getMessage());
        }
    }

    public function reopenItemForCorrection(int $packetId, int $itemId, string $reviewNote): void
    {
        $reviewNote = trim($reviewNote);

        if ($reviewNote === '') {
            throw new RuntimeException('Újranyitáskor kötelező megadni a javítás okát.');
        }

        if (!hasPermissions('declarations_admin_override')) {
            throw new RuntimeException('Nincs jogosultságod admin újranyitásra.');
        }

        $item = $this->findItemForPacket($packetId, $itemId);
        $submission = $this->submissionModel->findByPacketItemId($itemId);

        if (!$submission) {
            throw new RuntimeException('Ehhez a nyilatkozathoz nincs beküldött adat.');
        }

        $oldItemStatus = (string) $item->status;
        $oldSubmissionStatus = (string) $submission->status;

        if (!in_array($oldItemStatus, [
            DeclarationPacketItem::STATUS_COMPLETED,
            DeclarationPacketItem::STATUS_ACCEPTED,
            DeclarationPacketItem::STATUS_REJECTED,
        ], true)) {
            throw new RuntimeException('Csak beküldött, elfogadott vagy már elutasított nyilatkozat nyitható újra.');
        }

        $packet = $this->packetModel->find($packetId);
        $relation = $packet ? $this->relationModel->find((int) $packet->employment_relation_id) : null;

        if (!$packet) {
            throw new RuntimeException('A nyilatkozatcsomag nem található.');
        }

        $oldPacketStatus = (string) $packet->status;
        $oldRelationStatus = $relation ? (string) $relation->status : null;
        $reviewedBy = function_exists('logged') ? (int) logged('id') : null;

        $db = db_connect();
        $db->transBegin();

        try {
            $this->itemModel->markAsRejected($itemId, $reviewNote, $reviewedBy);
            $this->submissionModel->markAsRejected((int) $submission->id);

            $this->packetModel->markAsInProgress($packetId);

            if ($oldPacketStatus !== DeclarationPacket::STATUS_IN_PROGRESS) {
                $this->auditLogModel->logAction(
                    DeclarationAuditLogModel::ACTION_PACKET_STATUS_CHANGED,
                    'declaration_packet',
                    $packetId,
                    $packetId,
                    null,
                    $oldPacketStatus,
                    DeclarationPacket::STATUS_IN_PROGRESS,
                    'Admin újranyitás miatt a csomag újra folyamatban állapotba került.'
                );
            }

            if ($relation) {
                $this->relationModel->updateStatus((int) $relation->id, EmploymentRelation::STATUS_IN_PROGRESS);

                if ($oldRelationStatus !== EmploymentRelation::STATUS_IN_PROGRESS) {
                    $this->auditLogModel->logAction(
                        DeclarationAuditLogModel::ACTION_RELATION_STATUS_CHANGED,
                        'declaration_employment_relation',
                        (int) $relation->id,
                        $packetId,
                        null,
                        $oldRelationStatus,
                        EmploymentRelation::STATUS_IN_PROGRESS,
                        'Admin újranyitás miatt a beléptetési folyamat újra folyamatban állapotba került.'
                    );
                }
            }

            $this->auditLogModel->logAction(
                DeclarationAuditLogModel::ACTION_ITEM_REOPENED,
                'declaration_packet_item',
                $itemId,
                $packetId,
                $itemId,
                $oldItemStatus,
                DeclarationPacketItem::STATUS_REJECTED,
                $reviewNote,
                [
                    'submission_id' => (int) $submission->id,
                    'old_submission_status' => $oldSubmissionStatus,
                    'new_submission_status' => DeclarationSubmission::STATUS_REJECTED,
                    'template_id' => (int) $item->template_id,
                    'template_code' => $item->template_code ?? null,
                    'template_name' => $item->template_name ?? null,
                    'reviewed_by_user_id' => $reviewedBy,
                ]
            );

            if ($db->transStatus() === false) {
                throw new RuntimeException('A nyilatkozat újranyitása sikertelen.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    private function findItemForPacket(int $packetId, int $itemId)
    {
        $items = $this->itemModel->findWithTemplatesByPacketId($packetId);

        foreach ($items as $item) {
            if ((int) $item->id === $itemId) {
                return $item;
            }
        }

        throw new RuntimeException('A nyilatkozat nem található ebben a csomagban.');
    }

    private function closePacketIfEveryItemAccepted(int $packetId): void
    {
        if (!$this->allRequiredItemsAccepted($packetId)) {
            return;
        }

        $packet = $this->packetModel->find($packetId);

        if (!$packet) {
            throw new RuntimeException('A nyilatkozatcsomag nem található.');
        }

        $oldPacketStatus = (string) $packet->status;
        $this->packetModel->markAsApproved($packetId);

        $this->auditLogModel->logAction(
            DeclarationAuditLogModel::ACTION_PACKET_APPROVED,
            'declaration_packet',
            $packetId,
            $packetId,
            null,
            $oldPacketStatus,
            DeclarationPacket::STATUS_APPROVED,
            'Minden kötelező nyilatkozat elfogadásra került.',
            [
                'person_id' => (int) $packet->person_id,
                'employment_relation_id' => (int) $packet->employment_relation_id,
            ]
        );

        if (!empty($packet->employment_relation_id)) {
            $relation = $this->relationModel->find((int) $packet->employment_relation_id);
            $oldRelationStatus = $relation ? (string) $relation->status : null;

            $this->relationModel->updateStatus(
                (int) $packet->employment_relation_id,
                EmploymentRelation::STATUS_COMPLETED
            );

            $this->auditLogModel->logAction(
                DeclarationAuditLogModel::ACTION_RELATION_STATUS_CHANGED,
                'declaration_employment_relation',
                (int) $packet->employment_relation_id,
                $packetId,
                null,
                $oldRelationStatus,
                EmploymentRelation::STATUS_COMPLETED,
                'Minden kötelező nyilatkozat elfogadásra került.',
                [
                    'person_id' => (int) $packet->person_id,
                    'employment_relation_id' => (int) $packet->employment_relation_id,
                ]
            );
        }

        $activeInvitation = $this->invitationModel->findActiveByPacketId($packetId);

        if ($activeInvitation) {
            $oldInvitationStatus = (string) $activeInvitation->status;

            $this->invitationModel->update((int) $activeInvitation->id, [
                'status' => DeclarationInvitation::STATUS_COMPLETED,
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            $this->auditLogModel->logAction(
                DeclarationAuditLogModel::ACTION_INVITATION_COMPLETED,
                'declaration_invitation',
                (int) $activeInvitation->id,
                $packetId,
                null,
                $oldInvitationStatus,
                DeclarationInvitation::STATUS_COMPLETED,
                'A nyilatkozatcsomag elfogadása után a meghívó lezárásra került.',
                [
                    'person_id' => (int) $packet->person_id,
                    'employment_relation_id' => (int) $packet->employment_relation_id,
                    'invitation_id' => (int) $activeInvitation->id,
                ]
            );
        }
    }

    private function allRequiredItemsAccepted(int $packetId): bool
    {
        foreach ($this->itemModel->findWithTemplatesByPacketId($packetId) as $item) {
            $requiredPolicy = (string) ($item->template_required_policy ?? '');
            $isCandidateSelectable = (int) ($item->template_is_candidate_selectable ?? 0) === 1;

            if ($requiredPolicy === DeclarationTemplate::REQUIRED_OPTIONAL || $isCandidateSelectable) {
                continue;
            }

            if ((string) $item->status !== DeclarationPacketItem::STATUS_ACCEPTED) {
                return false;
            }
        }

        return true;
    }
}
