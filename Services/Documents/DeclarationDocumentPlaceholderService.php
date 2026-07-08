<?php

namespace App\Modules\Declarations\Services\Documents;

use App\Modules\Declarations\Entities\DeclarationSubmission;

class DeclarationDocumentPlaceholderService
{
    /**
     * @return list<string>
     */
    public function knownPlaceholderKeys(): array
    {
        return [
            'név',
            'nev',
            'teljes_név',
            'teljes_nev',
            'vezetéknév',
            'vezeteknev',
            'keresztnév',
            'keresztnev',
            'születési_név',
            'szuletesi_nev',
            'anyja_neve',
            'születési_hely',
            'szuletesi_hely',
            'születési_dátum',
            'szuletesi_datum',
            'taj',
            'taj_szám',
            'taj_szam',
            'adóazonosító',
            'adoazonosito',
            'adószám',
            'adoszam',
            'email',
            'telefon',
            'bankszámlaszám',
            'bankszamlaszam',
            'cég',
            'ceg',
            'munkáltató',
            'munkaltato',
            'munkáltató_neve',
            'munkaltato_neve',
            'jogviszony_kezdete',
            'jogviszony_vége',
            'jogviszony_vege',
            'adóév',
            'adoev',
            'év',
            'ev',
            'dátum',
            'datum',
            'nyilatkozat_neve',
            'nyilatkozat_kód',
            'nyilatkozat_kod',
            'csomag_azonosító',
            'csomag_azonosito',
        ];
    }

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
            'munkáltató' => $company->name ?? null,
            'munkaltato' => $company->name ?? null,
            'munkáltató_neve' => $company->name ?? null,
            'munkaltato_neve' => $company->name ?? null,
            'jogviszony_kezdete' => $relation->start_date ?? null,
            'jogviszony_vége' => $relation->end_date ?? null,
            'jogviszony_vege' => $relation->end_date ?? null,
            'adóév' => $packet->tax_year ?? null,
            'adoev' => $packet->tax_year ?? null,
            'év' => $packet->tax_year ?? null,
            'ev' => $packet->tax_year ?? null,
            'dátum' => $today,
            'datum' => $today,
            'nyilatkozat_neve' => $item->template_name ?? null,
            'nyilatkozat_kód' => $item->template_code ?? null,
            'nyilatkozat_kod' => $item->template_code ?? null,
            'csomag_azonosító' => $packet->id ?? null,
            'csomag_azonosito' => $packet->id ?? null,
        ];

        foreach (($data['template_fields'] ?? []) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $placeholders[(string) $key] = $value;
            }
        }

        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $placeholders[(string) $key] = $value;
            }
        }

        return $this->withCaseVariants($placeholders);
    }

    /**
     * @return array{
     *     title:string,
     *     subtitle:string,
     *     meta:array<string, string>,
     *     rows:array<string, string>,
     *     note:string
     * }
     */
    public function documentSummary(object $packet, object $item, ?DeclarationSubmission $submission, ?object $person = null, ?object $relation = null, ?object $company = null): array
    {
        $data = $this->submissionData($submission);
        $fullName = $this->fullName($person) ?: '-';

        $meta = [
            'Kitöltő' => $fullName,
            'Adóazonosító jel' => (string) ($person->tax_number ?? ($data['tax_number'] ?? '-')),
            'TAJ szám' => (string) ($person->taj_number ?? ($data['taj_number'] ?? '-')),
            'Cég' => (string) ($company->name ?? '-'),
            'Adóév' => (string) ($packet->tax_year ?? '-'),
            'Sablonverzió' => (string) ($item->template_version ?? '-'),
            'Sablonfájl' => (string) ($item->template_file ?? '-'),
            'Beküldve' => (string) ($submission->submitted_at ?? '-'),
        ];

        if (!empty($relation->start_date)) {
            $meta['Jogviszony kezdete'] = (string) $relation->start_date;
        }

        return [
            'title' => (string) ($item->template_name ?? 'Nyilatkozat'),
            'subtitle' => 'Online kitöltési összesítő',
            'meta' => $this->cleanSummaryRows($meta),
            'rows' => $this->summaryRowsFromData($data),
            'note' => 'A forrás DOCX sablon változatlanul megmarad. Ez az összesítő a kitöltő online űrlapon megadott adataiból készül a generált dokumentumhoz és előnézethez.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function summaryRowsForSubmission(?DeclarationSubmission $submission): array
    {
        return $this->summaryRowsFromData($this->submissionData($submission));
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

        if ($data instanceof \stdClass) {
            $data = (array) $data;
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);

            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            $data = is_array($decoded) ? $decoded : [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function summaryRowsFromData(array $data): array
    {
        if (is_array($data['display_rows'] ?? null)) {
            return $this->cleanSummaryRows($data['display_rows']);
        }

        if (is_array($data['template_fields'] ?? null)) {
            return $this->cleanSummaryRows($this->humanizedRows($data['template_fields']));
        }

        $hiddenKeys = [
            'confirm_truth',
            'template_code',
            'template_name',
            'template_version',
            'template_fields',
            'tax_fields',
            'repeaters',
            'display_rows',
            'confirmed_at',
        ];

        $rows = [];

        foreach ($data as $key => $value) {
            if (in_array((string) $key, $hiddenKeys, true)) {
                continue;
            }

            $rows[$this->humanizeKey((string) $key)] = $this->stringValue($value);
        }

        if ($rows === [] && !empty($data['confirm_truth'])) {
            $rows['Nyilatkozat'] = 'Megerősítve';
        }

        return $this->cleanSummaryRows($rows);
    }

    /**
     * @param array<string, mixed> $rows
     * @return array<string, string>
     */
    private function humanizedRows(array $rows): array
    {
        $result = [];

        foreach ($rows as $key => $value) {
            $result[$this->humanizeKey((string) $key)] = $this->stringValue($value);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $rows
     * @return array<string, string>
     */
    private function cleanSummaryRows(array $rows): array
    {
        $result = [];

        foreach ($rows as $label => $value) {
            $label = trim((string) $label);

            if ($label === '') {
                continue;
            }

            $text = $this->stringValue($value);
            $result[$label] = $text !== '' ? $text : '-';
        }

        return $result;
    }

    private function stringValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'Igen' : 'Nem';
        }

        if (is_array($value)) {
            $parts = [];

            foreach ($value as $key => $item) {
                if (is_array($item)) {
                    $parts[] = $this->stringValue($item);
                    continue;
                }

                $parts[] = is_string($key)
                    ? $this->humanizeKey($key) . ': ' . $this->stringValue($item)
                    : $this->stringValue($item);
            }

            return implode(', ', array_filter($parts, static fn(string $part): bool => $part !== ''));
        }

        return trim((string) ($value ?? ''));
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

    private function humanizeKey(string $key): string
    {
        $label = str_replace(['_', '-'], ' ', $key);

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
        }

        return ucfirst($label);
    }

    /**
     * @param array<string, scalar|null> $placeholders
     * @return array<string, scalar|null>
     */
    private function withCaseVariants(array $placeholders): array
    {
        foreach ($placeholders as $key => $value) {
            $key = (string) $key;

            if ($key === '') {
                continue;
            }

            if (function_exists('mb_convert_case')) {
                $placeholders[mb_convert_case($key, MB_CASE_TITLE, 'UTF-8')] = $value;
            } else {
                $placeholders[ucfirst($key)] = $value;
            }
        }

        return $placeholders;
    }
}
