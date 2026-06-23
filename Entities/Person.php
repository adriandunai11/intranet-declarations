<?php

namespace App\Modules\Declarations\Entities;

use CodeIgniter\Entity\Entity;

class Person extends Entity
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_MERGED = 'merged';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_BLOCKED,
        self::STATUS_MERGED,
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    protected $casts = [
        'id' => 'integer',
        'intranet_user_id' => '?integer',
    ];

    public function fullName(): string
    {
        return trim(($this->attributes['lastname'] ?? '') . ' ' . ($this->attributes['firstname'] ?? ''));
    }

    public function isLinkedToIntranet(): bool
    {
        return !empty($this->attributes['intranet_user_id']);
    }
}
