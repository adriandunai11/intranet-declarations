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
            return $rows;
        }

        $templateFields = is_array($data['template_fields'] ?? null) ? $data['template_fields'] : [];

        foreach ($templateFields as $key => $value) {
            $rows[$this->humanizeKey((string) $key)] = (string) $value;
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

            $rows[$this->humanizeKey((string) $key)] = (string) $value;
        }

        return $rows !== [] ? $rows : [
            'Beküldött adat' => '-',
        ];
    }

    private function humanizeKey(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
