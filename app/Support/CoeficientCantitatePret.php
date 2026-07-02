<?php

namespace App\Support;

class CoeficientCantitatePret
{
    public static function normalizeForSave(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return '1';
        }

        $raw = str_replace(',', '.', trim((string) $value));

        if ($raw === '' || ! is_numeric($raw)) {
            return '1';
        }

        $number = (float) $raw;

        if ($number > 1) {
            $number = $number / 100;
        }

        if ($number < 0) {
            $number = 0;
        }

        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.') ?: '0';
    }

    public static function toPercentForForm(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '100';
        }

        $number = (float) str_replace(',', '.', (string) $value);

        return rtrim(rtrim(number_format($number * 100, 4, '.', ''), '0'), '.') ?: '0';
    }

    public static function toMultiplier(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 1.0;
        }

        $number = (float) str_replace(',', '.', (string) $value);

        if ($number < 0) {
            return 0.0;
        }

        return $number;
    }
}
