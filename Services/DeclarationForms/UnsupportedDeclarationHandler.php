<?php

namespace App\Modules\Declarations\Services\DeclarationForms;

use RuntimeException;

class UnsupportedDeclarationHandler implements DeclarationFormHandlerInterface
{
    public function supports(string $templateCode): bool
    {
        return true;
    }

    public function title(object $item): string
    {
        return (string) ($item->template_name ?: 'Nyilatkozat nem elérhető');
    }

    public function view(): string
    {
        return 'App\Modules\Declarations\Views\public\forms\unsupported';
    }

    public function rules(): array
    {
        return [];
    }

    public function normalize(array $input): array
    {
        return [];
    }

    public function validateNormalized(array $data): void
    {
        throw new RuntimeException('Ez a nyilatkozat még nem küldhető be online.');
    }
}
