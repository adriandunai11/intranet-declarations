<?php

namespace App\Modules\Declarations\Services;

use App\Modules\Declarations\Models\PersonModel;

class PersonMatchingService
{
    public function __construct(private ?PersonModel $persons = null)
    {
        $this->persons ??= new PersonModel();
    }

    /**
     * @return array<int, array{score:int, reason:string, person:object}>
     */
    public function findPossibleMatches(array $input): array
    {
        $matches = [];

        $taxNumber = trim((string) ($input['tax_number'] ?? ''));
        if ($taxNumber !== '') {
            $person = $this->persons->findByTaxNumber($taxNumber);
            if ($person) {
                $matches[$person->id] = [
                    'score' => 100,
                    'reason' => 'Adóazonosító egyezés',
                    'person' => $person,
                ];
            }
        }

        $antraId = trim((string) ($input['antra_id'] ?? ''));
        if ($antraId !== '') {
            $person = $this->persons->findByAntraId($antraId);
            if ($person && !isset($matches[$person->id])) {
                $matches[$person->id] = [
                    'score' => 90,
                    'reason' => 'Antra azonosító egyezés',
                    'person' => $person,
                ];
            }
        }

        $birthDate = trim((string) ($input['birth_date'] ?? ''));
        $motherName = trim((string) ($input['mother_name'] ?? ''));
        $birthName = trim((string) ($input['birth_name'] ?? ''));

        if ($birthDate !== '' && $motherName !== '' && $birthName !== '') {
            $candidates = $this->persons
                ->where('birth_date', $birthDate)
                ->like('mother_name', $motherName)
                ->like('birth_name', $birthName)
                ->findAll(10);

            foreach ($candidates as $person) {
                if (!isset($matches[$person->id])) {
                    $matches[$person->id] = [
                        'score' => 75,
                        'reason' => 'Születési adatok és anyja neve alapján lehetséges egyezés',
                        'person' => $person,
                    ];
                }
            }
        }

        $tajNumber = trim((string) ($input['taj_number'] ?? ''));
        if ($tajNumber !== '') {
            $person = $this->persons->findByTajNumber($tajNumber);
            if ($person && !isset($matches[$person->id])) {
                $matches[$person->id] = [
                    'score' => 100,
                    'reason' => 'TAJ szám egyezés',
                    'person' => $person,
                ];
            }
        }

        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '') {
            $person = $this->persons->findByEmail($email);
            if ($person && !isset($matches[$person->id])) {
                $matches[$person->id] = [
                    'score' => 80,
                    'reason' => 'E-mail cím egyezés',
                    'person' => $person,
                ];
            }
        }

        usort($matches, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_values($matches);
    }
}
