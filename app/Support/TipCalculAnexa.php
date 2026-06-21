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

        if (str_contains($normalized, 'contor') && str_contains($normalized, 'configurabil')) {
            return 'contor_configurabil';
        }

        if ($normalized === 'contor') {
            return 'contor';
        }

        if ($normalized === 'pausal') {
            return 'pausal';
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

    public static function isPausal(?string $tipCalcul): bool
    {
        return self::normalize($tipCalcul) === 'pausal';
    }

    public static function isContorConfigurabil(?string $tipCalcul): bool
    {
        return self::normalize($tipCalcul) === 'contor_configurabil';
    }

    public static function isCitire(?string $tipCalcul): bool
    {
        return self::isContor($tipCalcul)
            || self::isPausal($tipCalcul)
            || self::isContorConfigurabil($tipCalcul);
    }

    public static function applyLiniiContorScope($query)
    {
        return $query
            ->where(function ($query): void {
                $query->whereRaw('lower(trim(tip_calcul)) = ?', ['contor'])
                    ->orWhereRaw('lower(trim(tip_calcul)) = ?', ['pausal'])
                    ->orWhereRaw('lower(trim(tip_calcul)) = ?', ['contor configurabil']);
            })
            ->where(function ($query): void {
                $query->whereNull('tip_linie')
                    ->orWhere('tip_linie', '!=', 'header');
            })
            ->where('activ', true);
    }

    public static function applyLiniiContorSpatiuScope($query)
    {
        return $query
            ->where(function ($query): void {
                $query->whereRaw('lower(trim(tip_calcul)) = ?', ['contor'])
                    ->orWhereRaw('lower(trim(tip_calcul)) = ?', ['pausal']);
            })
            ->where(function ($query): void {
                $query->whereNull('tip_linie')
                    ->orWhere('tip_linie', '!=', 'header');
            })
            ->where('activ', true);
    }

    public static function applyLiniiContorConfigurabilScope($query)
    {
        return $query
            ->where(function ($query): void {
                $query->whereRaw('lower(trim(tip_calcul)) = ?', ['contor configurabil']);
            })
            ->where(function ($query): void {
                $query->whereNull('tip_linie')
                    ->orWhere('tip_linie', '!=', 'header');
            })
            ->where('activ', true);
    }
}
