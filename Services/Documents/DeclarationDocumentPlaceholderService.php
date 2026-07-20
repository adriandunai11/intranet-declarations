<?php

namespace App\Modules\Declarations\Services\Documents;

use App\Modules\Declarations\Entities\DeclarationSubmission;
use App\Modules\Declarations\Services\DeclarationSubmissionDataNormalizer;

class DeclarationDocumentPlaceholderService
{
    private DeclarationSubmissionDataNormalizer $dataNormalizer;

    public function __construct(?DeclarationSubmissionDataNormalizer $dataNormalizer = null)
    {
        $this->dataNormalizer = $dataNormalizer ?? new DeclarationSubmissionDataNormalizer();
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
            'Nyilatkozati év' => (string) ($packet->tax_year ?? '-'),
            'Nyilatkozatverzió' => (string) ($item->template_version ?? '-'),
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
            'note' => 'Ez az összesítő a kitöltő online űrlapon megadott adataiból készül.',
        ];
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

}
