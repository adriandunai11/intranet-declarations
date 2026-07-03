<?php

namespace App\Modules\Declarations\Services\Documents;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class DeclarationDocumentGenerator
{
    /**
     * DOCX sablon kitöltése ${kulcs} formájú helyőrzőkkel.
     *
     * @param array<string, scalar|null> $placeholders
     */
    public function generateDocx(string $templatePath, array $placeholders, string $outputPath): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('A DOCX generáláshoz a PHP zip extension szükséges.');
        }

        if (!is_file($templatePath)) {
            throw new RuntimeException('A DOCX sablon nem található: ' . $templatePath);
        }

        $this->ensureDirectory(dirname($outputPath));

        if (!copy($templatePath, $outputPath)) {
            throw new RuntimeException('A DOCX kimeneti fájl létrehozása sikertelen.');
        }

        $zip = new ZipArchive();

        if ($zip->open($outputPath) !== true) {
            throw new RuntimeException('A DOCX fájl nem nyitható meg szerkesztésre.');
        }

        $replacementMap = $this->replacementMap($placeholders);

        foreach ($this->docxXmlFiles($zip) as $xmlFile) {
            $xml = $zip->getFromName($xmlFile);

            if ($xml === false) {
                continue;
            }

            $zip->addFromString($xmlFile, $this->replacePlaceholdersInXml($xml, $replacementMap));
        }

        $zip->close();

        return $outputPath;
    }

    /**
     * @param array<string, scalar|null> $placeholders
     */
    public function generatePdf(string $templatePath, array $placeholders, string $outputPath): string
    {
        $temporaryDocx = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . uniqid('declaration_', true)
            . '.docx';

        $this->generateDocx($templatePath, $placeholders, $temporaryDocx);

        try {
            return $this->convertDocxToPdf($temporaryDocx, $outputPath);
        } finally {
            if (is_file($temporaryDocx)) {
                @unlink($temporaryDocx);
            }
        }
    }

    public function convertDocxToPdf(string $docxPath, string $outputPath): string
    {
        $config = config(\App\Modules\Declarations\Config\Declarations::class);
        $commandTemplate = trim((string) ($config->docxToPdfCommand ?? ''));

        if ($commandTemplate !== '') {
            return $this->convertWithCommandTemplate($commandTemplate, $docxPath, $outputPath);
        }

        return $this->convertWithLibreOffice($docxPath, $outputPath);
    }

    private function convertWithCommandTemplate(string $commandTemplate, string $docxPath, string $outputPath): string
    {
        $this->ensureDirectory(dirname($outputPath));

        $command = strtr($commandTemplate, [
            '{input}' => escapeshellarg($docxPath),
            '{output}' => escapeshellarg($outputPath),
            '{output_dir}' => escapeshellarg(dirname($outputPath)),
        ]);

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0 || !is_file($outputPath)) {
            log_message('error', 'DOCX-PDF conversion failed: ' . implode("\n", $output));

            throw new RuntimeException('A PDF generálás sikertelen.');
        }

        return $outputPath;
    }

    private function convertWithLibreOffice(string $docxPath, string $outputPath): string
    {
        $binary = $this->findLibreOfficeBinary();

        if ($binary === null) {
            throw new RuntimeException('A PDF előnézethez LibreOffice vagy soffice szükséges a szerveren, vagy állítsd be a docxToPdfCommand értéket.');
        }

        $this->ensureDirectory(dirname($outputPath));

        $outputDirectory = dirname($outputPath);
        $generatedPath = $outputDirectory
            . DIRECTORY_SEPARATOR
            . pathinfo($docxPath, PATHINFO_FILENAME)
            . '.pdf';

        if (is_file($outputPath)) {
            @unlink($outputPath);
        }

        if ($generatedPath !== $outputPath && is_file($generatedPath)) {
            @unlink($generatedPath);
        }

        $command = escapeshellarg($binary)
            . ' --headless --convert-to pdf --outdir '
            . escapeshellarg($outputDirectory)
            . ' '
            . escapeshellarg($docxPath);

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0 || (!is_file($generatedPath) && !is_file($outputPath))) {
            log_message('error', 'LibreOffice DOCX-PDF conversion failed: ' . implode("\n", $output));

            throw new RuntimeException('A PDF generálás sikertelen.');
        }

        if (!is_file($outputPath)) {
            if (!@rename($generatedPath, $outputPath)) {
                if (!@copy($generatedPath, $outputPath)) {
                    throw new RuntimeException('A PDF kimeneti fájl létrehozása sikertelen.');
                }

                @unlink($generatedPath);
            }
        }

        return $outputPath;
    }

    private function findLibreOfficeBinary(): ?string
    {
        $environmentBinary = trim((string) getenv('LIBREOFFICE_BINARY'));

        if ($environmentBinary !== '' && is_file($environmentBinary)) {
            return $environmentBinary;
        }

        foreach ([
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            '/usr/bin/soffice',
            '/usr/local/bin/soffice',
            '/usr/bin/libreoffice',
            '/usr/local/bin/libreoffice',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        foreach (['soffice', 'libreoffice', 'lowriter'] as $name) {
            $path = $this->findExecutableInPath($name);

            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    private function findExecutableInPath(string $name): ?string
    {
        $path = (string) getenv('PATH');

        if ($path === '') {
            return null;
        }

        $extensions = [''];

        if (DIRECTORY_SEPARATOR === '\\') {
            $pathext = (string) getenv('PATHEXT');
            $extensions = array_filter(array_map('strtolower', explode(PATH_SEPARATOR, $pathext))) ?: ['.exe', '.bat', '.cmd'];
        }

        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            $directory = trim($directory);

            if ($directory === '') {
                continue;
            }

            foreach ($extensions as $extension) {
                $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name . $extension;

                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, scalar|null> $placeholders
     * @return array<string, string>
     */
    private function replacementMap(array $placeholders): array
    {
        $map = [];

        foreach ($placeholders as $key => $value) {
            $normalizedKey = trim((string) $key);

            if ($normalizedKey === '') {
                continue;
            }

            $map['${' . $normalizedKey . '}'] = (string) ($value ?? '');
        }

        return $map;
    }

    /**
     * @param array<string, string> $replacementMap
     */
    private function replacePlaceholdersInXml(string $xml, array $replacementMap): string
    {
        if ($replacementMap === []) {
            return $xml;
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            $escapedMap = [];

            foreach ($replacementMap as $key => $value) {
                $escapedMap[$key] = htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }

            return strtr($xml, $escapedMap);
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        foreach ($xpath->query('//w:p|//w:tbl') ?: [] as $container) {
            if (!$container instanceof DOMElement) {
                continue;
            }

            $textNodes = [];

            foreach ($xpath->query('.//w:t', $container) ?: [] as $textNode) {
                $textNodes[] = $textNode;
            }

            $this->replaceInTextNodes($textNodes, $replacementMap);
        }

        return $document->saveXML() ?: $xml;
    }

    /**
     * @param list<\DOMNode> $textNodes
     * @param array<string, string> $replacementMap
     */
    private function replaceInTextNodes(array $textNodes, array $replacementMap): void
    {
        if ($textNodes === []) {
            return;
        }

        $text = '';
        $ranges = [];

        foreach ($textNodes as $index => $node) {
            $start = strlen($text);
            $value = (string) $node->nodeValue;
            $text .= $value;
            $ranges[$index] = [
                'start' => $start,
                'end' => $start + strlen($value),
            ];
        }

        foreach ($replacementMap as $placeholder => $replacement) {
            $offset = 0;

            while (($position = strpos($text, $placeholder, $offset)) !== false) {
                $end = $position + strlen($placeholder);
                $startIndex = $this->nodeIndexForPosition($ranges, $position);
                $endIndex = $this->nodeIndexForPosition($ranges, max($position, $end - 1));

                if ($startIndex === null || $endIndex === null) {
                    $offset = $end;
                    continue;
                }

                $startNode = $textNodes[$startIndex];
                $endNode = $textNodes[$endIndex];
                $startRange = $ranges[$startIndex];
                $endRange = $ranges[$endIndex];

                $prefix = substr((string) $startNode->nodeValue, 0, $position - $startRange['start']);
                $suffix = substr((string) $endNode->nodeValue, $end - $endRange['start']);
                $startNode->nodeValue = $prefix . $replacement . $suffix;

                for ($index = $startIndex + 1; $index <= $endIndex; $index++) {
                    $textNodes[$index]->nodeValue = '';
                }

                $text = substr($text, 0, $position) . $replacement . substr($text, $end);
                $offset = $position + strlen($replacement);

                $text = '';
                foreach ($textNodes as $index => $node) {
                    $start = strlen($text);
                    $value = (string) $node->nodeValue;
                    $text .= $value;
                    $ranges[$index] = [
                        'start' => $start,
                        'end' => $start + strlen($value),
                    ];
                }
            }
        }
    }

    /**
     * @param array<int, array{start:int,end:int}> $ranges
     */
    private function nodeIndexForPosition(array $ranges, int $position): ?int
    {
        foreach ($ranges as $index => $range) {
            if ($position >= $range['start'] && $position < $range['end']) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function docxXmlFiles(ZipArchive $zip): array
    {
        $files = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (!is_string($name)) {
                continue;
            }

            if (
                $name === 'word/document.xml'
                || preg_match('/^word\/(header|footer)\d*\.xml$/', $name)
                || in_array($name, ['word/footnotes.xml', 'word/endnotes.xml'], true)
            ) {
                $files[] = $name;
            }
        }

        return $files;
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('A kimeneti könyvtár nem hozható létre: ' . $directory);
        }
    }
}
