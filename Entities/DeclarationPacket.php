<?php

namespace App\Modules\Declarations\Entities;

use CodeIgniter\Entity\Entity;

class DeclarationPacket extends Entity
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_IN_PROGRESS = 'in_progress';
    /** Minden csomagban lévő nyilatkozat mentve, a csomag ellenőrzésre vár. */
    public const STATUS_SUBMITTED = 'submitted';
    /** Minden ellenőrzendő nyilatkozat elfogadva, admin zárásra / következő HR lépésre vár. */
    public const STATUS_APPROVED = 'approved';
    /** A nyilatkozatcsomag adminisztratívan lezárva. */
    public const STATUS_CLOSED = 'closed';
    /** Régi kompatibilitási státusz; új kódban a submitted / approved / closed használandó. */
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const FLOW_ONBOARDING = 'onboarding';
    public const FLOW_SELF_SERVICE = 'self_service';
    public const FLOW_SELF_SERVICE_TAX = 'self_service_tax';
    public const FLOW_SELF_SERVICE_CHANGE = 'self_service_change';
    public const FLOW_ADMIN_MANUAL = 'admin_manual';

    protected $dates = [
        'sent_at',
        'completed_at',
        'cancelled_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'person_id' => 'integer',
        'employment_relation_id' => 'integer',
        'company_id' => 'integer',
        'tax_year' => '?integer',
        'created_by_user_id' => '?integer',
    ];
}
