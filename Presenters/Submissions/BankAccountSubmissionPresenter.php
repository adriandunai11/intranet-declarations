<?php

namespace App\Modules\Declarations\Presenters\Submissions;

use App\Modules\Declarations\Entities\DeclarationSubmission;
use App\Modules\Declarations\Services\DeclarationSubmissionDataNormalizer;

class BankAccountSubmissionPresenter implements SubmissionPresenterInterface
{
    private DeclarationSubmissionDataNormalizer $dataNormalizer;

    public function __construct(?DeclarationSubmissionDataNormalizer $dataNormalizer = null)
    {
        $this->dataNormalizer = $dataNormalizer ?? new DeclarationSubmissionDataNormalizer();
    }

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
