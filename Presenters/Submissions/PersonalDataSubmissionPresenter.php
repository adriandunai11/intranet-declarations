<?php

namespace App\Modules\Declarations\Presenters\Submissions;

use App\Modules\Declarations\Entities\DeclarationSubmission;

class PersonalDataSubmissionPresenter implements SubmissionPresenterInterface
{
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
