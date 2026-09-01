<?php

namespace App\Modules\Declarations\Services\Validation;

use DateTimeImmutable;

class HungarianIdentifierValidator
{
    private const HUNGARIAN_BANK_PREFIXES = [
        '101' => 'Magyar Nemzeti Bank',
        '104' => 'K&H Bank',
        '107' => 'CIB Bank',
        '109' => 'UniCredit Bank',
        '116' => 'Erste Bank',
        '117' => 'OTP Bank',
        '120' => 'Raiffeisen Bank',
        '121' => 'Gránit Bank',
        '162' => 'MagNet Bank',
    ];

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

        return $sum % 11 === (int) $value[9]
            && $this->birthDateFromTaxNumber($value) !== null;
    }

    public function birthDateFromTaxNumber(string $value): ?string
    {
        $value = preg_replace('/\D+/', '', $value) ?? '';

        if (!preg_match('/^8\d{9}$/', $value)) {
            return null;
        }

        $daysSinceEpoch = (int) substr($value, 1, 5);
        $birthDate = (new DateTimeImmutable('1867-01-01'))->modify('+' . $daysSinceEpoch . ' days');

        if ($birthDate > new DateTimeImmutable('today')) {
            return null;
        }

        return $birthDate->format('Y-m-d');
    }

    public function isValidCompanyTaxNumber(string $value): bool
    {
        $value = preg_replace('/\D+/', '', $value) ?? '';

        if (!in_array(strlen($value), [8, 11], true)) {
            return false;
        }

        if (!$this->isValidCvdBlock(substr($value, 0, 8))) {
            return false;
        }

        if (strlen($value) === 8) {
            return true;
        }

        $vatCode = (int) $value[8];

        return $vatCode >= 1 && $vatCode <= 5;
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

        return $this->areValidCvdBlocks($value);
    }

    public function bankNameForHungarianBankAccountNumber(string $value): ?string
    {
        $value = preg_replace('/\D+/', '', $value) ?? '';
        $prefix = substr($value, 0, 3);

        return self::HUNGARIAN_BANK_PREFIXES[$prefix] ?? null;
    }

    private function areValidCvdBlocks(string $value): bool
    {
        foreach (str_split($value, 8) as $block) {
            if (!$this->isValidCvdBlock($block)) {
                return false;
            }
        }

        return true;
    }

    private function isValidCvdBlock(string $block): bool
    {
        if (!preg_match('/^\d{8}$/', $block)) {
            return false;
        }

        $weights = [9, 7, 3, 1, 9, 7, 3, 1];
        $sum = 0;

        for ($i = 0; $i < 8; $i++) {
            $sum += ((int) $block[$i]) * $weights[$i];
        }

        return $sum % 10 === 0;
    }
}
