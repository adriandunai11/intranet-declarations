<?php

namespace App\Modules\Declarations\Entities;

use CodeIgniter\Entity\Entity;

class DeclarationPacket extends Entity
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_IN_PROGRESS = 'in_progress';
    /** Minden kötelező, beálló által kitöltendő dokumentum beküldve, ellenőrzésre vár. */
    public const STATUS_SUBMITTED = 'submitted';
    /** Minden beküldött kötelező dokumentum elfogadva, admin zárásra / következő HR lépésre vár. */
    public const STATUS_APPROVED = 'approved';
    /** A nyilatkozatcsomag adminisztratívan lezárva. */
    public const STATUS_CLOSED = 'closed';
    /** Régi kompatibilitási státusz; új kódban a submitted / approved / closed használandó. */
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

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