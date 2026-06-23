<?php

namespace App\Modules\Declarations\Entities;

use CodeIgniter\Entity\Entity;

class PersonChild extends Entity
{
    protected $casts = [
        'id' => 'integer',
        'person_id' => 'integer',
        'is_dependent' => 'boolean',
        'is_disabled' => 'boolean',
    ];
}
