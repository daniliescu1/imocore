<?php

namespace App\Support;

class DecimalInput
{
    public static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(str_replace(' ', '', (string) $value));

        if ($value === '') {
            return null;
        }

        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            $value = str_replace(',', '', $value);
        } elseif ($hasComma) {
            $value = str_replace(',', '.', $value);
        }

        return $value;
    }
}
