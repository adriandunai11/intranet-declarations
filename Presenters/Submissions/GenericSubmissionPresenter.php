<?php

namespace App\Modules\Declarations\Presenters\Submissions;

use App\Modules\Declarations\Entities\DeclarationSubmission;
use App\Modules\Declarations\Services\DeclarationSubmissionDataNormalizer;

class GenericSubmissionPresenter implements SubmissionPresenterInterface
{
    private DeclarationSubmissionDataNormalizer $dataNormalizer;

    public function __construct(?DeclarationSubmissionDataNormalizer $dataNormalizer = null)
    {
        $this->dataNormalizer = $dataNormalizer ?? new DeclarationSubmissionDataNormalizer();
    }

    public function supports(string $templateCode): bool
    {
        return true;
    }

    public function rows(DeclarationSubmission $submission): array
    {
        $data = $this->dataNormalizer->normalize($submission->data_json ?? []);
        $tables = $this->tables($submission);

        if (!is_array($data) || empty($data)) {
            return [
                'Beküldött adat' => '-',
            ];
        }

        $rows = [];
        $displayRows = is_array($data['display_rows'] ?? null) ? $data['display_rows'] : [];

        foreach ($displayRows as $key => $value) {
            $rows[(string) $key] = (string) $value;
        }

        if ($rows !== []) {
            return $this->withoutTableRows($rows, $tables);
        }

        $templateFields = is_array($data['template_fields'] ?? null) ? $data['template_fields'] : [];

        foreach ($templateFields as $key => $value) {
            $rows[$this->humanizeKey((string) $key)] = $this->stringValueForKey((string) $key, $value);
        }

        if ($rows === [] && !empty($data['confirm_truth'])) {
            $rows['Nyilatkozat'] = 'Megerősítve';
        }

        $hiddenKeys = [
            'confirm_truth',
            'template_code',
            'template_name',
            'template_version',
            'template_fields',
            'statement_fields',
            'tax_fields',
            'repeaters',
            'display_rows',
            'confirmed_at',
        ];

        foreach ($data as $key => $value) {
            if (in_array((string) $key, $hiddenKeys, true)) {
                continue;
            }

            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $rows[$this->humanizeKey((string) $key)] = $this->stringValueForKey((string) $key, $value);
        }

        $rows = $this->withoutTableRows($rows, $tables);

        return $rows !== [] ? $rows : [
            'Beküldött adat' => '-',
        ];
    }

    /**
     * @return list<array{title:string, columns:list<string>, rows:list<list<string>>}>
     */
    public function tables(DeclarationSubmission $submission): array
    {
        $data = $this->dataNormalizer->normalize($submission->data_json ?? []);
        $repeaters = is_array($data['repeaters'] ?? null) ? $data['repeaters'] : [];
        $tables = [];

        foreach ($repeaters as $repeaterKey => $rows) {
            if (!is_array($rows) || $rows === []) {
                continue;
            }

            $columns = $this->columnsForRepeaterRows($rows);

            if ($columns === []) {
                continue;
            }

            $tableRows = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $tableRow = [];

                foreach ($columns as $key => $label) {
                    $value = $row[$key] ?? '';
                    $value = $this->stringValueForKey((string) $key, $value);
                    $tableRow[] = $value !== '' ? $value : '-';
                }

                if (array_filter($tableRow, static fn(string $value): bool => $value !== '-') !== []) {
                    $tableRows[] = $tableRow;
                }
            }

            if ($tableRows !== []) {
                $tables[] = [
                    'title' => $this->humanizeKey((string) $repeaterKey),
                    'columns' => array_values($columns),
                    'rows' => $tableRows,
                ];
            }
        }

        return $tables;
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<string, string>
     */
    private function columnsForRepeaterRows(array $rows): array
    {
        $columns = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($row as $key => $value) {
                if (trim((string) $value) !== '') {
                    $columns[(string) $key] = $this->humanizeKey((string) $key);
                }
            }
        }

        return $columns;
    }

    /**
     * @param array<string, string> $rows
     * @param list<array{title:string, columns:list<string>, rows:list<list<string>>}> $tables
     * @return array<string, string>
     */
    private function withoutTableRows(array $rows, array $tables): array
    {
        if ($tables === []) {
            return $rows;
        }

        foreach (array_keys($rows) as $label) {
            foreach ($tables as $table) {
                $title = preg_quote((string) ($table['title'] ?? ''), '/');

                if ($title !== '' && preg_match('/^' . $title . '\s+-\s+\d+\.\s+[^,]+$/u', (string) $label)) {
                    unset($rows[$label]);
                    break;
                }
            }
        }

        return $rows;
    }

    private function humanizeKey(string $key): string
    {
        $labels = [
            'account_holder' => 'Számlatulajdonos',
            'bank_name' => 'Bank neve',
            'bank_account_number' => 'Bankszámlaszám',
            'birth_name' => 'Születési név',
            'mother_name' => 'Anyja születési neve',
            'birth_place' => 'Születési hely',
            'birth_date' => 'Születési dátum',
            'tax_number' => 'Adóazonosító jel',
            'taj_number' => 'TAJ szám',
            'phone' => 'Telefonszám',
            'spouse_name' => 'Házastárs vagy élettárs neve',
            'spouse_tax_number' => 'Házastárs vagy élettárs adóazonosító jele',
            'spouse_employer_name' => 'Másik jogosult munkáltatója',
            'spouse_employer_tax_number' => 'Másik jogosult munkáltatójának adószáma',
            'marriage_date' => 'Házasságkötés dátuma',
            'eligibility_start' => 'Jogosultság kezdete',
            'eligibility_end' => 'Jogosultság vége',
            'eligibility_note' => 'Megjegyzés vagy igazolás adatai',
            'waiver_scope' => 'Mellőzés módja',
            'monthly_limit' => 'Havi összeghatár forintban',
            'modified_statement' => 'Módosító nyilatkozat',
            'mother_discount_start' => 'Anyák kedvezményének kezdete',
            'foreign_discount_taken' => 'Külföldi kedvezmény igénybevétele',
            'skip_contribution_discount' => 'Családi járulékkedvezmény mellőzése',
            'claim_extra_leave' => 'Gyermek után járó pótszabadság igénylése',
            'extra_leave_note' => 'Megjegyzés',
            'children' => 'Gyermekek adatai',
            'child_name' => 'Gyermek neve',
            'custody_type' => 'Jogosultság alapja',
            'claim_scope' => 'Családi kedvezmény érvényesítése',
            'calculation_mode' => 'Családi kedvezmény kitöltése',
            'monthly_amount' => 'Havi családi kedvezmény forintban',
            'beneficiary_dependents_count' => 'Kedvezményezett eltartottak száma',
            'dependents' => 'Gyermekek és eltartottak adatai',
            'em_code' => 'EM* kód',
            'jj_code' => 'JJ** jogcím',
            'change_date' => 'Változás időpontja',
            'name' => 'Név',
            'nav_code' => 'NAV nyomtatványkód',
            'tax_group' => 'Adóügyi csoport',
            'taxpayer_scope' => 'Kinek szól',
            'short_description' => 'Rövid leírás',
            'long_description' => 'Részletes leírás',
            'confirm_truth' => 'Valóságtartalom megerősítése',
            'confirmed_at' => 'Megerősítés ideje',
        ];

        if (isset($labels[$key])) {
            return $labels[$key];
        }

        $label = str_replace(['_', '-'], ' ', $key);

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
        }

        return ucfirst($label);
    }

    private function stringValueForKey(string $key, $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Igen' : 'Nem';
        }

        if (is_array($value) || $value instanceof \stdClass) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return '';
        }

        $checkboxKeys = [
            'confirm_truth',
            'foreign_discount_taken',
            'skip_contribution_discount',
            'modified_statement',
        ];

        if (in_array($key, $checkboxKeys, true)) {
            return in_array(strtolower($text), ['1', 'true', 'yes', 'on', 'igen'], true) ? 'Igen' : 'Nem';
        }

        $options = [
            'claim_extra_leave' => [
                'yes' => 'Igen, kérem',
                'no' => 'Nem kérem',
            ],
            'claim_scope' => [
                'alone' => 'Egyedül érvényesítem',
                'shared' => 'Jogosult házastárssal vagy élettárssal közösen',
            ],
            'calculation_mode' => [
                'amount' => 'Havi forintösszeget adok meg',
                'dependents' => 'Kedvezményezett eltartottak száma alapján kérem',
            ],
            'waiver_scope' => [
                'full' => 'A kedvezmény teljes mellőzését kérem',
                'partial' => 'Csak egy megadott összeg felett kérem a mellőzést',
            ],
            'custody_type' => [
                'own_household' => 'Saját háztartásban nevelt gyermek',
                'shared_custody' => 'Felváltva gondozott gyermek',
                'disabled' => 'Fogyatékossággal élő gyermek',
                'other' => 'Egyéb jogosultsági ok',
            ],
            'em_code' => [
                '1' => '1 - Kedvezményezett eltartott',
                '2' => '2 - Eltartott',
                '3' => '3 - Felváltva gondozott gyermek',
                '4' => '4 - Tartósan beteg vagy súlyosan fogyatékos személy',
                '5' => '5 - Felváltva gondozott tartósan beteg vagy súlyosan fogyatékos személy',
                '0' => '0 - Kedvezménybe nem számító',
            ],
            'jj_code' => [
                'a' => 'a - Családi pótlékra jogosult vagy vele közös háztartásban élő házastárs',
                'b' => 'b - Várandós nő vagy vele közös háztartásban élő házastárs',
                'c' => 'c - Saját jogon családi pótlékra jogosult vagy vele közös háztartásban élő hozzátartozó',
                'd' => 'd - Rokkantsági járadékban részesülő vagy vele közös háztartásban élő hozzátartozó',
            ],
        ];

        return $options[$key][$text] ?? $text;
    }
}
