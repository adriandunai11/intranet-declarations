<?php

namespace App\Modules\Declarations\Entities;

use CodeIgniter\Entity\Entity;

class DeclarationPacketItem extends Entity
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    public const SOURCE_REQUIRED_ONBOARDING = 'required_onboarding';
    public const SOURCE_ADMIN_SELECTED = 'admin_selected';
    public const SOURCE_CANDIDATE_SELECTED = 'candidate_selected';
    public const SOURCE_SELF_SERVICE_PRIMARY = 'self_service_primary';

    protected $dates = [
        'completed_at',
        'accepted_at',
        'rejected_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'packet_id' => 'integer',
        'template_id' => 'integer',
        'sort_order' => 'integer',
    ];
}
