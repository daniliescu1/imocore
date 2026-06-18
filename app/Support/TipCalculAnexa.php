<?php

namespace App\Support;

class TipCalculAnexa
{
    public static function normalize(?string $tipCalcul): string
    {
        $tipCalcul = trim((string) $tipCalcul);
        $normalized = str_replace([' ', '*', '×', '_', '-'], '', strtolower($tipCalcul));

        if (str_starts_with($normalized, 'mp') && str_contains($normalized, 'coeficient')) {
            return 'mp_coeficient';
        }

        if ($normalized === 'contor') {
            return 'contor';
        }

        if (in_array($normalized, ['mp', 'pemp'], true)) {
            return 'mp';
        }

        if ($normalized === 'persoane') {
            return 'persoane';
        }

        return $tipCalcul ?: 'manual';
    }

    public static function isContor(?string $tipCalcul): bool
    {
        return self::normalize($tipCalcul) === 'contor';
    }

    public static function applyLiniiContorScope($query)
    {
        return $query
            ->where(function ($query): void {
                $query->whereRaw('lower(trim(tip_calcul)) = ?', ['contor']);
            })
            ->where(function ($query): void {
                $query->whereNull('tip_linie')
                    ->orWhere('tip_linie', '!=', 'header');
            })
            ->where('activ', true);
    }
}
