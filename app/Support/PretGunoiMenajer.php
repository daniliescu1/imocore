<?php

namespace App\Support;

class PretGunoiMenajer
{
    public const DENUMIRE = 'Servicii Gunoi Menajer';

    public static function isGunoiMenajer(?string $denumire): bool
    {
        return self::normalizeDenumire($denumire) === self::normalizeDenumire(self::DENUMIRE);
    }

    public static function normalizePretOptional(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = str_replace(',', '.', trim((string) $value));

        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $number = (float) $raw;

        if ($number < 0) {
            return null;
        }

        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.') ?: '0';
    }

    public static function valoarePentruPersoane(float $numarPersoane, float $pretPrimaPersoana, ?float $pretSuplimentar): float
    {
        $numarPersoane = max(0, (int) $numarPersoane);

        if ($numarPersoane === 0) {
            return 0.0;
        }

        if ($pretSuplimentar === null) {
            return $numarPersoane * $pretPrimaPersoana;
        }

        return $pretPrimaPersoana + ($numarPersoane - 1) * $pretSuplimentar;
    }

    private static function normalizeDenumire(?string $denumire): string
    {
        $value = mb_strtolower(trim((string) $denumire));
        $value = str_replace(['ă', 'â', 'î', 'ș', 'ş', 'ț', 'ţ'], ['a', 'a', 'i', 's', 's', 't', 't'], $value);

        return (string) preg_replace('/\s+/u', '', $value);
    }
}
