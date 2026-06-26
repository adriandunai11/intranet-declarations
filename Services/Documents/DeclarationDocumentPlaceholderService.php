<?php

namespace App\Modules\Declarations\Services\Documents;

use App\Modules\Declarations\Entities\DeclarationSubmission;

class DeclarationDocumentPlaceholderService
{
    /**
     * @return array<string, scalar|null>
     */
    public function build(object $packet, object $item, ?DeclarationSubmission $submission, ?object $person = null, ?object $relation = null, ?object $company = null): array
    {
        $data = $this->submissionData($submission);
        $fullName = $this->fullName($person);
        $today = date('Y-m-d');

        $placeholders = [
            'név' => $fullName,
            'nev' => $fullName,
            'teljes_név' => $fullName,
            'teljes_nev' => $fullName,
            'vezetéknév' => $person->lastname ?? ($data['lastname'] ?? null),
            'vezeteknev' => $person->lastname ?? ($data['lastname'] ?? null),
            'keresztnév' => $person->firstname ?? ($data['firstname'] ?? null),
            'keresztnev' => $person->firstname ?? ($data['firstname'] ?? null),
            'születési_név' => $person->birth_name ?? ($data['birth_name'] ?? null),
            'szuletesi_nev' => $person->birth_name ?? ($data['birth_name'] ?? null),
            'anyja_neve' => $person->mother_name ?? ($data['mother_name'] ?? null),
            'születési_hely' => $person->birth_place ?? ($data['birth_place'] ?? null),
            'szuletesi_hely' => $person->birth_place ?? ($data['birth_place'] ?? null),
            'születési_dátum' => $person->birth_date ?? ($data['birth_date'] ?? null),
            'szuletesi_datum' => $person->birth_date ?? ($data['birth_date'] ?? null),
            'taj' => $person->taj_number ?? ($data['taj_number'] ?? null),
            'taj_szám' => $person->taj_number ?? ($data['taj_number'] ?? null),
            'taj_szam' => $person->taj_number ?? ($data['taj_number'] ?? null),
            'adóazonosító' => $person->tax_number ?? ($data['tax_number'] ?? null),
            'adoazonosito' => $person->tax_number ?? ($data['tax_number'] ?? null),
            'adószám' => $person->tax_number ?? ($data['tax_number'] ?? null),
            'adoszam' => $person->tax_number ?? ($data['tax_number'] ?? null),
            'email' => $person->email ?? ($data['email'] ?? null),
            'telefon' => $person->phone ?? ($data['phone'] ?? null),
            'bankszámlaszám' => $data['bank_account_number'] ?? $data['bank_account'] ?? null,
            'bankszamlaszam' => $data['bank_account_number'] ?? $data['bank_account'] ?? null,
            'cég' => $company->name ?? null,
            'ceg' => $company->name ?? null,
            'jogviszony_kezdete' => $relation->start_date ?? null,
            'jogviszony_vége' => $relation->end_date ?? null,
            'jogviszony_vege' => $relation->end_date ?? null,
            'adóév' => $packet->tax_year ?? null,
            'adoev' => $packet->tax_year ?? null,
            'dátum' => $today,
            'datum' => $today,
            'nyilatkozat_neve' => $item->template_name ?? null,
            'nyilatkozat_kód' => $item->template_code ?? null,
            'nyilatkozat_kod' => $item->template_code ?? null,
            'csomag_azonosító' => $packet->id ?? null,
            'csomag_azonosito' => $packet->id ?? null,
        ];

        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $placeholders[(string) $key] = $value;
            }
        }

        return $placeholders;
    }

    /**
     * @return array<string, mixed>
     */
    private function submissionData(?DeclarationSubmission $submission): array
    {
        if (!$submission) {
            return [];
        }

        $data = $submission->data_json ?? [];

        return is_array($data) ? $data : [];
    }

    private function fullName(?object $person): ?string
    {
        if (!$person) {
            return null;
        }

        if (method_exists($person, 'fullName')) {
            return $person->fullName();
        }

        return trim((string) ($person->lastname ?? '') . ' ' . (string) ($person->firstname ?? '')) ?: null;
    }
}
