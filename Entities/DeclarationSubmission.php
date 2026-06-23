<?php

namespace App\Modules\Declarations\Entities;

use CodeIgniter\Entity\Entity;

class DeclarationSubmission extends Entity
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    protected $dates = [
        'submitted_at',
        'accepted_at',
        'rejected_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'packet_id' => 'integer',
        'packet_item_id' => 'integer',
        'template_id' => 'integer',
        'person_id' => 'integer',
        'employment_relation_id' => 'integer',
        'data_json' => 'json',
    ];
}