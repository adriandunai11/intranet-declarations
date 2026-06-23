<?php

namespace App\Modules\Declarations\Services\DeclarationForms;

use RuntimeException;

class BankAccountDeclarationHandler implements DeclarationFormHandlerInterface
{
    public function supports(string $templateCode): bool
    {
        return $templateCode === 'bank_account_statement';
    }

    public function title(object $item): string
    {
        return (string) ($item->template_name ?: 'Bankszámlaszám nyilatkozat');
    }

    public function view(): string
    {
        return 'App\Modules\Declarations\Views\public\forms\bank_account';
    }

    public function rules(): array
    {
        return [
            'account_holder' => 'required|min_length[3]|max_length[190]',
            'bank_name' => 'required|min_length[2]|max_length[190]',
            'bank_account_number' => 'required|min_length[17]|max_length[35]',
            'confirm_truth' => 'required',
        ];
    }

    public function normalize(array $input): array
    {
        return [
            'account_holder' => trim((string) ($input['account_holder'] ?? '')),
            'bank_name' => trim((string) ($input['bank_name'] ?? '')),
            'bank_account_number' => preg_replace('/[^0-9]/', '', (string) ($input['bank_account_number'] ?? '')),
            'confirm_truth' => !empty($input['confirm_truth']) ? 1 : 0,
        ];
    }

    public function validateNormalized(array $data): void
    {
        $length = strlen((string) ($data['bank_account_number'] ?? ''));

        if (!in_array($length, [16, 24], true)) {
            throw new RuntimeException('A bankszámlaszámnak 16 vagy 24 számjegyből kell állnia.');
        }

        if (!$this->isValidHungarianBankAccountNumber((string) ($data['bank_account_number'] ?? ''))) {
            throw new RuntimeException('A bankszámlaszám ellenőrző száma hibás.');
        }

        if ((int) ($data['confirm_truth'] ?? 0) !== 1) {
            throw new RuntimeException('A beküldéshez el kell fogadni a valóságtartalomról szóló nyilatkozatot.');
        }
    }

    private function isValidHungarianBankAccountNumber(string $value): bool
    {
        if (!preg_match('/^\d{16}(\d{8})?$/', $value)) {
            return false;
        }

        $weights = [9, 7, 3, 1, 9, 7, 3, 1];

        foreach (str_split($value, 8) as $block) {
            $sum = 0;

            for ($i = 0; $i < 8; $i++) {
                $sum += ((int) $block[$i]) * $weights[$i];
            }

            if ($sum % 10 !== 0) {
                return false;
            }
        }

        return true;
    }
}
