<?php

namespace App\Modules\Declarations\Services\Documents;

use DOMDocument;
use DOMElement;
use DOMXPath;
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
        return class_exists(ZipArchive::class) || class_exists(\PharData::class);
    }

    /**
     * @return list<string>
     */
    public function placeholdersInTemplate(string $templatePath): array
    {
        if (!is_file($templatePath) || !$this->canReadDocx()) {
            return [];
        }

        if (!class_exists(ZipArchive::class)) {
            return $this->placeholdersInTemplateWithPharData($templatePath);
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

    /**
     * @return list<string>
     */
    private function placeholdersInTemplateWithPharData(string $templatePath): array
    {
        if (!class_exists(\PharData::class)) {
            return [];
        }

        try {
            $archive = new \PharData($templatePath);
        } catch (\Throwable $e) {
            return [];
        }

        $placeholders = [];

        foreach (new \RecursiveIteratorIterator($archive) as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathName());
            $markerPosition = strrpos($path, '.docx/');

            if ($markerPosition === false) {
                continue;
            }

            $name = substr($path, $markerPosition + 6);

            if (!$this->isScannableXml($name) || !isset($archive[$name])) {
                continue;
            }

            $xml = $archive[$name]->getContent();

            if (!is_string($xml)) {
                continue;
            }

            foreach ($this->extractPlaceholders($xml) as $placeholder) {
                $placeholders[$placeholder] = true;
            }
        }

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

            if ($this->isCleanPlaceholder($placeholder)) {
                $placeholders[$placeholder] = true;
            }
        }

        return array_keys($placeholders);
    }

    private function visibleText(string $xml): string
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded) {
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $text = '';

            foreach ($xpath->query('//w:t') ?: [] as $node) {
                if ($node instanceof DOMElement) {
                    $text .= (string) $node->nodeValue;
                }
            }

            return $text;
        }

        if (preg_match_all('/<w:t\b[^>]*>(.*?)<\/w:t>/si', $xml, $matches)) {
            $text = '';

            foreach ($matches[1] as $part) {
                $text .= html_entity_decode((string) $part, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }

            return $text;
        }

        return '';
    }

    private function isCleanPlaceholder(string $placeholder): bool
    {
        if ($placeholder === '' || strlen($placeholder) > 80) {
            return false;
        }

        if (str_contains($placeholder, '<') || str_contains($placeholder, '>') || str_contains($placeholder, '"')) {
            return false;
        }

        return (bool) preg_match('/^[\p{L}\p{N}_\-. ]+$/u', $placeholder);
    }
}
