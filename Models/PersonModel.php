<?php

namespace App\Modules\Declarations\Models;

use App\Modules\Declarations\Entities\Person;
use CodeIgniter\Model;

class PersonModel extends Model
{
    protected $table = 'declaration_persons';
    protected $primaryKey = 'id';
    protected $returnType = Person::class;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'intranet_user_id',
        'antra_id',
        'lastname',
        'firstname',
        'birth_name',
        'mother_name',
        'birth_place',
        'birth_date',
        'tax_number',
        'taj_number',
        'email',
        'phone',
        'status',
    ];


    protected $validationRules = [
        'lastname' => 'required|max_length[100]',
        'firstname' => 'required|max_length[100]',
        'email' => 'permit_empty|valid_email|max_length[190]',
        'birth_date' => 'permit_empty|valid_date[Y-m-d]',
        'tax_number' => 'permit_empty|max_length[20]',
        'taj_number' => 'permit_empty|max_length[20]',
        'antra_id' => 'permit_empty|max_length[50]',
    ];

    protected $validationMessages = [
        'lastname' => [
            'required' => 'A vezetéknév megadása kötelező.',
        ],
        'firstname' => [
            'required' => 'A keresztnév megadása kötelező.',
        ],
        'email' => [
            'valid_email' => 'Érvényes e-mail címet adj meg.',
        ],
    ];
    public function findByTaxNumber(string $taxNumber): ?Person
    {
        $person = $this->where('tax_number', $taxNumber)->first();

        return $person ?: null;
    }

    public function findByAntraId(string $antraId): ?Person
    {
        $person = $this->where('antra_id', $antraId)->first();

        return $person ?: null;
    }

    public function findByTajNumber(string $tajNumber): ?Person
    {
        $person = $this->where('taj_number', $tajNumber)->first();

        return $person ?: null;
    }

    public function findByEmail(string $email): ?Person
    {
        $person = $this->where('email', $email)->first();

        return $person ?: null;
    }

}
