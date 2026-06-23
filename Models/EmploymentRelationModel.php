<?php

namespace App\Modules\Declarations\Models;

use App\Modules\Declarations\Entities\EmploymentRelation;
use CodeIgniter\Model;

class EmploymentRelationModel extends Model
{
    protected $table = 'declaration_employment_relations';
    protected $primaryKey = 'id';
    protected $returnType = EmploymentRelation::class;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'person_id',
        'company_id',
        'intranet_user_id',
        'primary_recruiter_user_id',
        'onboarding_type',
        'status',
        'location',
        'location_id',
        'start_date',
        'previous_relation_id',
        'created_by_user_id',
    ];

    protected $validationRules = [
        'person_id' => 'required|is_natural_no_zero',
        'company_id' => 'required|is_natural_no_zero',
        'location_id' => 'permit_empty|is_natural_no_zero',
        'primary_recruiter_user_id' => 'required|is_natural_no_zero',
        'onboarding_type' => 'required|max_length[50]',

        'status' => 'required|max_length[30]',
        'location' => 'permit_empty|max_length[190]',
        'start_date' => 'required|valid_date[Y-m-d]',
    ];

    protected $validationMessages = [
        'company_id' => [
            'required' => 'A cég megadása kötelező.',
            'is_natural_no_zero' => 'Érvényes céget adj meg.',
        ],
        'start_date' => [
            'required' => 'A kezdődátum megadása kötelező.',
            'valid_date' => 'A kezdődátum formátuma hibás.',
        ],
        'primary_recruiter_user_id' => [
            'required' => 'Az elsődleges toborzó megadása kötelező.',
            'is_natural_no_zero' => 'Érvényes elsődleges toborzót válassz.',
        ],
    ];

    public function findOpenByPersonAndCompany(int $personId, int $companyId): array
    {
        return $this->where('person_id', $personId)
            ->where('company_id', $companyId)
            ->whereNotIn('status', [
                EmploymentRelation::STATUS_CLOSED,
                EmploymentRelation::STATUS_CANCELLED,
            ])
            ->findAll();
    }

    public function findByPersonId(int $personId): array
    {
        return $this->where('person_id', $personId)
            ->orderBy('start_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function updateStatus(int $relationId, string $status): bool
    {
        return $this->update($relationId, [
            'status' => $status,
        ]);
    }
}
