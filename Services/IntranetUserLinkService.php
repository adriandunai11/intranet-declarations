<?php

namespace App\Modules\Declarations\Services;

use App\Modules\Declarations\Entities\Person;
use App\Modules\Declarations\Models\PersonModel;
use RuntimeException;

class IntranetUserLinkService
{
    private const USERS_TABLE = 'users';

    private PersonModel $personModel;
    private DeclarationAuditService $auditService;
    private $db;

    public function __construct()
    {
        $this->personModel = new PersonModel();
        $this->auditService = new DeclarationAuditService();
        $this->db = db_connect();
    }

    public function candidatesForPerson(int $personId): array
    {
        $person = $this->findPerson($personId);
        $candidates = [];

        foreach ($this->existingUserFields(['antraid', 'antra_id']) as $field) {
            $antraId = trim((string) ($person->antra_id ?? ''));

            if ($antraId === '') {
                continue;
            }

            foreach ($this->findActiveUsersByField($field, $antraId) as $user) {
                $this->addCandidate($candidates, $user, 'Antra azonosító egyezés');
            }
        }

        foreach ($this->existingUserFields(['email', 'mail']) as $field) {
            $email = trim((string) ($person->email ?? ''));

            if ($email === '') {
                continue;
            }

            foreach ($this->findActiveUsersByField($field, $email) as $user) {
                $this->addCandidate($candidates, $user, 'E-mail cím egyezés');
            }
        }

        usort($candidates, static fn(array $a, array $b): int => strcmp($a['label'], $b['label']));

        return array_values($candidates);
    }

    public function linkedUserForPerson(int $personId): ?array
    {
        $person = $this->findPerson($personId);
        $userId = (int) ($person->intranet_user_id ?? 0);

        if ($userId <= 0) {
            return null;
        }

        $user = $this->findActiveUserById($userId, false);

        return $user ? $this->userPayload($user, 'Kapcsolt intranet felhasználó') : [
            'id' => $userId,
            'label' => 'Felhasználó #' . $userId,
            'email' => '',
            'antra_id' => '',
            'status' => null,
            'reason' => 'Kapcsolt intranet felhasználó nem található aktívként',
        ];
    }

    public function flashUserAddPrefillForPerson(int $personId): void
    {
        $prefill = $this->userAddPrefillForPerson($personId);

        session()->setFlashdata('_ci_old_input', [
            'get' => [],
            'post' => $prefill,
        ]);

        session()->setFlashdata('declarations_user_add_prefill', $prefill);
    }

    public function userAddPrefillForPerson(int $personId): array
    {
        $person = $this->findPerson($personId);
        $lastname = trim((string) ($person->lastname ?? ''));
        $firstname = trim((string) ($person->firstname ?? ''));
        $phone = trim((string) ($person->phone ?? ''));
        $antraId = trim((string) ($person->antra_id ?? ''));
        $fullName = trim($lastname . ' ' . $firstname);

        $prefill = [
            'declaration_person_id' => (int) $person->id,
            'lastname' => $lastname,
            'last_name' => $lastname,
            'firstname' => $firstname,
            'first_name' => $firstname,
            'name' => $fullName,
            'phone' => $phone,
            'mobile' => $phone,
            'antraid' => $antraId,
            'antra_id' => $antraId,
            'return_url' => url('declarations/persons/' . (int) $person->id),
        ];

        return array_filter($prefill, static fn($value): bool => $value !== null && $value !== '');
    }

    public function linkExistingUser(int $personId, int $userId): array
    {
        $person = $this->findPerson($personId);
        $user = $this->findActiveUserById($userId);

        $this->assertUserNotLinkedToOtherPerson($personId, $userId);

        $oldUserId = (int) ($person->intranet_user_id ?? 0);

        $this->db->transBegin();

        try {
            if ($oldUserId !== $userId && !$this->personModel->update($personId, ['intranet_user_id' => $userId])) {
                $this->throwModelError('A személy intranet felhasználóhoz kapcsolása sikertelen.');
            }

            $this->auditService->log('person_intranet_user_linked', 'person', $personId, [
                'person_id' => $personId,
                'old_status' => $oldUserId > 0 ? (string) $oldUserId : null,
                'new_status' => (string) $userId,
                'payload' => [
                    'intranet_user_id' => $userId,
                ],
            ]);

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Az intranet felhasználó kapcsolása sikertelen.');
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return [
            'user_id' => $userId,
            'user' => $this->userPayload($user, 'Kapcsolt intranet felhasználó'),
        ];
    }

    public function linkMatchingPersonForUser(int $userId): ?object
    {
        $user = $this->findActiveUserById($userId, false);

        if (!$user) {
            return null;
        }

        $matches = [];
        $antraId = $this->userValue($user, ['antraid', 'antra_id']);

        if ($antraId === '') {
            return null;
        }

        foreach ($this->personModel->where('antra_id', $antraId)->findAll() as $person) {
            $matches[(int) $person->id] = $person;
        }

        $matches = array_values(array_filter($matches, static function ($person) use ($userId): bool {
            $linkedUserId = (int) ($person->intranet_user_id ?? 0);

            return $linkedUserId === 0 || $linkedUserId === $userId;
        }));

        if (count($matches) !== 1) {
            return null;
        }

        $person = $matches[0];
        $this->linkExistingUser((int) $person->id, $userId);

        return $this->personModel->find((int) $person->id);
    }

    /**
     * @return array{created:int,linked:int,already_linked:int,skipped:int,errors:list<string>}
     */
    public function syncDeclarationPersonsForActiveUsers(bool $dryRun = false): array
    {
        if (!$this->db->tableExists(self::USERS_TABLE)) {
            throw new RuntimeException('A users tábla nem található.');
        }

        $result = [
            'created' => 0,
            'linked' => 0,
            'already_linked' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($this->activeUsers() as $user) {
            $userId = (int) ($user->id ?? 0);

            if ($userId <= 0) {
                $result['skipped']++;
                continue;
            }

            if ($this->personModel->where('intranet_user_id', $userId)->first()) {
                $result['already_linked']++;
                continue;
            }

            $payload = $this->personPayloadForUser($user);

            if ($payload === null) {
                $result['skipped']++;
                $result['errors'][] = 'Felhasználó #' . $userId . ': nincs elég névadat nyilatkozati személy létrehozásához.';
                continue;
            }

            $existingPerson = $this->matchingPersonForUserPayload($payload);

            if ($existingPerson) {
                $linkedUserId = (int) ($existingPerson->intranet_user_id ?? 0);

                if ($linkedUserId > 0 && $linkedUserId !== $userId) {
                    $result['skipped']++;
                    $result['errors'][] = 'Felhasználó #' . $userId . ': az egyező nyilatkozati személy már másik intranet userhez kapcsolt.';
                    continue;
                }

                if (!$dryRun && !$this->personModel->update((int) $existingPerson->id, ['intranet_user_id' => $userId])) {
                    $result['skipped']++;
                    $result['errors'][] = 'Felhasználó #' . $userId . ': ' . $this->modelError('a kapcsolás sikertelen.');
                    continue;
                }

                if (!$dryRun) {
                    $this->auditService->log('person_intranet_user_linked_by_sync', 'person', (int) $existingPerson->id, [
                        'person_id' => (int) $existingPerson->id,
                        'new_status' => (string) $userId,
                        'payload' => ['intranet_user_id' => $userId],
                    ]);
                }

                $result['linked']++;
                continue;
            }

            if ($this->wouldCreateDuplicate($payload)) {
                $result['skipped']++;
                $result['errors'][] = 'Felhasználó #' . $userId . ': az ANTRA azonosító vagy e-mail cím alapján nem egyértelmű az új rekord létrehozása.';
                continue;
            }

            if ($dryRun) {
                $result['created']++;
                continue;
            }

            $personId = $this->personModel->insert($payload, true);

            if (!$personId) {
                $result['skipped']++;
                $result['errors'][] = 'Felhasználó #' . $userId . ': ' . $this->modelError('a nyilatkozati személy létrehozása sikertelen.');
                continue;
            }

            $this->auditService->log('person_created_by_user_sync', 'person', (int) $personId, [
                'person_id' => (int) $personId,
                'payload' => [
                    'intranet_user_id' => $userId,
                    'email' => $payload['email'] ?? null,
                    'antra_id' => $payload['antra_id'] ?? null,
                ],
            ]);

            $result['created']++;
        }

        return $result;
    }

    private function findPerson(int $personId): object
    {
        $person = $this->personModel->find($personId);

        if (!$person) {
            throw new RuntimeException('A személy nem található.');
        }

        return $person;
    }

    /**
     * @return list<object>
     */
    private function activeUsers(): array
    {
        $builder = $this->db->table(self::USERS_TABLE);

        if ($this->userFieldExists('status')) {
            $builder->where('status', 1);
        }

        return $builder
            ->orderBy('id', 'ASC')
            ->get()
            ->getResult();
    }

    private function personPayloadForUser(object $user): ?array
    {
        $userId = (int) ($user->id ?? 0);
        $lastname = $this->userValue($user, ['lastname', 'last_name']);
        $firstname = $this->userValue($user, ['firstname', 'first_name']);
        $fullName = $this->userValue($user, ['name', 'full_name', 'display_name']);

        if (($lastname === '' || $firstname === '') && $fullName !== '') {
            [$nameLast, $nameFirst] = $this->splitName($fullName);
            $lastname = $lastname !== '' ? $lastname : $nameLast;
            $firstname = $firstname !== '' ? $firstname : $nameFirst;
        }

        if ($lastname === '' || $firstname === '') {
            return null;
        }

        $payload = [
            'intranet_user_id' => $userId,
            'antra_id' => $this->nullableUserValue($user, ['antraid', 'antra_id']),
            'lastname' => $lastname,
            'firstname' => $firstname,
            'email' => $this->nullableUserValue($user, ['email', 'mail']),
            'phone' => $this->nullableUserValue($user, ['phone', 'mobile', 'telephone']),
            'status' => Person::STATUS_ACTIVE,
        ];

        return array_filter($payload, static fn($value): bool => $value !== null && $value !== '');
    }

    private function matchingPersonForUserPayload(array $payload): ?object
    {
        $antraId = trim((string) ($payload['antra_id'] ?? ''));

        if ($antraId !== '') {
            $person = $this->personModel->findByAntraId($antraId);

            if ($person) {
                return $person;
            }
        }

        $email = trim((string) ($payload['email'] ?? ''));

        if ($email === '') {
            return null;
        }

        $matches = $this->personModel->where('email', $email)->findAll();

        if (count($matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    private function wouldCreateDuplicate(array $payload): bool
    {
        $antraId = trim((string) ($payload['antra_id'] ?? ''));

        if ($antraId !== '' && $this->personModel->findByAntraId($antraId)) {
            return true;
        }

        $email = trim((string) ($payload['email'] ?? ''));

        return $email !== '' && $this->personModel->where('email', $email)->countAllResults() > 0;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitName(string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName) ?? '');

        if ($fullName === '') {
            return ['', ''];
        }

        $parts = explode(' ', $fullName, 2);

        return [
            trim((string) ($parts[0] ?? '')),
            trim((string) ($parts[1] ?? '')),
        ];
    }

    private function nullableUserValue(object $user, array $fields): ?string
    {
        $value = $this->userValue($user, $fields);

        return $value !== '' ? $value : null;
    }

    private function findActiveUserById(int $userId, bool $throw = true)
    {
        if ($userId <= 0 || !$this->db->tableExists(self::USERS_TABLE)) {
            if ($throw) {
                throw new RuntimeException('A kiválasztott intranet felhasználó nem található.');
            }

            return null;
        }

        $builder = $this->db->table(self::USERS_TABLE)
            ->where('id', $userId);

        if ($this->userFieldExists('status')) {
            $builder->where('status', 1);
        }

        $user = $builder->get()->getRow();

        if (!$user && $throw) {
            throw new RuntimeException('A kiválasztott intranet felhasználó nem található vagy nem aktív.');
        }

        return $user;
    }

    private function findActiveUsersByField(string $field, string $value): array
    {
        if (!$this->db->tableExists(self::USERS_TABLE) || !$this->userFieldExists($field)) {
            return [];
        }

        $builder = $this->db->table(self::USERS_TABLE)
            ->where($field, $value);

        if ($this->userFieldExists('status')) {
            $builder->where('status', 1);
        }

        return $builder->get()->getResult();
    }

    private function addCandidate(array &$candidates, object $user, string $reason): void
    {
        $id = (int) ($user->id ?? 0);

        if ($id <= 0) {
            return;
        }

        if (isset($candidates[$id])) {
            $candidates[$id]['reason'] .= ', ' . $reason;
            return;
        }

        $candidates[$id] = $this->userPayload($user, $reason);
    }

    private function userPayload(object $user, string $reason): array
    {
        $id = (int) ($user->id ?? 0);
        $label = $this->userValue($user, ['name', 'full_name', 'display_name']);
        $email = $this->userValue($user, ['email', 'mail']);
        $antraId = $this->userValue($user, ['antraid', 'antra_id']);

        if ($label === '') {
            $label = trim($this->userValue($user, ['lastname', 'last_name']) . ' ' . $this->userValue($user, ['firstname', 'first_name']));
        }

        if ($label === '') {
            $label = $this->userValue($user, ['username', 'user_name', 'login']);
        }

        return [
            'id' => $id,
            'label' => $label !== '' ? $label : ('Felhasználó #' . $id),
            'email' => $email,
            'antra_id' => $antraId,
            'status' => $user->status ?? null,
            'reason' => $reason,
        ];
    }

    private function userValue(object $user, array $fields): string
    {
        foreach ($fields as $field) {
            if (!property_exists($user, $field)) {
                continue;
            }

            $value = trim((string) ($user->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function existingUserFields(array $fields): array
    {
        return array_values(array_filter($fields, fn(string $field): bool => $this->userFieldExists($field)));
    }

    private function userFieldExists(string $field): bool
    {
        return in_array($field, $this->userFieldNames(), true);
    }

    private function userFieldNames(): array
    {
        static $fields = null;

        if ($fields !== null) {
            return $fields;
        }

        if (!$this->db->tableExists(self::USERS_TABLE)) {
            return $fields = [];
        }

        $fields = [];

        foreach ($this->db->getFieldData(self::USERS_TABLE) as $field) {
            $fields[] = (string) $field->name;
        }

        return $fields;
    }

    private function assertUserNotLinkedToOtherPerson(int $personId, int $userId): void
    {
        $existing = $this->personModel
            ->where('intranet_user_id', $userId)
            ->where('id !=', $personId)
            ->first();

        if ($existing) {
            throw new RuntimeException('Ez az intranet felhasználó már másik nyilatkozati személyhez van kapcsolva: ' . $existing->fullName() . '.');
        }
    }

    private function throwModelError(string $fallbackMessage): void
    {
        throw new RuntimeException($this->modelError($fallbackMessage));
    }

    private function modelError(string $fallbackMessage): string
    {
        $errors = $this->personModel->errors();

        if (!empty($errors)) {
            return implode(' ', $errors);
        }

        return $fallbackMessage;
    }
}
