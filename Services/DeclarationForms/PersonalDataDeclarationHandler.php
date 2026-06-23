<?php

namespace App\Modules\Declarations\Services\DeclarationForms;

use RuntimeException;

class PersonalDataDeclarationHandler implements DeclarationFormHandlerInterface
{
    public function supports(string $templateCode): bool
    {
        return $templateCode === 'personal_data_statement';
    }

    public function title(object $item): string
    {
        return (string) ($item->template_name ?: 'Személyes adatok nyilatkozata');
    }

    public function view(): string
    {
        return 'App\Modules\Declarations\Views\public\forms\personal_data';
    }

    public function rules(): array
    {
        return [
            'birth_name' => 'required|min_length[3]|max_length[190]',
            'mother_name' => 'required|min_length[3]|max_length[190]',
            'birth_place' => 'required|min_length[2]|max_length[100]',
            'birth_date' => 'required|valid_date[Y-m-d]',
            'tax_number' => 'required|min_length[10]|max_length[10]',
            'taj_number' => 'required|min_length[9]|max_length[9]',
            'phone' => 'required|min_length[6]|max_length[50]',
            'confirm_truth' => 'required',
        ];
    }

    public function normalize(array $input): array
    {
        return [
            'birth_name' => $this->cleanText($input['birth_name'] ?? ''),
            'mother_name' => $this->cleanText($input['mother_name'] ?? ''),
            'birth_place' => $this->cleanText($input['birth_place'] ?? ''),
            'birth_date' => trim((string) ($input['birth_date'] ?? '')),
            'tax_number' => preg_replace('/\D+/', '', (string) ($input['tax_number'] ?? '')),
            'taj_number' => preg_replace('/\D+/', '', (string) ($input['taj_number'] ?? '')),
            'phone' => $this->cleanText($input['phone'] ?? ''),
            'confirm_truth' => !empty($input['confirm_truth']) ? 1 : 0,
        ];
    }

    public function validateNormalized(array $data): void
    {
        if (strlen((string) ($data['tax_number'] ?? '')) !== 10) {
            throw new RuntimeException('Az adóazonosító jelnek pontosan 10 számjegyből kell állnia.');
        }

        if (strlen((string) ($data['taj_number'] ?? '')) !== 9) {
            throw new RuntimeException('A TAJ számnak pontosan 9 számjegyből kell állnia.');
        }

        if ((int) ($data['confirm_truth'] ?? 0) !== 1) {
            throw new RuntimeException('A beküldéshez el kell fogadni a valóságtartalomról szóló nyilatkozatot.');
        }
    }

    private function cleanText($value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value));
    }
}
