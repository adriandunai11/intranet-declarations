<?php

namespace App\Modules\Declarations\Services;

use App\Modules\Declarations\Entities\Person;
use App\Modules\Declarations\Models\PersonModel;
use App\Modules\Declarations\Services\DeclarationAuditService;
use RuntimeException;

class PersonService
{
    protected PersonModel $personModel;
    protected DeclarationAuditService $auditService;

    public function __construct()
    {
        $this->personModel = new PersonModel();
        $this->auditService = new DeclarationAuditService();
    }

    public function create(array $data): int
    {
        $payload = $this->payloadFromInput($data);
        $payload['intranet_user_id'] = $data['intranet_user_id'] ?? null;
        $payload['status'] = Person::STATUS_ACTIVE;

        $personId = $this->personModel->insert($payload, true);

        if (!$personId) {
            $this->throwModelError('A személy létrehozása sikertelen.');
        }

        $this->auditService->log('person_created', 'person', (int) $personId, [
            'person_id' => (int) $personId,
            'payload' => [
                'email' => $payload['email'] ?? null,
                'lastname' => $payload['lastname'] ?? null,
                'firstname' => $payload['firstname'] ?? null,
            ],
        ]);

        return (int) $personId;
    }

    public function update(int $personId, array $data): void
    {
        $person = $this->find($personId);
        $oldPerson = $this->personModel->find($personId);
        if (!$person) {
            throw new RuntimeException('A személy nem található.');
        }

        $payload = $this->payloadFromInput($data);

        // TAJ számot és adóazonosító jelet a beálló adja meg a public kitöltőfelületen.
        // Ha az admin űrlap nem küldi ezeket a mezőket, nem nullázzuk ki a már meglévő adatot.
        if (!array_key_exists('tax_number', $data)) {
            $payload['tax_number'] = $person->tax_number ?? null;
        }

        if (!array_key_exists('taj_number', $data)) {
            $payload['taj_number'] = $person->taj_number ?? null;
        }

        $payload['status'] = $this->normalizeStatus($data['status'] ?? $person->status ?? Person::STATUS_ACTIVE);

        if (!$this->personModel->update($personId, $payload)) {
            $this->throwModelError('A személy módosítása sikertelen.');
        }

        $this->auditService->log('person_updated', 'person', $personId, [
            'person_id' => $personId,
            'payload' => [
                'old' => $oldPerson ? [
                    'email' => $oldPerson->email ?? null,
                    'lastname' => $oldPerson->lastname ?? null,
                    'firstname' => $oldPerson->firstname ?? null,
                    'phone' => $oldPerson->phone ?? null,
                ] : null,
                'new' => [
                    'email' => $payload['email'] ?? null,
                    'lastname' => $payload['lastname'] ?? null,
                    'firstname' => $payload['firstname'] ?? null,
                    'phone' => $payload['phone'] ?? null,
                ],
            ],
        ]);
    }

    /**
     * @return Person[]
     */
    public function listForTable(): array
    {
        return $this->personModel
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function find(int $id): ?Person
    {
        $person = $this->personModel->find($id);

        return $person ?: null;
    }

    private function payloadFromInput(array $data): array
    {
        return [
            'antra_id' => $this->nullableString($data['antra_id'] ?? null),
            'lastname' => trim((string) ($data['lastname'] ?? '')),
            'firstname' => trim((string) ($data['firstname'] ?? '')),
            'birth_name' => $this->nullableString($data['birth_name'] ?? null),
            'mother_name' => $this->nullableString($data['mother_name'] ?? null),
            'birth_place' => $this->nullableString($data['birth_place'] ?? null),
            'birth_date' => $this->nullableString($data['birth_date'] ?? null),
            'tax_number' => $this->nullableString($data['tax_number'] ?? null),
            'taj_number' => $this->nullableString($data['taj_number'] ?? null),
            'email' => $this->nullableString($data['email'] ?? null),
            'phone' => $this->nullableString($data['phone'] ?? null),
        ];
    }

    private function normalizeStatus($status): string
    {
        $status = trim((string) $status);

        if (!in_array($status, Person::STATUSES, true)) {
            return Person::STATUS_ACTIVE;
        }

        return $status;
    }

    protected function nullableString($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function throwModelError(string $fallbackMessage): void
    {
        $errors = $this->personModel->errors();

        if (!empty($errors)) {
            throw new RuntimeException(implode(' ', $errors));
        }

        throw new RuntimeException($fallbackMessage);
    }
}
