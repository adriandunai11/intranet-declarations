<?php

namespace App\Modules\Declarations\Services;

use App\Modules\Declarations\Models\DeclarationAuditLogModel;

class DeclarationAuditService
{
    protected DeclarationAuditLogModel $auditLogModel;

    public function __construct()
    {
        $this->auditLogModel = new DeclarationAuditLogModel();
    }

    public function log(string $action, string $entityType, ?int $entityId = null, array $context = []): void
    {
        $payload = $context['payload'] ?? null;

        unset($context['payload']);

        $this->auditLogModel->log([
            'actor_user_id' => $context['actor_user_id'] ?? $this->currentUserId(),
            'actor_type' => $context['actor_type'] ?? $this->defaultActorType(),
            'actor_label' => $context['actor_label'] ?? null,

            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,

            'person_id' => $context['person_id'] ?? null,
            'employment_relation_id' => $context['employment_relation_id'] ?? null,
            'packet_id' => $context['packet_id'] ?? null,
            'packet_item_id' => $context['packet_item_id'] ?? null,
            'submission_id' => $context['submission_id'] ?? null,
            'invitation_id' => $context['invitation_id'] ?? null,

            'old_status' => $context['old_status'] ?? null,
            'new_status' => $context['new_status'] ?? null,
            'note' => $context['note'] ?? null,
            'payload_json' => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    public function statusChanged(
        string $entityType,
        int $entityId,
        ?string $oldStatus,
        ?string $newStatus,
        array $context = []
    ): void {
        $context['old_status'] = $oldStatus;
        $context['new_status'] = $newStatus;

        $this->log($entityType . '_status_changed', $entityType, $entityId, $context);
    }

    private function currentUserId(): ?int
    {
        if (!function_exists('logged')) {
            return null;
        }

        $id = logged('id');

        return $id ? (int) $id : null;
    }

    private function defaultActorType(): string
    {
        return $this->currentUserId() ? 'admin_user' : 'system';
    }
}