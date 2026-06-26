<?php

namespace App\Modules\Declarations\Models;

use CodeIgniter\Model;

class DeclarationAuditLogModel extends Model
{
    public const ACTION_PACKET_CREATED = 'packet_created';
    public const ACTION_PACKET_STATUS_CHANGED = 'packet_status_changed';
    public const ACTION_PACKET_SUBMITTED = 'packet_submitted';
    public const ACTION_PACKET_APPROVED = 'packet_approved';
    public const ACTION_PACKET_CLOSED = 'packet_closed';
    public const ACTION_PACKET_COMPLETED = 'packet_completed';
    public const ACTION_INVITATION_CREATED = 'invitation_created';
    public const ACTION_INVITATION_REGENERATED = 'invitation_regenerated';
    public const ACTION_INVITATION_REVOKED = 'invitation_revoked';
    public const ACTION_INVITATION_OPENED = 'invitation_opened';
    public const ACTION_INVITATION_COMPLETED = 'invitation_completed';
    public const ACTION_ANTRA_VERIFICATION_SUCCEEDED = 'antra_verification_succeeded';
    public const ACTION_ANTRA_VERIFICATION_FAILED = 'antra_verification_failed';
    public const ACTION_ITEM_SUBMITTED = 'item_submitted';
    public const ACTION_ITEM_RESUBMITTED = 'item_resubmitted';
    public const ACTION_ITEM_ACCEPTED = 'item_accepted';
    public const ACTION_ITEM_REJECTED = 'item_rejected';
    public const ACTION_ITEM_REOPENED = 'item_reopened_for_correction';
    public const ACTION_OPTIONAL_TEMPLATE_ADDED = 'optional_template_added';
    public const ACTION_PERSON_DATA_UPDATED = 'person_data_updated_from_submission';
    public const ACTION_RELATION_STATUS_CHANGED = 'relation_status_changed';
    public const ACTION_INVITATION_EMAIL_SENT = 'invitation_email_sent';
    public const ACTION_REJECTION_EMAIL_SENT = 'rejection_email_sent';
    public const ACTION_PACKET_REVIEW_EMAIL_SENT = 'packet_review_email_sent';
    public const ACTION_DOCUMENT_GENERATED = 'document_generated';

    protected $table = 'declaration_audit_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'actor_user_id',
        'actor_type',
        'actor_label',
        'action',
        'entity_type',
        'entity_id',
        'person_id',
        'employment_relation_id',
        'packet_id',
        'packet_item_id',
        'submission_id',
        'invitation_id',
        'old_status',
        'new_status',
        'note',
        'payload_json',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    public function log(array $data): int
    {
        $request = service('request');

        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $data['actor_user_id'] = $data['actor_user_id'] ?? (function_exists('logged') ? logged('id') : null);
        $data['ip_address'] = $data['ip_address'] ?? $request->getIPAddress();
        $data['user_agent'] = $data['user_agent'] ?? substr((string) $request->getUserAgent(), 0, 255);

        if (isset($data['payload']) && !isset($data['payload_json'])) {
            $data['payload_json'] = json_encode($data['payload'], JSON_UNESCAPED_UNICODE);
            unset($data['payload']);
        }

        return (int) $this->insert($data, true);
    }

    public function logAction(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?int $packetId = null,
        ?int $packetItemId = null,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?string $note = null,
        array $payload = []
    ): int {
        $data = [
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'packet_id' => $packetId,
            'packet_item_id' => $packetItemId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
            'payload_json' => $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        ];

        foreach ([
            'actor_type',
            'actor_label',
            'person_id',
            'employment_relation_id',
            'submission_id',
            'invitation_id',
        ] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        return $this->log($data);
    }

    public function findByPacketId(int $packetId, int $limit = 50): array
    {
        return $this->where('packet_id', $packetId)
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function findByPersonId(int $personId, int $limit = 50): array
    {
        return $this->groupStart()
                ->where('person_id', $personId)
                ->orGroupStart()
                    ->whereIn('entity_type', ['person', 'declaration_person'])
                    ->where('entity_id', $personId)
                ->groupEnd()
            ->groupEnd()
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->findAll();
    }
}
