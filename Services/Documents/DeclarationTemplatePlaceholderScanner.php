<?php

namespace App\Modules\Declarations\Services\Documents;

use ZipArchive;

class DeclarationTemplatePlaceholderScanner
{
    protected DeclarationTemplateFileResolver $fileResolver;

    public function __construct(?DeclarationTemplateFileResolver $fileResolver = null)
    {
        $this->fileResolver = $fileResolver ?? new DeclarationTemplateFileResolver();
    }

    /**
     * @return list<string>
     */
    public function placeholdersForItem(object $item): array
    {
        $path = $this->fileResolver->resolveForItem($item);

        if ($path === null) {
            return [];
        }

        return $this->placeholdersInTemplate($path);
    }

    public function templateExistsForItem(object $item): bool
    {
        return $this->fileResolver->resolveForItem($item) !== null;
    }

    public function canReadDocx(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * @return list<string>
     */
    public function placeholdersInTemplate(string $templatePath): array
    {
        if (!is_file($templatePath) || !$this->canReadDocx()) {
            return [];
        }

        $zip = new ZipArchive();

        if ($zip->open($templatePath) !== true) {
            return [];
        }

        $placeholders = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (!is_string($name) || !$this->isScannableXml($name)) {
                continue;
            }

            $xml = $zip->getFromName($name);

            if (!is_string($xml)) {
                continue;
            }

            foreach ($this->extractPlaceholders($xml) as $placeholder) {
                $placeholders[$placeholder] = true;
            }
        }

        $zip->close();

        $result = array_keys($placeholders);
        sort($result, SORT_NATURAL | SORT_FLAG_CASE);

        return $result;
    }

    private function isScannableXml(string $name): bool
    {
        return $name === 'word/document.xml'
            || (bool) preg_match('/^word\/(header|footer)\d*\.xml$/', $name)
            || in_array($name, ['word/footnotes.xml', 'word/endnotes.xml'], true);
    }

    /**
     * @return list<string>
     */
    private function extractPlaceholders(string $xml): array
    {
        $visibleText = $this->visibleText($xml);

        $placeholders = [];

        if (!preg_match_all('/\$\{([^}]+)\}/u', $visibleText, $matches)) {
            return [];
        }

        foreach ($matches[1] as $placeholder) {
            $placeholder = trim((string) $placeholder);

            if ($placeholder !== '') {
                $placeholders[$placeholder] = true;
            }
        }

        return array_keys($placeholders);
    }

    private function visibleText(string $xml): string
    {
        if (!preg_match_all('/<w:t\b[^>]*>(.*?)<\/w:t>/si', $xml, $matches)) {
            return '';
        }

        $text = '';

        foreach ($matches[1] as $part) {
            $text .= html_entity_decode((string) $part, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        return $text;
    }
}
