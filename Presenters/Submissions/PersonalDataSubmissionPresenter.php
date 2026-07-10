<?php

namespace App\Modules\Declarations\Presenters\Submissions;

use App\Modules\Declarations\Entities\DeclarationSubmission;
use App\Modules\Declarations\Services\DeclarationSubmissionDataNormalizer;

class PersonalDataSubmissionPresenter implements SubmissionPresenterInterface
{
    private DeclarationSubmissionDataNormalizer $dataNormalizer;

    public function __construct(?DeclarationSubmissionDataNormalizer $dataNormalizer = null)
    {
        $this->dataNormalizer = $dataNormalizer ?? new DeclarationSubmissionDataNormalizer();
    }

    public function supports(string $templateCode): bool
    {
        return $templateCode === 'personal_data_statement';
    }

    public function rows(DeclarationSubmission $submission): array
    {
        $data = $this->data($submission);

        return [
            'Születési név' => $this->value($data, 'birth_name'),
            'Anyja neve' => $this->value($data, 'mother_name'),
            'Születési hely' => $this->value($data, 'birth_place'),
            'Születési dátum' => $this->value($data, 'birth_date'),
            'Adóazonosító jel' => $this->value($data, 'tax_number'),
            'TAJ szám' => $this->value($data, 'taj_number'),
            'Telefonszám' => $this->value($data, 'phone'),
        ];
    }

    private function data(DeclarationSubmission $submission): array
    {
        return $this->dataNormalizer->normalize($submission->data_json ?? []);
    }

    private function value(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        if (is_array($value) || $value instanceof \stdClass) {
            return '-';
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : '-';
    }
}
