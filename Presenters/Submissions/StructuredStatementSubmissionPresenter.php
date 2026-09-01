<?php

namespace App\Modules\Declarations\Presenters\Submissions;

use App\Modules\Declarations\Entities\DeclarationSubmission;
use App\Modules\Declarations\Services\DeclarationForms\StructuredStatementSchemaService;
use App\Modules\Declarations\Services\DeclarationSubmissionDataNormalizer;

class StructuredStatementSubmissionPresenter implements SubmissionPresenterInterface
{
    private StructuredStatementSchemaService $schemaService;
    private DeclarationSubmissionDataNormalizer $dataNormalizer;

    public function __construct(
        ?StructuredStatementSchemaService $schemaService = null,
        ?DeclarationSubmissionDataNormalizer $dataNormalizer = null
    ) {
        $this->schemaService = $schemaService ?? new StructuredStatementSchemaService();
        $this->dataNormalizer = $dataNormalizer ?? new DeclarationSubmissionDataNormalizer();
    }

    public function supports(string $templateCode): bool
    {
        return $this->schemaService->supports($templateCode);
    }

    public function rows(DeclarationSubmission $submission): array
    {
        $data = $this->data($submission);

        return $this->rowsFromData($data, (string) ($data['template_code'] ?? ''));
    }

    public function rowsForTemplate(string $templateCode, DeclarationSubmission $submission): array
    {
        $data = $this->data($submission);

        return $this->rowsFromData($data, $this->effectiveTemplateCode($data, $templateCode));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function rowsFromData(array $data, string $templateCode): array
    {
        $displayRows = is_array($data['display_rows'] ?? null) ? $data['display_rows'] : [];

        if ($displayRows !== []) {
            return $this->withoutTableRows($this->cleanRows($displayRows), $this->tablesFromData($data, $templateCode));
        }

        if ($templateCode === '' || !$this->schemaService->supports($templateCode)) {
            return [];
        }

        $schema = $this->schemaService->schemaFor($templateCode);
        $fields = is_array($data['statement_fields'] ?? null) ? $data['statement_fields'] : [];
        $rows = [];

        foreach (($schema['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }

            foreach (($section['fields'] ?? []) as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $key = (string) ($field['key'] ?? '');
                $value = $this->displayValue($field, $fields[$key] ?? null);

                if ($value !== '') {
                    $rows[(string) ($field['label'] ?? $key)] = $value;
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<array{title:string, columns:list<string>, rows:list<list<string>>}>
     */
    public function tables(DeclarationSubmission $submission): array
    {
        $data = $this->data($submission);

        return $this->tablesFromData($data, (string) ($data['template_code'] ?? ''));
    }

    /**
     * @return list<array{title:string, columns:list<string>, rows:list<list<string>>}>
     */
    public function tablesForTemplate(string $templateCode, DeclarationSubmission $submission): array
    {
        $data = $this->data($submission);

        return $this->tablesFromData($data, $this->effectiveTemplateCode($data, $templateCode));
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{title:string, columns:list<string>, rows:list<list<string>>}>
     */
    private function tablesFromData(array $data, string $templateCode): array
    {
        $templateCode = $this->effectiveTemplateCode($data, $templateCode);

        if ($templateCode === '' || !$this->schemaService->supports($templateCode)) {
            return [];
        }

        $schema = $this->schemaService->schemaFor($templateCode);
        $repeaters = is_array($data['repeaters'] ?? null) ? $data['repeaters'] : [];
        $tables = [];

        foreach (($schema['repeaters'] ?? []) as $repeater) {
            if (!is_array($repeater)) {
                continue;
            }

            $repeaterKey = (string) ($repeater['key'] ?? '');
            $rowsForRepeater = is_array($repeaters[$repeaterKey] ?? null) ? $repeaters[$repeaterKey] : [];

            if ($repeaterKey === '' || $rowsForRepeater === []) {
                continue;
            }

            $columns = is_array($repeater['columns'] ?? null)
                ? array_values(array_filter($repeater['columns'], 'is_array'))
                : [];

            if ($columns === []) {
                continue;
            }

            $tableRows = [];

            foreach ($rowsForRepeater as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $tableRow = [];

                foreach ($columns as $column) {
                    $columnKey = (string) ($column['key'] ?? '');
                    $value = $this->displayValue($column, $row[$columnKey] ?? null);
                    $tableRow[] = $value !== '' ? $value : '-';
                }

                if (array_filter($tableRow, static fn(string $value): bool => $value !== '-') !== []) {
                    $tableRows[] = $tableRow;
                }
            }

            if ($tableRows !== []) {
                $tables[] = [
                    'title' => (string) ($repeater['title'] ?? $repeaterKey),
                    'columns' => array_map(
                        static fn(array $column): string => (string) ($column['label'] ?? $column['key'] ?? ''),
                        $columns
                    ),
                    'rows' => $tableRows,
                ];
            }
        }

        return $tables;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function effectiveTemplateCode(array $data, string $fallbackTemplateCode = ''): string
    {
        $dataTemplateCode = (string) ($data['template_code'] ?? '');

        return $fallbackTemplateCode !== '' ? $fallbackTemplateCode : $dataTemplateCode;
    }

    private function data(DeclarationSubmission $submission): array
    {
        return $this->dataNormalizer->normalize($submission->data_json ?? []);
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

        return trim((string) ($value ?? ''));
    }

    /**
     * @param array<string, mixed> $rows
     * @return array<string, string>
     */
    private function cleanRows(array $rows): array
    {
        $clean = [];

        foreach ($rows as $label => $value) {
            $label = trim((string) $label);

            if ($label === '') {
                continue;
            }

            $clean[$label] = trim((string) $value) !== '' ? (string) $value : '-';
        }

        return $clean;
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
}
