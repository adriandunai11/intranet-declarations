<?php

namespace App\Modules\Declarations\Services\DeclarationForms;

use App\Modules\Declarations\Services\Validation\HungarianIdentifierValidator;
use RuntimeException;

class StructuredStatementDeclarationHandler implements DeclarationFormHandlerInterface
{
    protected ?object $item;
    protected StructuredStatementSchemaService $schemaService;
    protected HungarianIdentifierValidator $identifierValidator;

    public function __construct(
        ?object $item = null,
        ?StructuredStatementSchemaService $schemaService = null,
        ?HungarianIdentifierValidator $identifierValidator = null
    ) {
        $this->item = $item;
        $this->schemaService = $schemaService ?? new StructuredStatementSchemaService();
        $this->identifierValidator = $identifierValidator ?? new HungarianIdentifierValidator();
    }

    public function supports(string $templateCode): bool
    {
        return $this->schemaService->supports($templateCode);
    }

    public function title(object $item): string
    {
        $schema = $this->schemaService->schemaFor((string) ($item->template_code ?? ''));

        return (string) ($schema['title'] ?? ($item->template_name ?: 'Nyilatkozat'));
    }

    public function view(): string
    {
        return 'App\Modules\Declarations\Views\public\forms\structured_statement';
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
            'statementFormSchema' => $this->schema(),
        ];
    }

    public function normalize(array $input): array
    {
        $schema = $this->schema();
        $rawFields = $input['statement_fields'] ?? [];
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

        foreach ($this->allFields($schema) as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key === '' || $this->isFieldVisible($field, $fields)) {
                continue;
            }

            $fields[$key] = $this->emptyValueForField($field);
        }

        $repeaters = [];

        foreach (($schema['repeaters'] ?? []) as $repeater) {
            if (!is_array($repeater)) {
                continue;
            }

            $repeaterKey = (string) ($repeater['key'] ?? '');

            if ($repeaterKey === '') {
                continue;
            }

            if (!$this->isRepeaterVisible($repeater, $fields)) {
                $repeaters[$repeaterKey] = [];
                continue;
            }

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

        return [
            'confirm_truth' => !empty($input['confirm_truth']) ? 1 : 0,
            'template_code' => $this->templateCode(),
            'template_name' => (string) ($schema['title'] ?? ($this->item->template_name ?? 'Nyilatkozat')),
            'template_version' => (string) ($this->item->template_version ?? ''),
            'statement_fields' => $fields,
            'repeaters' => $repeaters,
            'template_fields' => $this->flattenForPlaceholders($schema, $fields, $repeaters),
            'display_rows' => $this->displayRows($schema, $fields, $repeaters),
            'confirmed_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function validateNormalized(array $data): void
    {
        $schema = $this->schema();
        $fields = is_array($data['statement_fields'] ?? null) ? $data['statement_fields'] : [];
        $repeaters = is_array($data['repeaters'] ?? null) ? $data['repeaters'] : [];
        $errors = [];

        if ((int) ($data['confirm_truth'] ?? 0) !== 1) {
            $errors[] = 'A beküldéshez el kell fogadni a valóságtartalomról szóló nyilatkozatot.';
        }

        foreach ($this->allFields($schema) as $field) {
            $key = (string) ($field['key'] ?? '');
            $value = $fields[$key] ?? null;
            $label = (string) ($field['label'] ?? $key);

            if ($this->isFieldRequired($field, $fields) && $this->isEmptyValue($value, (string) ($field['type'] ?? 'text'))) {
                $errors[] = 'A(z) "' . $label . '" mező kitöltése kötelező.';
            }

            $this->validateFieldValue($field, $value, 'A(z) "' . $label . '" mező', $errors);
        }

        foreach (($schema['repeaters'] ?? []) as $repeater) {
            if (!is_array($repeater)) {
                continue;
            }

            $repeaterKey = (string) ($repeater['key'] ?? '');
            $rows = is_array($repeaters[$repeaterKey] ?? null) ? $repeaters[$repeaterKey] : [];
            $min = $this->minimumRowsForRepeater($repeater, $fields);

            if (count($rows) < $min) {
                $errors[] = 'A(z) "' . (string) ($repeater['title'] ?? $repeaterKey) . '" részben legalább ' . $min . ' adatlapot meg kell adni.';
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
                    $rowLabel = ((int) $index + 1) . '. ' . (string) ($repeater['row_label'] ?? 'adatlap');

                    if (!empty($column['required']) && $this->isEmptyValue($value, (string) ($column['type'] ?? 'text'))) {
                        $errors[] = $rowLabel . ': a(z) "' . $label . '" mező kitöltése kötelező.';
                    }

                    $this->validateFieldValue($column, $value, $rowLabel . ': a(z) "' . $label . '" mező', $errors);
                }
            }
        }

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
                    if (is_array($section['visible_when'] ?? null)) {
                        $field['_section_visible_when'] = $section['visible_when'];
                    }

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

        if (in_array((string) ($field['validation'] ?? ''), ['tax_number', 'taj_number'], true)) {
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
     * @param array<string, mixed> $fields
     */
    private function isFieldVisible(array $field, array $fields): bool
    {
        $sectionCondition = $field['_section_visible_when'] ?? null;

        if (is_array($sectionCondition) && !$this->conditionMatches($sectionCondition, $fields)) {
            return false;
        }

        $condition = $field['visible_when'] ?? null;

        return !is_array($condition) || $this->conditionMatches($condition, $fields);
    }

    /**
     * @param array<string, mixed> $repeater
     * @param array<string, mixed> $fields
     */
    private function isRepeaterVisible(array $repeater, array $fields): bool
    {
        $condition = $repeater['visible_when'] ?? null;

        return !is_array($condition) || $this->conditionMatches($condition, $fields);
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $fields
     */
    private function isFieldRequired(array $field, array $fields): bool
    {
        if (!$this->isFieldVisible($field, $fields)) {
            return false;
        }

        if (!empty($field['required'])) {
            return true;
        }

        $condition = $field['required_when'] ?? null;

        return is_array($condition) && $this->conditionMatches($condition, $fields);
    }

    /**
     * @param array<string, mixed> $repeater
     * @param array<string, mixed> $fields
     */
    private function minimumRowsForRepeater(array $repeater, array $fields): int
    {
        if (!$this->isRepeaterVisible($repeater, $fields)) {
            return 0;
        }

        $minimum = max(0, (int) ($repeater['min'] ?? 0));
        $condition = $repeater['required_when'] ?? null;

        if (is_array($condition) && $this->conditionMatches($condition, $fields)) {
            $minimum = max($minimum, (int) ($condition['min'] ?? 1));
        }

        return $minimum;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $fields
     */
    private function conditionMatches(array $condition, array $fields): bool
    {
        $fieldKey = (string) ($condition['field'] ?? '');

        if ($fieldKey === '') {
            return false;
        }

        $actual = (string) ($fields[$fieldKey] ?? '');

        if (isset($condition['values']) && is_array($condition['values'])) {
            return in_array($actual, array_map('strval', $condition['values']), true);
        }

        return $actual === (string) ($condition['value'] ?? '');
    }

    /**
     * @param array<string, mixed> $field
     */
    private function emptyValueForField(array $field)
    {
        return ((string) ($field['type'] ?? 'text')) === 'checkbox' ? 0 : '';
    }

    /**
     * @param array<string, mixed> $field
     * @param list<string> $errors
     */
    private function validateFieldValue(array $field, $value, string $label, array &$errors): void
    {
        if (($field['type'] ?? '') === 'date' && !$this->isEmptyValue($value) && !$this->isValidDate((string) $value)) {
            $errors[] = $label . 'ben hibás dátum szerepel.';
        }

        if (!empty($field['not_future'])
            && !$this->isEmptyValue($value)
            && $this->isValidDate((string) $value)
            && $this->isFutureDate((string) $value)
        ) {
            $errors[] = $label . ' nem lehet jövőbeli dátum.';
        }

        if (($field['type'] ?? '') === 'number' && !$this->isEmptyValue($value) && !is_numeric((string) $value)) {
            $errors[] = $label . 'ben csak szám szerepelhet.';
        }

        if (($field['type'] ?? '') === 'select' && !$this->isEmptyValue($value) && !$this->isAllowedOption($field, (string) $value)) {
            $errors[] = $label . 'ben érvénytelen érték szerepel.';
        }

        if (($field['validation'] ?? '') === 'tax_number' && !$this->isEmptyValue($value) && !$this->identifierValidator->isValidTaxNumber((string) $value)) {
            $errors[] = $label . 'ben hibás az adóazonosító jel.';
        }

        if (($field['validation'] ?? '') === 'taj_number' && !$this->isEmptyValue($value) && !$this->identifierValidator->isValidTajNumber((string) $value)) {
            $errors[] = $label . 'ben hibás a TAJ szám.';
        }
    }

    private function isValidDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }

    private function isFutureDate(string $value): bool
    {
        return $value > date('Y-m-d');
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
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $fields
     * @param array<string, list<array<string, mixed>>> $repeaters
     * @return array<string, string>
     */
    private function displayRows(array $schema, array $fields, array $repeaters): array
    {
        $rows = [];

        foreach ($this->allFields($schema) as $field) {
            if (!$this->isFieldVisible($field, $fields)) {
                continue;
            }

            $key = (string) ($field['key'] ?? '');
            $value = $this->displayValue($field, $fields[$key] ?? null);

            if ($value !== '') {
                $rows[(string) ($field['label'] ?? $key)] = $value;
            }
        }

        foreach (($schema['repeaters'] ?? []) as $repeater) {
            if (!is_array($repeater) || !$this->isRepeaterVisible($repeater, $fields)) {
                continue;
            }

            $repeaterKey = (string) ($repeater['key'] ?? '');
            $repeaterTitle = (string) ($repeater['title'] ?? $repeaterKey);

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
                    $rows[$repeaterTitle . ' - ' . ((int) $index + 1) . '. '
                        . (string) ($repeater['row_label'] ?? 'adatlap')] = implode(', ', $parts);
                }
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

        foreach ($this->allFields($schema) as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $this->addPlaceholder($placeholders, $key, $fields[$key] ?? '');
        }

        foreach ($repeaters as $repeaterKey => $rows) {
            $this->addPlaceholder($placeholders, $repeaterKey . '_count', count($rows));
            $this->addPlaceholder($placeholders, $repeaterKey . '_szama', count($rows));
            $this->addPlaceholder($placeholders, $repeaterKey . '_száma', count($rows));

            foreach ($rows as $index => $row) {
                $number = (int) $index + 1;

                foreach ($row as $key => $value) {
                    $this->addPlaceholder($placeholders, $repeaterKey . '_' . $number . '_' . (string) $key, is_scalar($value) || $value === null ? $value : '');
                }
            }
        }

        return $placeholders;
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

    private function cleanText($value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) ($value ?? '')) ?? '');
    }
}
