<?php

namespace App\Modules\Declarations\Services\DeclarationForms;

use App\Modules\Declarations\Services\Validation\HungarianIdentifierValidator;
use RuntimeException;

class BankAccountDeclarationHandler implements DeclarationFormHandlerInterface
{
    protected HungarianIdentifierValidator $identifierValidator;

    public function __construct()
    {
        $this->identifierValidator = new HungarianIdentifierValidator();
    }
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
            'bank_account_number' => 'required|min_length[16]|max_length[35]',
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

        if (!$this->identifierValidator->isValidHungarianBankAccountNumber((string) ($data['bank_account_number'] ?? ''))) {
            throw new RuntimeException('A bankszámlaszám ellenőrző száma hibás.');
        }

        if ((int) ($data['confirm_truth'] ?? 0) !== 1) {
            throw new RuntimeException('A beküldéshez el kell fogadni a valóságtartalomról szóló nyilatkozatot.');
        }
    }

}
