<?php

namespace App\Modules\Declarations\Presenters\Submissions;

use App\Modules\Declarations\Entities\DeclarationSubmission;

class BankAccountSubmissionPresenter implements SubmissionPresenterInterface
{
    public function supports(string $templateCode): bool
    {
        return $templateCode === 'bank_account_statement';
    }

    public function rows(DeclarationSubmission $submission): array
    {
        $data = $this->data($submission);

        return [
            'Számlatulajdonos' => $this->value($data, 'account_holder'),
            'Bank neve' => $this->value($data, 'bank_name'),
            'Bankszámlaszám' => $this->formatBankAccountNumber(
                $this->value($data, 'bank_account_number')
            ),
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

    private function formatBankAccountNumber(string $value): string
    {
        if ($value === '-' || $value === '') {
            return '-';
        }

        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return '-';
        }

        if (strlen($digits) === 16) {
            return substr($digits, 0, 8) . '-' . substr($digits, 8, 8);
        }

        if (strlen($digits) === 24) {
            return substr($digits, 0, 8) . '-' . substr($digits, 8, 8) . '-' . substr($digits, 16, 8);
        }

        return $value;
    }
}