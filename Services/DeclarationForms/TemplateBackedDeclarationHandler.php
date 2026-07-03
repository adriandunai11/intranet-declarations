<?php

namespace App\Modules\Declarations\Services\DeclarationForms;

use App\Modules\Declarations\Services\Documents\DeclarationDocumentPlaceholderService;
use App\Modules\Declarations\Services\Documents\DeclarationTemplatePlaceholderScanner;
use RuntimeException;

class TemplateBackedDeclarationHandler implements DeclarationFormHandlerInterface
{
    protected object $item;
    protected DeclarationTemplatePlaceholderScanner $placeholderScanner;
    protected DeclarationDocumentPlaceholderService $documentPlaceholderService;

    public function __construct(
        object $item,
        ?DeclarationTemplatePlaceholderScanner $placeholderScanner = null,
        ?DeclarationDocumentPlaceholderService $documentPlaceholderService = null
    ) {
        $this->item = $item;
        $this->placeholderScanner = $placeholderScanner ?? new DeclarationTemplatePlaceholderScanner();
        $this->documentPlaceholderService = $documentPlaceholderService ?? new DeclarationDocumentPlaceholderService();
    }

    public function supports(string $templateCode): bool
    {
        return trim($templateCode) !== '';
    }

    public function title(object $item): string
    {
        return (string) ($item->template_name ?: 'Nyilatkozat kitöltése');
    }

    public function view(): string
    {
        return 'App\Modules\Declarations\Views\public\forms\generic_template';
    }

    public function rules(): array
    {
        return [
            'confirm_truth' => 'required',
        ];
    }

    public function normalize(array $input): array
    {
        $rawValues = $input['placeholder_values'] ?? [];
        $rawValues = is_array($rawValues) ? $rawValues : [];
        $templateFields = [];

        foreach ($this->editablePlaceholders() as $placeholder) {
            $encodedKey = rawurlencode($placeholder);
            $value = $rawValues[$encodedKey] ?? $rawValues[$placeholder] ?? null;

            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map('trim', array_map('strval', $value))));
            }

            $templateFields[$placeholder] = trim((string) ($value ?? ''));
        }

        $data = [
            'confirm_truth' => !empty($input['confirm_truth']) ? '1' : '',
            'template_code' => (string) ($this->item->template_code ?? ''),
            'template_name' => (string) ($this->item->template_name ?? ''),
            'template_version' => (string) ($this->item->template_version ?? ''),
            'template_fields' => $templateFields,
            'confirmed_at' => date('Y-m-d H:i:s'),
        ];

        foreach ($templateFields as $key => $value) {
            $data[$key] = $value;
        }

        return $data;
    }

    public function validateNormalized(array $data): void
    {
        if (!$this->canInspectTemplate()) {
            throw new RuntimeException('A sablon mezőinek kiolvasásához a PHP zip extension szükséges a szerveren.');
        }

        if (!$this->hasTemplatePlaceholders()) {
            throw new RuntimeException('Ez a DOCX sablon még nincs online kitöltésre előkészítve. Tegyél a sablonba ${...} helyőrzőket.');
        }

        if (($data['confirm_truth'] ?? '') !== '1') {
            throw new RuntimeException('A beküldéshez el kell fogadni a valóságtartalomról szóló nyilatkozatot.');
        }

        foreach (($data['template_fields'] ?? []) as $placeholder => $value) {
            if (trim((string) $value) === '') {
                throw new RuntimeException('A(z) "' . $this->labelForPlaceholder((string) $placeholder) . '" mező kitöltése kötelező.');
            }
        }
    }

    /**
     * @return list<array{key:string, encoded_key:string, label:string, required:bool}>
     */
    public function editableFields(): array
    {
        return array_map(function (string $placeholder): array {
            return [
                'key' => $placeholder,
                'encoded_key' => rawurlencode($placeholder),
                'label' => $this->labelForPlaceholder($placeholder),
                'required' => true,
            ];
        }, $this->editablePlaceholders());
    }

    /**
     * @return list<string>
     */
    public function templatePlaceholders(): array
    {
        return $this->placeholderScanner->placeholdersForItem($this->item);
    }

    public function hasTemplatePlaceholders(): bool
    {
        return $this->templatePlaceholders() !== [];
    }

    public function canInspectTemplate(): bool
    {
        return $this->placeholderScanner->canReadDocx();
    }

    /**
     * @return list<string>
     */
    public function editablePlaceholders(): array
    {
        $known = [];

        foreach ($this->documentPlaceholderService->knownPlaceholderKeys() as $key) {
            $known[$this->lookupKey($key)] = true;
        }

        $editable = [];

        foreach ($this->templatePlaceholders() as $placeholder) {
            if (isset($known[$this->lookupKey($placeholder)])) {
                continue;
            }

            $editable[] = $placeholder;
        }

        return $editable;
    }

    public function templateFileAvailable(): bool
    {
        return $this->placeholderScanner->templateExistsForItem($this->item);
    }

    private function labelForPlaceholder(string $placeholder): string
    {
        $label = str_replace(['_', '-'], ' ', $placeholder);

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
        }

        return ucfirst($label);
    }

    private function lookupKey(string $value): string
    {
        $value = trim($value);

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }
}
