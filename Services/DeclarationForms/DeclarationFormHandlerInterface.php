<?php

namespace App\Modules\Declarations\Services\DeclarationForms;

interface DeclarationFormHandlerInterface
{
    public function supports(string $templateCode): bool;

    public function title(object $item): string;

    public function view(): string;

    public function rules(): array;

    public function normalize(array $input): array;

    public function validateNormalized(array $data): void;
}
