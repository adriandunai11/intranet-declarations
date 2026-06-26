<?php

namespace App\Modules\Declarations\Services\Documents;

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

            $zip->addFromString($xmlFile, strtr($xml, $replacementMap));
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

        if ($commandTemplate === '') {
            throw new RuntimeException('A PDF generáláshoz még nincs beállítva DOCX-PDF konverter parancs.');
        }

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

            $map['${' . $normalizedKey . '}'] = htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        return $map;
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
