<?php

namespace App\Modules\Declarations\Presenters\Submissions;

use App\Modules\Declarations\Entities\DeclarationSubmission;
use App\Modules\Declarations\Services\DeclarationSubmissionDataNormalizer;
use App\Modules\Declarations\Services\DeclarationForms\TaxDeclarationSchemaService;

class TaxSubmissionPresenter implements SubmissionPresenterInterface
{
    private TaxDeclarationSchemaService $schemaService;
    private DeclarationSubmissionDataNormalizer $dataNormalizer;

    public function __construct(
        ?TaxDeclarationSchemaService $schemaService = null,
        ?DeclarationSubmissionDataNormalizer $dataNormalizer = null
    )
    {
        $this->schemaService = $schemaService ?? new TaxDeclarationSchemaService();
        $this->dataNormalizer = $dataNormalizer ?? new DeclarationSubmissionDataNormalizer();
    }

    public function supports(string $templateCode): bool
    {
        return $this->schemaService->supports($templateCode);
    }

    public function rows(DeclarationSubmission $submission): array
    {
        $data = $this->data($submission);
        $displayRows = is_array($data['display_rows'] ?? null) ? $data['display_rows'] : [];

        if ($displayRows !== []) {
            return $this->cleanRows($displayRows);
        }

        $schema = $this->schemaService->schemaFor((string) ($data['template_code'] ?? ''));

        return $this->rowsFromStructuredData($schema, $data);
    }

    private function data(DeclarationSubmission $submission): array
    {
        return $this->dataNormalizer->normalize($submission->data_json ?? []);
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function rowsFromStructuredData(array $schema, array $data): array
    {
        $fields = is_array($data['tax_fields'] ?? null) ? $data['tax_fields'] : [];
        $repeaters = is_array($data['repeaters'] ?? null) ? $data['repeaters'] : [];
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

        foreach (($schema['repeaters'] ?? []) as $repeater) {
            if (!is_array($repeater)) {
                continue;
            }

            $repeaterKey = (string) ($repeater['key'] ?? '');
            $repeaterTitle = (string) ($repeater['title'] ?? $repeaterKey);
            $rowsForRepeater = is_array($repeaters[$repeaterKey] ?? null) ? $repeaters[$repeaterKey] : [];

            foreach ($rowsForRepeater as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }

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
                    $rows[$repeaterTitle . ' - ' . ((int) $index + 1) . '. sor'] = implode(', ', $parts);
                }
            }
        }

        if ($rows === [] && !empty($data['confirm_truth'])) {
            $rows['Nyilatkozat'] = 'Megerősítve';
        }

        return $rows !== [] ? $rows : [
            'Beküldött adat' => '-',
        ];
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

        return $clean !== [] ? $clean : [
            'Beküldött adat' => '-',
        ];
    }
}
