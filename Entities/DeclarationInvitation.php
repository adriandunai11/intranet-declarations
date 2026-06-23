<?php

namespace App\Modules\Declarations\Entities;

use CodeIgniter\Entity\Entity;

class DeclarationInvitation extends Entity
{
    public const STATUS_CREATED = 'created';
    public const STATUS_SENT = 'sent';
    public const STATUS_OPENED = 'opened';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVOKED = 'revoked';

    protected $attributes = [
        'status' => self::STATUS_CREATED,
    ];

    protected $casts = [
        'id' => 'integer',
        'person_id' => 'integer',
        'employment_relation_id' => '?integer',
        'packet_id' => '?integer',
    ];

    public function isExpired(): bool
    {
        $expiresAt = $this->attributes['expires_at'] ?? null;

        return !empty($expiresAt) && strtotime((string) $expiresAt) < time();
    }
}
