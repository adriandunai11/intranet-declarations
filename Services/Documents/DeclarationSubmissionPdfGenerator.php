<?php

namespace App\Modules\Declarations\Services\Documents;

use RuntimeException;

class DeclarationSubmissionPdfGenerator
{
    /**
     * @param array<string, mixed> $summary
     */
    public function generate(array $summary, string $outputPath): string
    {
        $directory = dirname($outputPath);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('A PDF kimeneti könyvtár nem hozható létre.');
        }

        $pages = $this->paginate($this->lines($summary));
        $pdf = $this->buildPdf($pages);

        if (file_put_contents($outputPath, $pdf) === false) {
            throw new RuntimeException('A PDF kimeneti fájl létrehozása sikertelen.');
        }

        return $outputPath;
    }

    /**
     * @param array<string, mixed> $summary
     * @return list<array{text:string,size:int,bold?:bool}>
     */
    private function lines(array $summary): array
    {
        $lines = [
            ['text' => 'Online nyilatkozat - bekuldesi osszesito', 'size' => 16, 'bold' => true],
            ['text' => (string) ($summary['title'] ?? 'Nyilatkozat'), 'size' => 13, 'bold' => true],
            ['text' => 'A dokumentum az online feluleten bekuldott adatok alapjan keszult munkaügyi lefuzeshez.', 'size' => 9],
            ['text' => '', 'size' => 10],
        ];

        $meta = is_array($summary['meta'] ?? null) ? $summary['meta'] : [];

        if ($meta !== []) {
            $lines[] = ['text' => 'Azonosito adatok', 'size' => 12, 'bold' => true];

            foreach ($meta as $label => $value) {
                foreach ($this->wrap($this->keyValue((string) $label, (string) $value), 96) as $wrapped) {
                    $lines[] = ['text' => $wrapped, 'size' => 10];
                }
            }

            $lines[] = ['text' => '', 'size' => 10];
        }

        $rows = is_array($summary['rows'] ?? null) ? $summary['rows'] : [];
        $lines[] = ['text' => 'Kitoltott adatok', 'size' => 12, 'bold' => true];

        if ($rows === []) {
            $lines[] = ['text' => 'Nincs megjelenitheto kitoltott adat.', 'size' => 10];
        } else {
            foreach ($rows as $label => $value) {
                foreach ($this->wrap($this->keyValue((string) $label, (string) $value), 96) as $wrapped) {
                    $lines[] = ['text' => $wrapped, 'size' => 10];
                }
            }
        }

        $lines[] = ['text' => '', 'size' => 10];
        $lines[] = ['text' => 'Bekuldesi nyom: a vegleges bekuldes idopontja es a csomag audit naploja az admin feluleten ellenorizheto.', 'size' => 8];

        return $lines;
    }

    private function keyValue(string $label, string $value): string
    {
        $value = trim($value);

        return trim($label) . ': ' . ($value !== '' ? $value : '-');
    }

    /**
     * @param list<array{text:string,size:int,bold?:bool}> $lines
     * @return list<list<array{text:string,size:int,bold?:bool}>>
     */
    private function paginate(array $lines): array
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

        return $pages !== [] ? $pages : [[['text' => 'Nincs megjelenitheto adat.', 'size' => 10]]];
    }

    /**
     * @param list<list<array{text:string,size:int,bold?:bool}>> $pages
     */
    private function buildPdf(array $pages): string
    {
        $objects = [];
        $fontObjectNumber = 3 + (count($pages) * 2);
        $kids = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        foreach ($pages as $pageIndex => $lines) {
            $pageObjectNumber = 3 + ($pageIndex * 2);
            $contentObjectNumber = $pageObjectNumber + 1;
            $kids[] = $pageObjectNumber . ' 0 R';
            $content = $this->pageContent($lines);

            $objects[$pageObjectNumber] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '
                . $fontObjectNumber . ' 0 R >> >> /Contents ' . $contentObjectNumber . ' 0 R >>';
            $objects[$contentObjectNumber] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
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

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF\n";

        return $pdf;
    }

    /**
     * @param list<array{text:string,size:int,bold?:bool}> $lines
     */
    private function pageContent(array $lines): string
    {
        $content = "BT\n50 792 Td\n";
        $currentY = 792;

        foreach ($lines as $line) {
            $size = max(7, min(18, (int) $line['size']));
            $leading = max(11, $size + 4);
            $fontSize = !empty($line['bold']) ? $size + 1 : $size;
            $content .= '/F1 ' . $fontSize . " Tf\n";
            $content .= '(' . $this->escape($this->ascii((string) $line['text'])) . ") Tj\n";
            $content .= '0 -' . $leading . " Td\n";
            $currentY -= $leading;

            if ($currentY < 52) {
                break;
            }
        }

        return $content . 'ET';
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, int $limit): array
    {
        $text = preg_replace('/\s+/', ' ', trim($this->ascii($text))) ?? trim($text);

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

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function ascii(string $text): string
    {
        $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ö' => 'O', 'Ő' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ű' => 'U',
        ];

        return strtr($text, $map);
    }
}
