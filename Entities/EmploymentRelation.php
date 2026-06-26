<?php

namespace App\Modules\Declarations\Entities;

use CodeIgniter\Entity\Entity;

class EmploymentRelation extends Entity
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_INVITED = 'invited';
    public const STATUS_ONBOARDING = 'onboarding';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_TRANSFERRED = 'transferred';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DECLARATIONS_SUBMITTED = 'declarations_submitted';
    public const STATUS_COMPLETED = 'completed';


    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $casts = [
        'id' => 'integer',
        'person_id' => 'integer',
        'company_id' => 'integer',
        'intranet_user_id' => '?integer',
        'primary_recruiter_user_id' => '?integer',
        'previous_relation_id' => '?integer',
        'created_by_user_id' => '?integer',
        'location_id' => 'integer',
    ];

    public function isOpen(): bool
    {
        return !in_array($this->attributes['status'] ?? '', [
            self::STATUS_CLOSED,
            self::STATUS_CANCELLED,
        ], true);
    }
}
