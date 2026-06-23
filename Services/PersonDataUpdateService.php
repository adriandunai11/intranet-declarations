<?php

namespace App\Modules\Declarations\Services;

use App\Modules\Declarations\Models\PersonModel;
use App\Modules\Declarations\Services\DeclarationAuditService;
use RuntimeException;

class PersonDataUpdateService
{
    protected PersonModel $personModel;
    protected DeclarationAuditService $auditService;
    public function __construct()
    {
        $this->personModel = new PersonModel();
        $this->auditService = new DeclarationAuditService();
    }

    public function updateFromPersonalDataDeclaration(int $personId, array $data): void
    {
        $person = $this->personModel->find($personId);

        if (!$person) {
            throw new RuntimeException('A személy nem található a személyes adatok frissítéséhez.');
        }

        $taxNumber = $this->digits($data['tax_number'] ?? '');
        $tajNumber = $this->digits($data['taj_number'] ?? '');

        $this->assertUniqueValue('tax_number', $taxNumber, $personId, 'Ez az adóazonosító jel már másik személyhez tartozik.');
        $this->assertUniqueValue('taj_number', $tajNumber, $personId, 'Ez a TAJ szám már másik személyhez tartozik.');

        $payload = [
            'birth_name' => $this->nullableString($data['birth_name'] ?? null),
            'mother_name' => $this->nullableString($data['mother_name'] ?? null),
            'birth_place' => $this->nullableString($data['birth_place'] ?? null),
            'birth_date' => $this->nullableString($data['birth_date'] ?? null),
            'tax_number' => $taxNumber !== '' ? $taxNumber : null,
            'taj_number' => $tajNumber !== '' ? $tajNumber : null,
            'phone' => $this->nullableString($data['phone'] ?? null),
        ];

        if (!$this->personModel->update($personId, $payload)) {
            $errors = $this->personModel->errors();

            throw new RuntimeException(!empty($errors) ? implode(' ', $errors) : 'A személyes adatok frissítése sikertelen.');
        }

        $this->auditService->log('person_sensitive_data_updated_from_candidate', 'person', $personId, [
            'actor_type' => 'candidate',
            'person_id' => $personId,
            'payload' => [
                'updated_fields' => array_keys(array_filter($payload, static fn($value) => $value !== null)),
                'tax_number_changed' => ($person->tax_number ?? null) !== ($payload['tax_number'] ?? null),
                'taj_number_changed' => ($person->taj_number ?? null) !== ($payload['taj_number'] ?? null),
                'phone_changed' => ($person->phone ?? null) !== ($payload['phone'] ?? null),
            ],
        ]);
    }

    private function assertUniqueValue(string $field, string $value, int $personId, string $message): void
    {
        if ($value === '') {
            return;
        }

        $existing = $this->personModel->where($field, $value)->first();

        if ($existing && (int) $existing->id !== $personId) {
            throw new RuntimeException($message);
        }
    }

    private function digits($value): string
    {
        return preg_replace('/\D+/', '', (string) $value);
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
