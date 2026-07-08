<?php

namespace App\Modules\Declarations\Services\Documents;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class DeclarationDocumentGenerator
{
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * DOCX sablon kitöltése ${kulcs} formájú helyőrzőkkel.
     *
     * @param array<string, scalar|null> $placeholders
     */
    public function generateDocx(string $templatePath, array $placeholders, string $outputPath, array $documentSummary = []): string
    {
        if (!is_file($templatePath)) {
            throw new RuntimeException('A DOCX sablon nem található: ' . $templatePath);
        }

        $this->ensureDirectory(dirname($outputPath));

        $generatedWithTemplateProcessor = false;

        if ($this->canUseTemplateProcessor()) {
            try {
                $this->generateDocxWithTemplateProcessor($templatePath, $placeholders, $outputPath);
                $generatedWithTemplateProcessor = true;
            } catch (\Throwable $e) {
                $this->logError('DOCX TemplateProcessor generation failed, falling back to OOXML replacement: ' . $e->getMessage());
            }
        }

        if (!$generatedWithTemplateProcessor) {
            $this->generateDocxWithOoxmlReplacement($templatePath, $placeholders, $outputPath);
        }

        if ($this->hasDocumentSummary($documentSummary)) {
            $this->appendDocumentSummaryToDocx($outputPath, $documentSummary);
        }

        return $outputPath;
    }

    /**
     * @param array<string, scalar|null> $placeholders
     */
    private function generateDocxWithTemplateProcessor(string $templatePath, array $placeholders, string $outputPath): void
    {
        $templateProcessorClass = '\\PhpOffice\\PhpWord\\TemplateProcessor';
        $templateProcessor = new $templateProcessorClass($templatePath);

        foreach ($placeholders as $key => $value) {
            $key = trim((string) $key);

            if ($key === '') {
                continue;
            }

            $templateProcessor->setValue($key, (string) ($value ?? ''));
        }

        $templateProcessor->saveAs($outputPath);
    }

    /**
     * @param array<string, scalar|null> $placeholders
     */
    private function generateDocxWithOoxmlReplacement(string $templatePath, array $placeholders, string $outputPath): void
    {
        if (!copy($templatePath, $outputPath)) {
            throw new RuntimeException('A DOCX kimeneti fájl létrehozása sikertelen.');
        }

        $replacementMap = $this->replacementMap($placeholders);

        if (!class_exists(ZipArchive::class)) {
            $this->generateDocxWithPharDataReplacement($outputPath, $replacementMap);

            return;
        }

        $zip = new ZipArchive();

        if ($zip->open($outputPath) !== true) {
            throw new RuntimeException('A DOCX fájl nem nyitható meg szerkesztésre.');
        }

        foreach ($this->docxXmlFiles($zip) as $xmlFile) {
            $xml = $zip->getFromName($xmlFile);

            if ($xml === false) {
                continue;
            }

            $zip->addFromString($xmlFile, $this->replacePlaceholdersInXml($xml, $replacementMap));
        }

        $zip->close();
    }

    /**
     * @param array<string, string> $replacementMap
     */
    private function generateDocxWithPharDataReplacement(string $outputPath, array $replacementMap): void
    {
        if (!class_exists(\PharData::class)) {
            throw new RuntimeException('A DOCX generáláshoz PHP ZipArchive vagy PharData támogatás szükséges.');
        }

        try {
            $archive = new \PharData($outputPath);
        } catch (\Throwable $e) {
            throw new RuntimeException('A DOCX fájl nem nyitható meg szerkesztésre.', 0, $e);
        }

        foreach ($this->docxXmlFilesFromPharData($archive) as $xmlFile) {
            if (!isset($archive[$xmlFile])) {
                continue;
            }

            $xml = $archive[$xmlFile]->getContent();

            if (!is_string($xml)) {
                continue;
            }

            $archive[$xmlFile] = $this->replacePlaceholdersInXml($xml, $replacementMap);
        }
    }

    /**
     * @param array<string, scalar|null> $placeholders
     */
    public function generatePdf(string $templatePath, array $placeholders, string $outputPath, array $documentSummary = []): string
    {
        $temporaryDocx = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . uniqid('declaration_', true)
            . '.docx';

        try {
            $this->generateDocx($templatePath, $placeholders, $temporaryDocx, $documentSummary);

            return $this->convertDocxToPdf($temporaryDocx, $outputPath);
        } catch (\Throwable $e) {
            if (!$this->hasDocumentSummary($documentSummary)) {
                throw $e;
            }

            $this->logError('DOCX based PDF generation failed, falling back to summary PDF: ' . $e->getMessage());

            return $this->generateSummaryPdf($documentSummary, $outputPath);
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
            $this->logError('DOCX-PDF conversion failed: ' . implode("\n", $output));

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
            $this->logError('LibreOffice DOCX-PDF conversion failed: ' . implode("\n", $output));

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

    /**
     * @param array<string, mixed> $summary
     */
    public function generateSummaryPdf(array $summary, string $outputPath): string
    {
        $this->ensureDirectory(dirname($outputPath));

        $lines = $this->summaryPdfLines($summary);
        $pages = $this->paginatePdfLines($lines);
        $pdf = $this->buildSimplePdf($pages);

        if (file_put_contents($outputPath, $pdf) === false) {
            throw new RuntimeException('A PDF kimeneti fájl létrehozása sikertelen.');
        }

        return $outputPath;
    }

    /**
     * @param array<string, mixed> $summary
     * @return list<array{text:string,size:int,bold?:bool}>
     */
    private function summaryPdfLines(array $summary): array
    {
        $lines = [
            [
                'text' => (string) ($summary['subtitle'] ?? 'Online kitoltesi osszesito'),
                'size' => 16,
                'bold' => true,
            ],
            [
                'text' => (string) ($summary['title'] ?? 'Nyilatkozat'),
                'size' => 13,
                'bold' => true,
            ],
            [
                'text' => 'PDF osszesito fallback: a NAV sablonos PDF-hez LibreOffice/soffice vagy docxToPdfCommand szukseges.',
                'size' => 8,
            ],
            [
                'text' => '',
                'size' => 10,
            ],
        ];

        $note = trim((string) ($summary['note'] ?? ''));

        if ($note !== '') {
            foreach ($this->wrapPdfText($note, 92) as $wrappedLine) {
                $lines[] = [
                    'text' => $wrappedLine,
                    'size' => 9,
                ];
            }

            $lines[] = [
                'text' => '',
                'size' => 10,
            ];
        }

        $meta = is_array($summary['meta'] ?? null) ? $summary['meta'] : [];

        if ($meta !== []) {
            $lines[] = [
                'text' => 'Azonosito adatok',
                'size' => 12,
                'bold' => true,
            ];

            foreach ($meta as $label => $value) {
                foreach ($this->wrapPdfText($this->keyValuePdfLine((string) $label, (string) $value), 96) as $wrappedLine) {
                    $lines[] = [
                        'text' => $wrappedLine,
                        'size' => 10,
                    ];
                }
            }

            $lines[] = [
                'text' => '',
                'size' => 10,
            ];
        }

        $rows = is_array($summary['rows'] ?? null) ? $summary['rows'] : [];

        $lines[] = [
            'text' => 'Kitoltott adatok',
            'size' => 12,
            'bold' => true,
        ];

        if ($rows === []) {
            $lines[] = [
                'text' => 'Nincs megjelenitheto kitoltott adat.',
                'size' => 10,
            ];
        } else {
            foreach ($rows as $label => $value) {
                foreach ($this->wrapPdfText($this->keyValuePdfLine((string) $label, (string) $value), 96) as $wrappedLine) {
                    $lines[] = [
                        'text' => $wrappedLine,
                        'size' => 10,
                    ];
                }
            }
        }

        return $lines;
    }

    private function keyValuePdfLine(string $label, string $value): string
    {
        $value = trim($value);

        return trim($label) . ': ' . ($value !== '' ? $value : '-');
    }

    /**
     * @param list<array{text:string,size:int,bold?:bool}> $lines
     * @return list<list<array{text:string,size:int,bold?:bool}>>
     */
    private function paginatePdfLines(array $lines): array
    {
        $pages = [];
        $page = [];
        $y = 792;

        foreach ($lines as $line) {
            $height = max(11, (int) $line['size'] + 4);

            if ($page !== [] && ($y - $height) < 52) {
                $pages[] = $page;
                $page = [];
                $y = 792;
            }

            $page[] = $line;
            $y -= $height;
        }

        if ($page !== []) {
            $pages[] = $page;
        }

        return $pages !== [] ? $pages : [[[
            'text' => 'Nincs megjelenitheto adat.',
            'size' => 10,
        ]]];
    }

    /**
     * @param list<list<array{text:string,size:int,bold?:bool}>> $pages
     */
    private function buildSimplePdf(array $pages): string
    {
        $objects = [];
        $fontObjectNumber = 3 + (count($pages) * 2);
        $kids = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        foreach ($pages as $pageIndex => $lines) {
            $pageObjectNumber = 3 + ($pageIndex * 2);
            $contentObjectNumber = $pageObjectNumber + 1;
            $kids[] = $pageObjectNumber . ' 0 R';

            $content = $this->pdfPageContent($lines);
            $objects[$pageObjectNumber] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '
                . $fontObjectNumber
                . ' 0 R >> >> /Contents '
                . $contentObjectNumber
                . ' 0 R >>';
            $objects[$contentObjectNumber] = '<< /Length '
                . strlen($content)
                . " >>\nstream\n"
                . $content
                . "\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($pages) . ' >>';
        $objects[$fontObjectNumber] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($number = 1; $number <= count($objects); $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number] ?? 0);
        }

        $pdf .= "trailer\n<< /Size "
            . (count($objects) + 1)
            . " /Root 1 0 R >>\nstartxref\n"
            . $xrefOffset
            . "\n%%EOF\n";

        return $pdf;
    }

    /**
     * @param list<array{text:string,size:int,bold?:bool}> $lines
     */
    private function pdfPageContent(array $lines): string
    {
        $content = "BT\n50 792 Td\n";
        $currentY = 792;

        foreach ($lines as $line) {
            $size = max(7, min(18, (int) $line['size']));
            $leading = max(11, $size + 4);
            $fontSize = !empty($line['bold']) ? $size + 1 : $size;
            $safeText = $this->escapePdfString($this->transliterateForPdf((string) $line['text']));

            $content .= '/F1 ' . $fontSize . " Tf\n";
            $content .= '(' . $safeText . ") Tj\n";
            $content .= '0 -' . $leading . " Td\n";
            $currentY -= $leading;

            if ($currentY < 52) {
                break;
            }
        }

        return $content . "ET";
    }

    /**
     * @return list<string>
     */
    private function wrapPdfText(string $text, int $limit): array
    {
        $text = $this->transliterateForPdf($text);
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);

        if ($text === '') {
            return [''];
        }

        $lines = [];

        while (strlen($text) > $limit) {
            $breakAt = strrpos(substr($text, 0, $limit + 1), ' ');

            if ($breakAt === false || $breakAt < 20) {
                $breakAt = $limit;
            }

            $lines[] = trim(substr($text, 0, $breakAt));
            $text = trim(substr($text, $breakAt));
        }

        if ($text !== '') {
            $lines[] = $text;
        }

        return $lines;
    }

    private function transliterateForPdf(string $text): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        if ($converted === false) {
            $converted = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
        }

        return str_replace(["\r", "\n", "\t"], ' ', $converted);
    }

    private function escapePdfString(string $text): string
    {
        return strtr($text, [
            '\\' => '\\\\',
            '(' => '\\(',
            ')' => '\\)',
        ]);
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

    private function canUseTemplateProcessor(): bool
    {
        return class_exists('\\PhpOffice\\PhpWord\\TemplateProcessor');
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function appendDocumentSummaryToDocx(string $docxPath, array $summary): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->appendDocumentSummaryToDocxWithPharData($docxPath, $summary);

            return;
        }

        $zip = new ZipArchive();

        if ($zip->open($docxPath) !== true) {
            throw new RuntimeException('A DOCX fájl nem nyitható meg az összesítő beszúrásához.');
        }

        $xml = $zip->getFromName('word/document.xml');

        if (!is_string($xml)) {
            $zip->close();

            throw new RuntimeException('A DOCX dokumentumtörzs nem található az összesítő beszúrásához.');
        }

        $zip->addFromString('word/document.xml', $this->appendDocumentSummary($xml, $summary));
        $zip->close();
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function appendDocumentSummaryToDocxWithPharData(string $docxPath, array $summary): void
    {
        if (!class_exists(\PharData::class)) {
            throw new RuntimeException('A DOCX összesítő beszúrásához PHP ZipArchive vagy PharData támogatás szükséges.');
        }

        try {
            $archive = new \PharData($docxPath);
        } catch (\Throwable $e) {
            throw new RuntimeException('A DOCX fájl nem nyitható meg az összesítő beszúrásához.', 0, $e);
        }

        if (!isset($archive['word/document.xml'])) {
            throw new RuntimeException('A DOCX dokumentumtörzs nem található az összesítő beszúrásához.');
        }

        $xml = $archive['word/document.xml']->getContent();

        if (!is_string($xml)) {
            throw new RuntimeException('A DOCX dokumentumtörzs nem olvasható az összesítő beszúrásához.');
        }

        $archive['word/document.xml'] = $this->appendDocumentSummary($xml, $summary);
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function hasDocumentSummary(array $summary): bool
    {
        return !empty($summary['meta']) || !empty($summary['rows']);
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function appendDocumentSummary(string $xml, array $summary): string
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $xml;
        }

        $body = $document->getElementsByTagNameNS(self::WORD_NS, 'body')->item(0);

        if (!$body instanceof DOMElement) {
            return $xml;
        }

        $insertBefore = null;

        foreach ($body->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'sectPr') {
                $insertBefore = $child;
                break;
            }
        }

        $this->insertParagraph($document, $body, $insertBefore, '', ['page_break' => true]);
        $this->insertParagraph($document, $body, $insertBefore, (string) ($summary['subtitle'] ?? 'Online kitöltési összesítő'), [
            'bold' => true,
            'size' => 30,
            'after' => 120,
        ]);
        $this->insertParagraph($document, $body, $insertBefore, (string) ($summary['title'] ?? 'Nyilatkozat'), [
            'bold' => true,
            'size' => 24,
            'after' => 180,
        ]);

        $note = trim((string) ($summary['note'] ?? ''));

        if ($note !== '') {
            $this->insertParagraph($document, $body, $insertBefore, $note, [
                'size' => 20,
                'after' => 180,
            ]);
        }

        $meta = is_array($summary['meta'] ?? null) ? $summary['meta'] : [];

        if ($meta !== []) {
            $this->insertParagraph($document, $body, $insertBefore, 'Azonosító adatok', [
                'bold' => true,
                'size' => 22,
                'before' => 80,
                'after' => 80,
            ]);

            foreach ($meta as $label => $value) {
                $this->insertKeyValueParagraph($document, $body, $insertBefore, (string) $label, (string) $value);
            }
        }

        $rows = is_array($summary['rows'] ?? null) ? $summary['rows'] : [];

        $this->insertParagraph($document, $body, $insertBefore, 'Kitöltött adatok', [
            'bold' => true,
            'size' => 22,
            'before' => 220,
            'after' => 80,
        ]);

        if ($rows === []) {
            $this->insertParagraph($document, $body, $insertBefore, 'Nincs megjeleníthető kitöltött adat.', [
                'size' => 20,
            ]);
        } else {
            foreach ($rows as $label => $value) {
                $this->insertKeyValueParagraph($document, $body, $insertBefore, (string) $label, (string) $value);
            }
        }

        return $document->saveXML() ?: $xml;
    }

    private function insertKeyValueParagraph(DOMDocument $document, DOMElement $body, $insertBefore, string $label, string $value): void
    {
        $paragraph = $this->paragraph($document, [
            'after' => 80,
        ]);

        $paragraph->appendChild($this->run($document, trim($label) . ': ', [
            'bold' => true,
            'size' => 20,
        ]));
        $paragraph->appendChild($this->run($document, $value !== '' ? $value : '-', [
            'size' => 20,
        ]));

        $body->insertBefore($paragraph, $insertBefore);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function insertParagraph(DOMDocument $document, DOMElement $body, $insertBefore, string $text, array $options = []): void
    {
        $paragraph = $this->paragraph($document, $options);

        if (!empty($options['page_break'])) {
            $run = $document->createElementNS(self::WORD_NS, 'w:r');
            $break = $document->createElementNS(self::WORD_NS, 'w:br');
            $break->setAttribute('w:type', 'page');
            $run->appendChild($break);
            $paragraph->appendChild($run);
        }

        if ($text !== '') {
            $paragraph->appendChild($this->run($document, $text, $options));
        }

        $body->insertBefore($paragraph, $insertBefore);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function paragraph(DOMDocument $document, array $options = []): DOMElement
    {
        $paragraph = $document->createElementNS(self::WORD_NS, 'w:p');
        $properties = $document->createElementNS(self::WORD_NS, 'w:pPr');
        $spacing = $document->createElementNS(self::WORD_NS, 'w:spacing');
        $spacing->setAttribute('w:before', (string) (int) ($options['before'] ?? 0));
        $spacing->setAttribute('w:after', (string) (int) ($options['after'] ?? 0));
        $properties->appendChild($spacing);
        $paragraph->appendChild($properties);

        return $paragraph;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function run(DOMDocument $document, string $text, array $options = []): DOMElement
    {
        $run = $document->createElementNS(self::WORD_NS, 'w:r');
        $properties = $document->createElementNS(self::WORD_NS, 'w:rPr');
        $size = (int) ($options['size'] ?? 20);

        if (!empty($options['bold'])) {
            $properties->appendChild($document->createElementNS(self::WORD_NS, 'w:b'));
        }

        $fontSize = $document->createElementNS(self::WORD_NS, 'w:sz');
        $fontSize->setAttribute('w:val', (string) $size);
        $properties->appendChild($fontSize);

        $complexFontSize = $document->createElementNS(self::WORD_NS, 'w:szCs');
        $complexFontSize->setAttribute('w:val', (string) $size);
        $properties->appendChild($complexFontSize);

        $run->appendChild($properties);

        $textNode = $document->createElementNS(self::WORD_NS, 'w:t');
        $textNode->setAttribute('xml:space', 'preserve');
        $textNode->appendChild($document->createTextNode($text));
        $run->appendChild($textNode);

        return $run;
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

            if ($this->isDocxXmlContentFile($name)) {
                $files[] = $name;
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function docxXmlFilesFromPharData(\PharData $archive): array
    {
        $files = [];

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

            if ($this->isDocxXmlContentFile($name)) {
                $files[] = $name;
            }
        }

        return array_values(array_unique($files));
    }

    private function isDocxXmlContentFile(string $name): bool
    {
        return $name === 'word/document.xml'
            || (bool) preg_match('/^word\/(header|footer)\d*\.xml$/', $name)
            || in_array($name, ['word/footnotes.xml', 'word/endnotes.xml'], true);
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

    private function logError(string $message): void
    {
        if (function_exists('log_message')) {
            log_message('error', $message);
        }
    }
}
