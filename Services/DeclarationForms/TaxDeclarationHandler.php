<?php

namespace App\Modules\Declarations\Services\DeclarationForms;

use App\Modules\Declarations\Services\Validation\HungarianIdentifierValidator;
use RuntimeException;

class TaxDeclarationHandler implements DeclarationFormHandlerInterface
{
    protected ?object $item;
    protected TaxDeclarationSchemaService $schemaService;
    protected HungarianIdentifierValidator $identifierValidator;

    public function __construct(
        ?object $item = null,
        ?TaxDeclarationSchemaService $schemaService = null,
        ?HungarianIdentifierValidator $identifierValidator = null
    ) {
        $this->item = $item;
        $this->schemaService = $schemaService ?? new TaxDeclarationSchemaService();
        $this->identifierValidator = $identifierValidator ?? new HungarianIdentifierValidator();
    }

    public function supports(string $templateCode): bool
    {
        return $this->schemaService->supports($templateCode);
    }

    public function title(object $item): string
    {
        $schema = $this->schemaService->schemaFor((string) ($item->template_code ?? ''));

        return (string) ($item->template_name ?: ($schema['title'] ?? 'Adóügyi nyilatkozat'));
    }

    public function view(): string
    {
        return 'App\Modules\Declarations\Views\public\forms\tax_declaration';
    }

    public function rules(): array
    {
        return [
            'confirm_truth' => 'required',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        return [
            'taxFormSchema' => $this->schema(),
        ];
    }

    public function normalize(array $input): array
    {
        $schema = $this->schema();
        $rawFields = $input['tax_fields'] ?? [];
        $rawFields = is_array($rawFields) ? $rawFields : [];
        $rawRepeaters = $input['repeaters'] ?? [];
        $rawRepeaters = is_array($rawRepeaters) ? $rawRepeaters : [];
        $fields = [];

        foreach ($this->allFields($schema) as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $fields[$key] = $this->normalizeValue($field, $rawFields[$key] ?? null);
        }

        $repeaters = [];

        foreach (($schema['repeaters'] ?? []) as $repeater) {
            if (!is_array($repeater)) {
                continue;
            }

            $repeaterKey = (string) ($repeater['key'] ?? '');
            $rawRows = $rawRepeaters[$repeaterKey] ?? [];
            $rawRows = is_array($rawRows) ? array_values($rawRows) : [];
            $rows = [];

            foreach ($rawRows as $rawRow) {
                if (!is_array($rawRow)) {
                    continue;
                }

                $row = [];

                foreach (($repeater['columns'] ?? []) as $column) {
                    if (!is_array($column)) {
                        continue;
                    }

                    $columnKey = (string) ($column['key'] ?? '');

                    if ($columnKey === '') {
                        continue;
                    }

                    $row[$columnKey] = $this->normalizeValue($column, $rawRow[$columnKey] ?? null);
                }

                if (!$this->isEmptyRow($row)) {
                    $rows[] = $row;
                }
            }

            $repeaters[$repeaterKey] = $rows;
        }

        $displayRows = $this->displayRows($schema, $fields, $repeaters);

        return [
            'confirm_truth' => !empty($input['confirm_truth']) ? 1 : 0,
            'template_code' => $this->templateCode(),
            'template_name' => (string) ($this->item->template_name ?? ($schema['title'] ?? 'Adóügyi nyilatkozat')),
            'template_version' => (string) ($this->item->template_version ?? ''),
            'tax_fields' => $fields,
            'repeaters' => $repeaters,
            'template_fields' => $this->flattenForPlaceholders($schema, $fields, $repeaters),
            'display_rows' => $displayRows,
            'confirmed_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function validateNormalized(array $data): void
    {
        $schema = $this->schema();
        $fields = is_array($data['tax_fields'] ?? null) ? $data['tax_fields'] : [];
        $repeaters = is_array($data['repeaters'] ?? null) ? $data['repeaters'] : [];
        $errors = [];

        if ((int) ($data['confirm_truth'] ?? 0) !== 1) {
            $errors[] = 'A beküldéshez el kell fogadni a valóságtartalomról szóló nyilatkozatot.';
        }

        foreach ($this->allFields($schema) as $field) {
            $key = (string) ($field['key'] ?? '');
            $value = $fields[$key] ?? null;

            if (!empty($field['required']) && $this->isEmptyValue($value, (string) ($field['type'] ?? 'text'))) {
                $errors[] = 'A(z) "' . (string) ($field['label'] ?? $key) . '" mező kitöltése kötelező.';
            }

            if (($field['validation'] ?? '') === 'tax_number' && !$this->isEmptyValue($value) && !$this->identifierValidator->isValidTaxNumber((string) $value)) {
                $errors[] = 'A(z) "' . (string) ($field['label'] ?? $key) . '" mezőben hibás az adóazonosító jel.';
            }

            if (($field['type'] ?? '') === 'select' && !$this->isEmptyValue($value) && !$this->isAllowedOption($field, (string) $value)) {
                $errors[] = 'A(z) "' . (string) ($field['label'] ?? $key) . '" mezőben érvénytelen érték szerepel.';
            }
        }

        foreach (($schema['repeaters'] ?? []) as $repeater) {
            if (!is_array($repeater)) {
                continue;
            }

            $repeaterKey = (string) ($repeater['key'] ?? '');
            $rows = is_array($repeaters[$repeaterKey] ?? null) ? $repeaters[$repeaterKey] : [];
            $min = (int) ($repeater['min'] ?? 0);

            if (count($rows) < $min) {
                $errors[] = 'A(z) "' . (string) ($repeater['title'] ?? $repeaterKey) . '" részben legalább ' . $min . ' sort meg kell adni.';
            }

            foreach ($rows as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }

                foreach (($repeater['columns'] ?? []) as $column) {
                    if (!is_array($column)) {
                        continue;
                    }

                    $columnKey = (string) ($column['key'] ?? '');
                    $value = $row[$columnKey] ?? null;
                    $label = (string) ($column['label'] ?? $columnKey);
                    $rowLabel = ((int) $index + 1) . '. sor';

                    if (!empty($column['required']) && $this->isEmptyValue($value, (string) ($column['type'] ?? 'text'))) {
                        $errors[] = $rowLabel . ': a(z) "' . $label . '" mező kitöltése kötelező.';
                    }

                    if (($column['validation'] ?? '') === 'tax_number' && !$this->isEmptyValue($value) && !$this->identifierValidator->isValidTaxNumber((string) $value)) {
                        $errors[] = $rowLabel . ': a(z) "' . $label . '" mezőben hibás az adóazonosító jel.';
                    }

                    if (($column['type'] ?? '') === 'select' && !$this->isEmptyValue($value) && !$this->isAllowedOption($column, (string) $value)) {
                        $errors[] = $rowLabel . ': a(z) "' . $label . '" mezőben érvénytelen érték szerepel.';
                    }
                }
            }
        }

        $this->validateConditionalRules($fields, $errors);

        if ($errors !== []) {
            throw new RuntimeException(implode(' ', array_unique($errors)));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return $this->schemaService->schemaFor($this->templateCode());
    }

    private function templateCode(): string
    {
        return (string) ($this->item->template_code ?? '');
    }

    /**
     * @param array<string, mixed> $schema
     * @return list<array<string, mixed>>
     */
    private function allFields(array $schema): array
    {
        $fields = [];

        foreach (($schema['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }

            foreach (($section['fields'] ?? []) as $field) {
                if (is_array($field)) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function normalizeValue(array $field, $value)
    {
        $type = (string) ($field['type'] ?? 'text');

        if ($type === 'checkbox') {
            return !empty($value) ? 1 : 0;
        }

        if ($type === 'number') {
            $value = str_replace(',', '.', (string) ($value ?? ''));
            $value = preg_replace('/[^0-9.\-]+/', '', $value) ?? '';

            return trim($value);
        }

        if (($field['validation'] ?? '') === 'tax_number') {
            return preg_replace('/\D+/', '', (string) ($value ?? '')) ?? '';
        }

        return $this->cleanText($value);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (!$this->isEmptyValue($value)) {
                return false;
            }
        }

        return true;
    }

    private function isEmptyValue($value, string $type = 'text'): bool
    {
        if ($type === 'checkbox') {
            return (int) $value !== 1;
        }

        return trim((string) ($value ?? '')) === '';
    }

    /**
     * @param array<string, mixed> $field
     */
    private function isAllowedOption(array $field, string $value): bool
    {
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];

        return array_key_exists($value, $options);
    }

    /**
     * @param array<string, mixed> $fields
     * @param list<string> $errors
     */
    private function validateConditionalRules(array $fields, array &$errors): void
    {
        $code = $this->templateCode();

        if (in_array($code, ['family_tax_discount', 'combined_family_mothers_discount'], true)) {
            if (($fields['claim_scope'] ?? '') === 'shared') {
                if ($this->isEmptyValue($fields['spouse_name'] ?? '')) {
                    $errors[] = 'Közös érvényesítésnél a másik jogosult nevét meg kell adni.';
                }

                if ($this->isEmptyValue($fields['spouse_tax_number'] ?? '')) {
                    $errors[] = 'Közös érvényesítésnél a másik jogosult adóazonosító jelét meg kell adni.';
                }
            }

            if (($fields['calculation_mode'] ?? '') === 'amount' && $this->isEmptyValue($fields['monthly_amount'] ?? '')) {
                $errors[] = 'Havi forintösszeg választása esetén a havi összeget meg kell adni.';
            }

            if (($fields['calculation_mode'] ?? '') === 'dependents' && $this->isEmptyValue($fields['beneficiary_dependents_count'] ?? '')) {
                $errors[] = 'Eltartottak száma alapján történő igénylésnél a kedvezményezett eltartottak számát meg kell adni.';
            }
        }

        if ($code === 'under_25_tax_discount_waiver' && ($fields['waiver_scope'] ?? '') === 'partial' && $this->isEmptyValue($fields['monthly_limit'] ?? '')) {
            $errors[] = 'Részleges mellőzésnél a havi összeghatárt meg kell adni.';
        }
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $fields
     * @param array<string, list<array<string, mixed>>> $repeaters
     * @return array<string, string>
     */
    private function displayRows(array $schema, array $fields, array $repeaters): array
    {
        $rows = [];

        foreach ($this->allFields($schema) as $field) {
            $key = (string) ($field['key'] ?? '');
            $value = $this->displayValue($field, $fields[$key] ?? null);

            if ($value !== '') {
                $rows[(string) ($field['label'] ?? $key)] = $value;
            }
        }

        foreach (($schema['repeaters'] ?? []) as $repeater) {
            if (!is_array($repeater)) {
                continue;
            }

            $repeaterKey = (string) ($repeater['key'] ?? '');
            $rowSummaries = [];

            foreach (($repeaters[$repeaterKey] ?? []) as $index => $row) {
                $parts = [];

                foreach (($repeater['columns'] ?? []) as $column) {
                    if (!is_array($column)) {
                        continue;
                    }

                    $columnKey = (string) ($column['key'] ?? '');
                    $value = $this->displayValue($column, $row[$columnKey] ?? null);

                    if ($value !== '') {
                        $parts[] = (string) ($column['label'] ?? $columnKey) . ': ' . $value;
                    }
                }

                if ($parts !== []) {
                    $rowSummaries[] = ((int) $index + 1) . '. sor - ' . implode(', ', $parts);
                }
            }

            if ($rowSummaries !== []) {
                $rows[(string) ($repeater['title'] ?? $repeaterKey)] = implode(' | ', $rowSummaries);
            }
        }

        return $rows;
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

            return (string) ($options[(string) $value] ?? $value ?? '');
        }

        return trim((string) ($value ?? ''));
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $fields
     * @param array<string, list<array<string, mixed>>> $repeaters
     * @return array<string, scalar|null>
     */
    private function flattenForPlaceholders(array $schema, array $fields, array $repeaters): array
    {
        $placeholders = [];
        $fieldDefinitions = [];

        foreach ($this->allFields($schema) as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key !== '') {
                $fieldDefinitions[$key] = $field;
            }
        }

        foreach ($fields as $key => $value) {
            $field = $fieldDefinitions[$key] ?? ['type' => 'text'];
            $this->addPlaceholder($placeholders, $key, $this->placeholderValue($field, $value));

            foreach ($this->aliasesForField($key) as $alias) {
                $this->addPlaceholder($placeholders, $alias, $this->placeholderValue($field, $value));
            }
        }

        $this->addChoicePlaceholders($placeholders, $fields);

        foreach ($repeaters as $repeaterKey => $rows) {
            $this->addPlaceholder($placeholders, $repeaterKey . '_count', count($rows));
            $this->addPlaceholder($placeholders, $repeaterKey . '_szama', count($rows));
            $this->addPlaceholder($placeholders, $repeaterKey . '_száma', count($rows));

            foreach ($rows as $index => $row) {
                $number = (int) $index + 1;

                foreach ($row as $key => $value) {
                    $normalizedValue = (string) $value;

                    foreach ($this->repeaterPrefixes((string) $repeaterKey) as $prefix) {
                        $this->addPlaceholder($placeholders, $prefix . '_' . $number . '_' . $key, $normalizedValue);

                        foreach ($this->aliasesForField((string) $key) as $alias) {
                            $this->addPlaceholder($placeholders, $prefix . '_' . $number . '_' . $alias, $normalizedValue);
                        }
                    }
                }
            }
        }

        return $placeholders;
    }

    /**
     * @param array<string, scalar|null> $placeholders
     * @param array<string, mixed> $field
     */
    private function placeholderValue(array $field, $value)
    {
        if (($field['type'] ?? '') === 'checkbox') {
            return (int) $value === 1 ? 'X' : '';
        }

        if (($field['type'] ?? '') === 'select') {
            return $this->displayValue($field, $value);
        }

        return is_scalar($value) || $value === null ? $value : '';
    }

    /**
     * @param array<string, scalar|null> $placeholders
     */
    private function addPlaceholder(array &$placeholders, string $key, $value): void
    {
        $key = trim($key);

        if ($key === '') {
            return;
        }

        $placeholders[$key] = is_scalar($value) || $value === null ? $value : '';

        if (function_exists('mb_convert_case')) {
            $placeholders[mb_convert_case($key, MB_CASE_TITLE, 'UTF-8')] = $placeholders[$key];
        } else {
            $placeholders[ucfirst($key)] = $placeholders[$key];
        }
    }

    /**
     * @return list<string>
     */
    private function aliasesForField(string $key): array
    {
        $aliases = [
            'claim_scope' => ['ervenyesites_modja', 'érvényesítés_módja', 'igenyles_modja', 'igénylés_módja'],
            'calculation_mode' => ['kedvezmeny_kitoltese', 'kedvezmény_kitöltése'],
            'monthly_amount' => ['havi_osszeg', 'havi_összeg', 'forint_osszeg', 'forint_összeg'],
            'beneficiary_dependents_count' => ['kedvezmenyezett_eltartottak_szama', 'kedvezményezett_eltartottak_száma'],
            'spouse_name' => ['hazastars_neve', 'házastárs_neve', 'elettars_neve', 'élettárs_neve'],
            'spouse_tax_number' => ['hazastars_adoazonosito', 'házastárs_adóazonosító', 'hazastars_adoazonosito_jele'],
            'spouse_employer_name' => ['hazastars_munkaltatoja', 'házastárs_munkáltatója'],
            'spouse_employer_tax_number' => ['hazastars_munkaltato_adoszama', 'házastárs_munkáltató_adószáma'],
            'marriage_date' => ['hazassagkotes_datuma', 'házasságkötés_dátuma'],
            'eligibility_start' => ['jogosultsag_kezdete', 'jogosultság_kezdete'],
            'eligibility_end' => ['jogosultsag_vege', 'jogosultság_vége'],
            'waiver_scope' => ['mellozes_modja', 'mellőzés_módja'],
            'monthly_limit' => ['osszeghatar', 'összeghatár', 'havi_osszeghatar', 'havi_összeghatár'],
            'modified_statement' => ['modosito_nyilatkozat', 'módosító_nyilatkozat'],
            'mother_discount_start' => ['anyak_kedvezmenye_kezdete', 'anyák_kedvezménye_kezdete'],
            'tax_number' => ['adoazonosito', 'adóazonosító', 'adoazonosito_jele', 'adóazonosító_jele'],
            'name' => ['nev', 'név'],
            'birth_date' => ['szuletesi_datum', 'születési_dátum'],
            'birth_place' => ['szuletesi_hely', 'születési_hely'],
            'change_date' => ['valtozas_idopontja', 'változás_időpontja'],
            'em_code' => ['em', 'em_kod', 'em_kód', 'eltartotti_minoseg', 'eltartotti_minőség'],
            'jj_code' => ['jj', 'jj_jogcim', 'jj_jogcím', 'jogosultsag_jogcime', 'jogosultság_jogcíme'],
            'entitled_em' => ['em'],
            'entitled_jj' => ['jj'],
        ];

        return $aliases[$key] ?? [];
    }

    /**
     * @param array<string, scalar|null> $placeholders
     * @param array<string, mixed> $fields
     */
    private function addChoicePlaceholders(array &$placeholders, array $fields): void
    {
        $this->addPlaceholder($placeholders, 'csaladi_kedvezmeny_egyedul', ($fields['claim_scope'] ?? '') === 'alone' ? 'X' : '');
        $this->addPlaceholder($placeholders, 'csaladi_kedvezmeny_kozosen', ($fields['claim_scope'] ?? '') === 'shared' ? 'X' : '');
        $this->addPlaceholder($placeholders, 'kedvezmeny_forint_osszegben', ($fields['calculation_mode'] ?? '') === 'amount' ? 'X' : '');
        $this->addPlaceholder($placeholders, 'kedvezmeny_eltartottak_szama_alapjan', ($fields['calculation_mode'] ?? '') === 'dependents' ? 'X' : '');
        $this->addPlaceholder($placeholders, 'mellozes_teljes', ($fields['waiver_scope'] ?? '') === 'full' ? 'X' : '');
        $this->addPlaceholder($placeholders, 'mellozes_reszleges', ($fields['waiver_scope'] ?? '') === 'partial' ? 'X' : '');
        $this->addPlaceholder($placeholders, 'jarulekkedvezmeny_mellozese', !empty($fields['skip_contribution_discount']) ? 'X' : '');
        $this->addPlaceholder($placeholders, 'kulfoldi_kedvezmeny', !empty($fields['foreign_discount_taken']) ? 'X' : '');
    }

    /**
     * @return list<string>
     */
    private function repeaterPrefixes(string $repeaterKey): array
    {
        if ($repeaterKey === 'children') {
            return ['child', 'children', 'gyermek'];
        }

        return ['dependent', 'dependents', 'eltartott', 'gyermek'];
    }

    private function cleanText($value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) ($value ?? '')) ?? '');
    }
}
