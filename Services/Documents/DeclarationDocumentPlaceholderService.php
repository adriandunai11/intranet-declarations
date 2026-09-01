<?php

namespace App\Modules\Declarations\Services\Documents;

use App\Modules\Declarations\Entities\DeclarationSubmission;
use App\Modules\Declarations\Presenters\Submissions\SubmissionPresenterRegistry;
use App\Modules\Declarations\Services\DeclarationSubmissionDataNormalizer;
use App\Modules\Declarations\Services\DeclarationForms\StructuredStatementSchemaService;
use App\Modules\Declarations\Services\DeclarationForms\TaxDeclarationSchemaService;

class DeclarationDocumentPlaceholderService
{
    private DeclarationSubmissionDataNormalizer $dataNormalizer;
    private SubmissionPresenterRegistry $submissionPresenterRegistry;
    private StructuredStatementSchemaService $structuredSchemaService;
    private TaxDeclarationSchemaService $taxSchemaService;

    public function __construct(
        ?DeclarationSubmissionDataNormalizer $dataNormalizer = null,
        ?SubmissionPresenterRegistry $submissionPresenterRegistry = null,
        ?StructuredStatementSchemaService $structuredSchemaService = null,
        ?TaxDeclarationSchemaService $taxSchemaService = null
    ) {
        $this->dataNormalizer = $dataNormalizer ?? new DeclarationSubmissionDataNormalizer();
        $this->submissionPresenterRegistry = $submissionPresenterRegistry ?? new SubmissionPresenterRegistry();
        $this->structuredSchemaService = $structuredSchemaService ?? new StructuredStatementSchemaService();
        $this->taxSchemaService = $taxSchemaService ?? new TaxDeclarationSchemaService();
    }

    /**
     * @return array{
     *     title:string,
     *     subtitle:string,
     *     meta:array<string, string>,
     *     rows:array<string, string>,
     *     tables:list<array{title:string, columns:list<string>, rows:list<list<string>>}>,
     *     note:string
     * }
     */
    public function documentSummary(object $packet, object $item, ?DeclarationSubmission $submission, ?object $person = null, ?object $company = null): array
    {
        $data = $this->submissionData($submission);
        $fullName = $this->fullName($person) ?: '-';
        $templateCode = (string) ($item->template_code ?? ($data['template_code'] ?? ''));
        $presenterRows = $submission ? $this->submissionPresenterRegistry->rowsFor($templateCode, $submission) : [];
        $presenterTables = $submission ? $this->submissionPresenterRegistry->tablesFor($templateCode, $submission) : [];
        $tables = $presenterTables !== [] ? $presenterTables : $this->summaryTablesFromData($data, $templateCode);
        $rows = $presenterRows !== [] ? $presenterRows : $this->summaryRowsFromData($data, $tables);

        $meta = [
            'Kitöltő' => $fullName,
            'Adóazonosító jel' => (string) ($person->tax_number ?? ($data['tax_number'] ?? '-')),
            'TAJ szám' => (string) ($person->taj_number ?? ($data['taj_number'] ?? '-')),
            'Cég' => (string) ($company->name ?? '-'),
            'Nyilatkozati év' => (string) ($packet->tax_year ?? '-'),
            'Nyilatkozatverzió' => (string) ($item->template_version ?? '-'),
            'Beküldve' => (string) ($submission->submitted_at ?? '-'),
        ];

        if (!empty($packet->process_date)) {
            $meta['Folyamat dátuma'] = (string) $packet->process_date;
        }

        return [
            'title' => $this->templateTitle($templateCode, $item),
            'subtitle' => 'Online kitöltési összesítő',
            'meta' => $this->cleanSummaryRows($meta),
            'rows' => $this->cleanSummaryRows($rows),
            'tables' => $tables,
            'note' => 'Ez az összesítő a kitöltő online űrlapon megadott adataiból készül.',
        ];
    }

    private function templateTitle(string $templateCode, object $item): string
    {
        if ($templateCode !== '' && $this->taxSchemaService->supports($templateCode)) {
            $schema = $this->taxSchemaService->schemaFor($templateCode);

            return (string) ($schema['title'] ?? ($item->template_name ?? 'Nyilatkozat'));
        }

        if ($templateCode !== '' && $this->structuredSchemaService->supports($templateCode)) {
            $schema = $this->structuredSchemaService->schemaFor($templateCode);

            return (string) ($schema['title'] ?? ($item->template_name ?? 'Nyilatkozat'));
        }

        return (string) ($item->template_name ?? 'Nyilatkozat');
    }

    /**
     * @return array<string, mixed>
     */
    private function submissionData(?DeclarationSubmission $submission): array
    {
        if (!$submission) {
            return [];
        }

        return $this->dataNormalizer->normalize($submission->data_json ?? []);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array{title:string, columns:list<string>, rows:list<list<string>>}> $tables
     * @return array<string, string>
     */
    private function summaryRowsFromData(array $data, array $tables = []): array
    {
        if (is_array($data['display_rows'] ?? null)) {
            return $this->withoutTableRows($this->cleanSummaryRows($data['display_rows']), $tables);
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
            'statement_fields',
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

            $rows[$this->humanizeKey((string) $key)] = $this->stringValueForKey((string) $key, $value);
        }

        if ($rows === [] && !empty($data['confirm_truth'])) {
            $rows['Nyilatkozat'] = 'Megerősítve';
        }

        return $this->cleanSummaryRows($rows);
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{title:string, columns:list<string>, rows:list<list<string>>}>
     */
    private function summaryTablesFromData(array $data, string $templateCode = ''): array
    {
        $repeaters = is_array($data['repeaters'] ?? null) ? $data['repeaters'] : [];

        if ($repeaters === []) {
            return [];
        }

        $schema = $this->schemaForData($data, $templateCode);
        $repeaterDefinitions = [];

        foreach (($schema['repeaters'] ?? []) as $repeaterDefinition) {
            if (!is_array($repeaterDefinition)) {
                continue;
            }

            $key = (string) ($repeaterDefinition['key'] ?? '');

            if ($key !== '') {
                $repeaterDefinitions[$key] = $repeaterDefinition;
            }
        }

        $tables = [];

        foreach ($repeaters as $repeaterKey => $rows) {
            if (!is_array($rows) || $rows === []) {
                continue;
            }

            $definition = $repeaterDefinitions[(string) $repeaterKey] ?? null;
            $columns = is_array($definition) && is_array($definition['columns'] ?? null)
                ? array_values(array_filter($definition['columns'], 'is_array'))
                : $this->genericColumns($rows);

            if ($columns === []) {
                continue;
            }

            $tableRows = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $tableRow = [];

                foreach ($columns as $column) {
                    $columnKey = (string) ($column['key'] ?? '');
                    $tableRow[] = $this->displayValue($column, $row[$columnKey] ?? null);
                }

                if (array_filter($tableRow, static fn(string $value): bool => trim($value) !== '') !== []) {
                    $tableRows[] = array_map(static fn(string $value): string => $value !== '' ? $value : '-', $tableRow);
                }
            }

            if ($tableRows === []) {
                continue;
            }

            $usedColumnIndexes = [];

            foreach (array_keys($columns) as $columnIndex) {
                foreach ($tableRows as $tableRow) {
                    if (($tableRow[$columnIndex] ?? '-') !== '-') {
                        $usedColumnIndexes[] = $columnIndex;
                        break;
                    }
                }
            }

            $visibleColumns = array_values(array_intersect_key($columns, array_flip($usedColumnIndexes)));
            $visibleRows = array_map(
                static fn(array $tableRow): array => array_values(array_intersect_key($tableRow, array_flip($usedColumnIndexes))),
                $tableRows
            );

            $tables[] = [
                'title' => (string) ($definition['title'] ?? $this->humanizeKey((string) $repeaterKey)),
                'columns' => array_map(
                    fn(array $column): string => (string) ($column['label'] ?? $this->humanizeKey((string) ($column['key'] ?? ''))),
                    $visibleColumns
                ),
                'rows' => $visibleRows,
            ];
        }

        return $tables;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function schemaForData(array $data, string $fallbackTemplateCode = ''): array
    {
        $dataTemplateCode = (string) ($data['template_code'] ?? '');
        $templateCode = $fallbackTemplateCode !== '' ? $fallbackTemplateCode : $dataTemplateCode;

        if ($templateCode !== '' && $this->taxSchemaService->supports($templateCode)) {
            return $this->taxSchemaService->schemaFor($templateCode);
        }

        if ($templateCode !== '' && $this->structuredSchemaService->supports($templateCode)) {
            return $this->structuredSchemaService->schemaFor($templateCode);
        }

        return [];
    }

    /**
     * @param array<int, mixed> $rows
     * @return list<array<string, string>>
     */
    private function genericColumns(array $rows): array
    {
        $keys = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($row as $key => $value) {
                if (trim((string) $value) !== '') {
                    $keys[(string) $key] = true;
                }
            }
        }

        return array_map(
            fn(string $key): array => ['key' => $key, 'label' => $this->humanizeKey($key), 'type' => 'text'],
            array_keys($keys)
        );
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

                if ($title !== '' && preg_match('/^' . $title . '\s+-\s+\d+\./u', (string) $label)) {
                    unset($rows[$label]);
                    break;
                }
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $rows
     * @return array<string, string>
     */
    private function humanizedRows(array $rows): array
    {
        $result = [];

        foreach ($rows as $key => $value) {
            $result[$this->humanizeKey((string) $key)] = $this->stringValueForKey((string) $key, $value);
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
                    ? $this->humanizeKey($key) . ': ' . $this->stringValueForKey($key, $item)
                    : $this->stringValue($item);
            }

            return implode(', ', array_filter($parts, static fn(string $part): bool => $part !== ''));
        }

        return trim((string) ($value ?? ''));
    }

    private function stringValueForKey(string $key, $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Igen' : 'Nem';
        }

        if (is_array($value) || $value instanceof \stdClass) {
            return $this->stringValue($value);
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

    /**
     * @param array<string, mixed> $field
     */
    private function displayValue(array $field, $value): string
    {
        $type = (string) ($field['type'] ?? 'text');

        if ($type === 'checkbox') {
            return (int) $value === 1 ? 'Igen' : '';
        }

        if ($type === 'select') {
            $options = is_array($field['options'] ?? null) ? $field['options'] : [];

            return trim((string) ($options[(string) $value] ?? $value ?? ''));
        }

        return $this->stringValueForKey((string) ($field['key'] ?? ''), $value);
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
        $labels = [
            'account_holder' => 'Számlatulajdonos',
            'bank_name' => 'Bank neve',
            'bank_account_number' => 'Bankszámlaszám',
            'birth_name' => 'Születési név',
            'mother_name' => 'Anyja neve',
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
}
