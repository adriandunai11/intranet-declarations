<?php

namespace App\Modules\Declarations\Services\Validation;

class HungarianIdentifierValidator
{
    public function isValidTaxNumber(string $value): bool
    {
        $value = preg_replace('/\D+/', '', $value) ?? '';

        if (!preg_match('/^8\d{9}$/', $value)) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $value[$i]) * ($i + 1);
        }

        return $sum % 11 === (int) $value[9];
    }

    public function isValidTajNumber(string $value): bool
    {
        $value = preg_replace('/\D+/', '', $value) ?? '';

        if (!preg_match('/^\d{9}$/', $value)) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 8; $i++) {
            $sum += ((int) $value[$i]) * (($i % 2 === 0) ? 3 : 7);
        }

        return $sum % 10 === (int) $value[8];
    }

    public function isValidHungarianBankAccountNumber(string $value): bool
    {
        $value = preg_replace('/\D+/', '', $value) ?? '';

        if (!preg_match('/^\d{16}(\d{8})?$/', $value)) {
            return false;
        }

        $weights = [9, 7, 3, 1, 9, 7, 3, 1];

        foreach (str_split($value, 8) as $block) {
            $sum = 0;

            for ($i = 0; $i < 8; $i++) {
                $sum += ((int) $block[$i]) * $weights[$i];
            }

            if ($sum % 10 !== 0) {
                return false;
            }
        }

        return true;
    }
}
