<?php

namespace App\Modules\Declarations\Services\Documents;

use RuntimeException;

class DeclarationSubmissionPdfGenerator
{
    private const PAGE_WIDTH = 595;
    private const PAGE_HEIGHT = 842;
    private const MARGIN_LEFT = 50;
    private const MARGIN_RIGHT = 50;
    private const MARGIN_TOP_Y = 792;
    private const MARGIN_BOTTOM = 52;

    /**
     * @param array<string, mixed> $summary
     */
    public function generate(array $summary, string $outputPath): string
    {
        $directory = dirname($outputPath);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('A PDF kimeneti könyvtár nem hozható létre.');
        }

        $pages = $this->renderPages($summary);
        $pdf = $this->buildPdf($pages);

        if (file_put_contents($outputPath, $pdf) === false) {
            throw new RuntimeException('A PDF kimeneti fájl létrehozása sikertelen.');
        }

        return $outputPath;
    }

    /**
     * @param array<string, mixed> $summary
     * @return list<string>
     */
    private function renderPages(array $summary): array
    {
        $pages = [];
        $content = '';
        $y = self::MARGIN_TOP_Y;

        $newPage = function () use (&$pages, &$content, &$y): void {
            if (trim($content) !== '') {
                $pages[] = $content;
            }

            $content = '';
            $y = self::MARGIN_TOP_Y;
        };

        $ensureSpace = function (int $height) use (&$y, $newPage): void {
            if (($y - $height) < self::MARGIN_BOTTOM) {
                $newPage();
            }
        };

        $addText = function (string $text, int $size = 10, bool $bold = false) use (&$content, &$y, $ensureSpace): void {
            $lineHeight = max(11, $size + 4);

            if (trim($text) === '') {
                $ensureSpace($lineHeight);
                $y -= $lineHeight;
                return;
            }

            foreach ($this->wrapForWidth($text, $this->contentWidth(), $size) as $line) {
                $ensureSpace($lineHeight);
                $fontSize = $bold ? $size + 1 : $size;
                $content .= $this->textCommand(self::MARGIN_LEFT, $y, $fontSize, $line);
                $y -= $lineHeight;
            }
        };

        $addKeyValueRows = function (array $rows) use ($addText): void {
            foreach ($rows as $label => $value) {
                $text = trim((string) $label) . ': ' . (trim((string) $value) !== '' ? (string) $value : '-');
                $addText($text, 10);
            }
        };

        $addTable = function (array $table) use (&$content, &$y, $addText, $ensureSpace, $newPage): void {
            $columns = is_array($table['columns'] ?? null) ? array_values($table['columns']) : [];
            $rows = is_array($table['rows'] ?? null) ? array_values($table['rows']) : [];

            if ($columns === [] || $rows === []) {
                return;
            }

            $addText('', 6);
            $addText((string) ($table['title'] ?? 'Táblázat'), 11, true);

            $widths = $this->tableColumnWidths($columns);
            $headerPlan = $this->tableRowPlan($columns, $widths, 8);
            $renderHeader = function () use (&$content, &$y, $widths, $headerPlan): void {
                $content .= $this->tableRowCommand($headerPlan, $widths, $y, true);
                $y -= (int) $headerPlan['height'];
            };

            $ensureSpace((int) $headerPlan['height']);
            $renderHeader();

            foreach ($rows as $row) {
                $cells = is_array($row) ? array_values($row) : [];

                while (count($cells) < count($columns)) {
                    $cells[] = '-';
                }

                $cells = array_slice($cells, 0, count($columns));
                $rowPlan = $this->tableRowPlan($cells, $widths, 8);

                if (($y - (int) $rowPlan['height']) < self::MARGIN_BOTTOM) {
                    $newPage();
                    $addText((string) ($table['title'] ?? 'Táblázat') . ' - folytatás', 11, true);
                    $ensureSpace((int) $headerPlan['height']);
                    $renderHeader();
                }

                $content .= $this->tableRowCommand($rowPlan, $widths, $y, false);
                $y -= (int) $rowPlan['height'];
            }
        };

        $addText('Online nyilatkozat - beküldési összesítő', 16, true);
        $addText((string) ($summary['title'] ?? 'Nyilatkozat'), 13, true);
        $addText('A PDF az online felületen beküldött adatok alapján készült munkaügyi lefűzéshez.', 9);
        $addText('', 10);

        $meta = is_array($summary['meta'] ?? null) ? $summary['meta'] : [];

        if ($meta !== []) {
            $addText('Azonosító adatok', 12, true);
            $addKeyValueRows($meta);
            $addText('', 10);
        }

        $rows = is_array($summary['rows'] ?? null) ? $summary['rows'] : [];
        $tables = is_array($summary['tables'] ?? null) ? $summary['tables'] : [];

        $addText('Kitöltött adatok', 12, true);

        if ($rows === [] && $tables === []) {
            $addText('Nincs megjeleníthető kitöltött adat.', 10);
        } else {
            $addKeyValueRows($rows);

            foreach ($tables as $table) {
                if (is_array($table)) {
                    $addTable($table);
                }
            }
        }

        $addText('', 10);
        $addText('Beküldési nyom: a végleges beküldés időpontja és a csomag audit naplója az admin felületen ellenőrizhető.', 8);

        if (trim($content) !== '') {
            $pages[] = $content;
        }

        return $pages !== [] ? $pages : [$this->textCommand(self::MARGIN_LEFT, self::MARGIN_TOP_Y, 10, 'Nincs megjeleníthető adat.')];
    }

    /**
     * @param list<string> $pages
     */
    private function buildPdf(array $pages): string
    {
        $objects = [];
        $fontObjectNumber = 3 + (count($pages) * 2);
        $kids = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        foreach ($pages as $pageIndex => $content) {
            $pageObjectNumber = 3 + ($pageIndex * 2);
            $contentObjectNumber = $pageObjectNumber + 1;
            $kids[] = $pageObjectNumber . ' 0 R';

            $objects[$pageObjectNumber] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '
                . $fontObjectNumber . ' 0 R >> >> /Contents ' . $contentObjectNumber . ' 0 R >>';
            $objects[$contentObjectNumber] = $this->streamObject($content);
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($pages) . ' >>';

        foreach ($this->fontObjects($fontObjectNumber) as $objectNumber => $object) {
            $objects[$objectNumber] = $object;
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
        }

        $maxObjectNumber = max(array_keys($objects));
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxObjectNumber + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($number = 1; $number <= $maxObjectNumber; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . ($maxObjectNumber + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF\n";

        return $pdf;
    }

    /**
     * @return array<int, string>
     */
    private function fontObjects(int $fontObjectNumber): array
    {
        $fontPath = $this->fontPath();

        if ($fontPath === null) {
            return [
                $fontObjectNumber => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            ];
        }

        $descriptorObjectNumber = $fontObjectNumber + 1;
        $fontFileObjectNumber = $fontObjectNumber + 2;
        $fontData = file_get_contents($fontPath);

        if ($fontData === false) {
            return [
                $fontObjectNumber => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            ];
        }

        $fontName = $this->fontName($fontPath);
        $widths = implode(' ', array_fill(0, 224, '520'));

        return [
            $fontObjectNumber => '<< /Type /Font /Subtype /TrueType /BaseFont /' . $fontName
                . ' /FirstChar 32 /LastChar 255 /Widths [' . $widths . ']'
                . ' /Encoding << /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [213 /Odblacute 219 /Udblacute 245 /odblacute 251 /udblacute] >>'
                . ' /FontDescriptor ' . $descriptorObjectNumber . ' 0 R >>',
            $descriptorObjectNumber => '<< /Type /FontDescriptor /FontName /' . $fontName
                . ' /Flags 32 /FontBBox [-1000 -350 2200 1100] /ItalicAngle 0 /Ascent 950 /Descent -250 /CapHeight 700 /StemV 80'
                . ' /FontFile2 ' . $fontFileObjectNumber . ' 0 R >>',
            $fontFileObjectNumber => $this->streamObject($fontData),
        ];
    }

    private function textCommand(int $x, int $y, int $fontSize, string $text): string
    {
        return "BT\n/F1 " . max(7, min(18, $fontSize)) . " Tf\n1 0 0 1 " . $x . ' ' . $y . " Tm\n("
            . $this->escapePdfString($this->encodeText($text)) . ") Tj\nET\n";
    }

    /**
     * @param list<string> $columns
     * @return list<int>
     */
    private function tableColumnWidths(array $columns): array
    {
        $weights = [];

        foreach ($columns as $column) {
            $label = (string) $column;
            $weight = 1.0;

            if (str_contains($label, 'Adóazonosító')) {
                $weight = 1.3;
            } elseif (str_contains($label, 'Név')) {
                $weight = 1.25;
            } elseif (str_contains($label, 'dátum') || str_contains($label, 'időpont')) {
                $weight = 1.0;
            } elseif (str_contains($label, 'kód') || str_contains($label, 'jogcím')) {
                $weight = .95;
            }

            $weights[] = $weight;
        }

        $totalWeight = array_sum($weights) ?: 1;
        $availableWidth = $this->contentWidth();
        $widths = [];
        $used = 0;

        foreach ($weights as $index => $weight) {
            if ($index === count($weights) - 1) {
                $widths[] = $availableWidth - $used;
                continue;
            }

            $width = (int) floor($availableWidth * ($weight / $totalWeight));
            $widths[] = $width;
            $used += $width;
        }

        return $widths;
    }

    /**
     * @param list<mixed> $cells
     * @param list<int> $widths
     * @return array{height:int, lines:list<list<string>>, fontSize:int}
     */
    private function tableRowPlan(array $cells, array $widths, int $fontSize): array
    {
        $cellLines = [];
        $maxLines = 1;

        foreach ($cells as $index => $cell) {
            $width = max(32, (int) ($widths[$index] ?? 80) - 8);
            $lines = $this->wrapForWidth((string) $cell, $width, $fontSize);
            $cellLines[] = $lines;
            $maxLines = max($maxLines, count($lines));
        }

        return [
            'height' => max(24, ($maxLines * ($fontSize + 3)) + 10),
            'lines' => $cellLines,
            'fontSize' => $fontSize,
        ];
    }

    /**
     * @param array{height:int, lines:list<list<string>>, fontSize:int} $plan
     * @param list<int> $widths
     */
    private function tableRowCommand(array $plan, array $widths, int $topY, bool $header): string
    {
        $height = (int) $plan['height'];
        $bottomY = $topY - $height;
        $x = self::MARGIN_LEFT;
        $command = '';

        if ($header) {
            $command .= "0.94 0.98 0.94 rg\n" . self::MARGIN_LEFT . ' ' . $bottomY . ' ' . $this->contentWidth() . ' ' . $height . " re f\n0 g\n";
        }

        foreach ($widths as $index => $width) {
            $command .= $x . ' ' . $bottomY . ' ' . $width . ' ' . $height . " re S\n";
            $lineY = $topY - 14;
            $fontSize = $header ? 8 : (int) $plan['fontSize'];

            foreach (($plan['lines'][$index] ?? ['-']) as $line) {
                $command .= $this->textCommand($x + 4, $lineY, $fontSize, $line);
                $lineY -= $fontSize + 3;
            }

            $x += $width;
        }

        return $command;
    }

    /**
     * @return list<string>
     */
    private function wrapForWidth(string $text, int $width, int $fontSize): array
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

        if ($text === '') {
            return [''];
        }

        $limit = max(8, (int) floor($width / max(3.8, $fontSize * .55)));
        $words = preg_split('/\s+/u', $text) ?: [$text];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            if ($line === '') {
                $line = $word;
                continue;
            }

            if ($this->textLength($line . ' ' . $word) <= $limit) {
                $line .= ' ' . $word;
                continue;
            }

            $lines[] = $line;
            $line = $word;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        $result = [];

        foreach ($lines as $line) {
            if ($this->textLength($line) <= $limit) {
                $result[] = $line;
                continue;
            }

            $result = array_merge($result, $this->hardWrap($line, $limit));
        }

        return $result !== [] ? $result : [''];
    }

    /**
     * @return list<string>
     */
    private function hardWrap(string $text, int $limit): array
    {
        $chunks = [];

        while ($this->textLength($text) > $limit) {
            $chunks[] = $this->textSlice($text, 0, $limit);
            $text = $this->textSlice($text, $limit);
        }

        if ($text !== '') {
            $chunks[] = $text;
        }

        return $chunks;
    }

    private function textLength(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }

    private function textSlice(string $text, int $start, ?int $length = null): string
    {
        if (function_exists('mb_substr')) {
            return $length === null
                ? mb_substr($text, $start, null, 'UTF-8')
                : mb_substr($text, $start, $length, 'UTF-8');
        }

        return $length === null ? substr($text, $start) : substr($text, $start, $length);
    }

    private function contentWidth(): int
    {
        return self::PAGE_WIDTH - self::MARGIN_LEFT - self::MARGIN_RIGHT;
    }

    private function streamObject(string $data): string
    {
        return '<< /Length ' . strlen($data) . " >>\nstream\n" . $data . "\nendstream";
    }

    private function encodeText(string $text): string
    {
        $encoded = @iconv('UTF-8', 'CP1250//TRANSLIT//IGNORE', $text);

        if ($encoded !== false) {
            return $encoded;
        }

        $encoded = @iconv('UTF-8', 'Windows-1250//TRANSLIT//IGNORE', $text);

        if ($encoded !== false) {
            return $encoded;
        }

        return $this->ascii($text);
    }

    private function escapePdfString(string $text): string
    {
        $escaped = '';
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            $ord = ord($char);

            if ($char === '\\' || $char === '(' || $char === ')') {
                $escaped .= '\\' . $char;
                continue;
            }

            if ($ord < 32 || $ord > 126) {
                $escaped .= sprintf('\\%03o', $ord);
                continue;
            }

            $escaped .= $char;
        }

        return $escaped;
    }

    private function fontPath(): ?string
    {
        $paths = array_filter([
            getenv('DECLARATION_PDF_FONT') ?: null,
            'C:\\Windows\\Fonts\\segoeui.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf',
        ]);

        foreach ($paths as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function fontName(string $fontPath): string
    {
        $name = pathinfo($fontPath, PATHINFO_FILENAME);
        $name = preg_replace('/[^A-Za-z0-9]+/', '', $name) ?: 'DeclarationPdfFont';

        return $name;
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
