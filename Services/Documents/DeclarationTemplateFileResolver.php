<?php

namespace App\Modules\Declarations\Services\Documents;

class DeclarationTemplateFileResolver
{
    public function resolveForItem(object $item): ?string
    {
        $snapshotFile = trim((string) ($item->template_file_snapshot ?? ''));

        if ($snapshotFile !== '') {
            $snapshotPath = $this->pathForFile($snapshotFile);

            if ($snapshotPath !== null && is_file($snapshotPath)) {
                return realpath($snapshotPath) ?: $snapshotPath;
            }

            if (!$this->isLegacyCodeBasedSnapshot($item, $snapshotFile)) {
                return null;
            }
        }

        foreach ($this->candidatePathsForItem($item) as $path) {
            if (is_file($path)) {
                return realpath($path) ?: $path;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function candidatePathsForItem(object $item): array
    {
        $paths = [];

        foreach ($this->candidateFilesForItem($item) as $file) {
            $path = $this->pathForFile($file);

            if ($path !== null && !in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    public function candidateFilesForItem(object $item): array
    {
        $files = [];

        foreach (['template_file_snapshot', 'template_file', 'current_template_file'] as $property) {
            $value = trim((string) ($item->{$property} ?? ''));

            if ($value !== '' && !in_array($value, $files, true)) {
                $files[] = $value;
            }
        }

        foreach (['template_code', 'template_code_snapshot', 'current_template_code'] as $property) {
            $code = trim((string) ($item->{$property} ?? ''));

            if ($code === '') {
                continue;
            }

            $file = $code . '.docx';

            if (!in_array($file, $files, true)) {
                $files[] = $file;
            }
        }

        return $files;
    }

    public function firstCandidateLabel(object $item): string
    {
        $files = $this->candidateFilesForItem($item);

        return $files[0] ?? '';
    }

    private function pathForFile(string $file): ?string
    {
        $file = trim($file);

        if ($file === '' || str_contains($file, "\0")) {
            return null;
        }

        $config = config(\App\Modules\Declarations\Config\Declarations::class);
        $basePath = rtrim((string) $config->documentTemplatePath, DIRECTORY_SEPARATOR);
        $normalizedFile = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);

        if ($this->isAbsolutePath($normalizedFile)) {
            return $normalizedFile;
        }

        $parts = array_values(array_filter(explode(DIRECTORY_SEPARATOR, $normalizedFile), static fn(string $part): bool => $part !== ''));

        if (in_array('..', $parts, true)) {
            return null;
        }

        return $basePath . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || (bool) preg_match('/^[A-Za-z]:\\\\/', $path);
    }

    private function isLegacyCodeBasedSnapshot(object $item, string $snapshotFile): bool
    {
        foreach (['template_code_snapshot', 'template_code', 'current_template_code'] as $property) {
            $code = trim((string) ($item->{$property} ?? ''));

            if ($code !== '' && $snapshotFile === $code . '.docx') {
                return true;
            }
        }

        return false;
    }
}
