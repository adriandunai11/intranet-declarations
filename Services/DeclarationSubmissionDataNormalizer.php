<?php

namespace App\Modules\Declarations\Services;

class DeclarationSubmissionDataNormalizer
{
    /**
     * @return array<string, mixed>
     */
    public function normalize($data): array
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);

            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            $data = $decoded;
        }

        $data = $this->normalizeValue($data);

        return is_array($data) ? $data : [];
    }

    private function normalizeValue($value)
    {
        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeValue($item);
        }

        return $value;
    }
}
