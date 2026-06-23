<?php

namespace App\Modules\Declarations\Models;

use App\Modules\Declarations\Entities\PersonChild;
use CodeIgniter\Model;

class PersonChildModel extends Model
{
    protected $table = 'declaration_person_children';
    protected $primaryKey = 'id';
    protected $returnType = PersonChild::class;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'person_id',
        'name',
        'tax_number',
        'birth_date',
        'relationship_type',
        'is_dependent',
        'is_disabled',
        'valid_from',
        'valid_to',
    ];
}
