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

        if ($normalized === 'administrare') {
            return 'administrare';
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

    public static function isAdministrare(?string $tipCalcul): bool
    {
        return self::normalize($tipCalcul) === 'administrare';
    }

    public static function folosesteFacturatDinTemplate(?string $tipCalcul): bool
    {
        return in_array(self::normalize($tipCalcul), ['manual', 'fix', 'administrare', 'zero'], true);
    }

    public static function cantitateDinTemplateLinie(
        ?string $tipCalcul,
        mixed $facturat,
        mixed $pretUnitar,
        mixed $valoare,
    ): ?float {
        if (! self::folosesteFacturatDinTemplate($tipCalcul)) {
            return null;
        }

        if ($facturat !== null && $facturat !== '') {
            return (float) $facturat;
        }

        $pret = (float) ($pretUnitar ?? 0);
        $total = (float) ($valoare ?? 0);

        if ($pret > 0 && $total > 0) {
            return round($total / $pret, 3);
        }

        return null;
    }

    public static function isContorConfigurabil(?string $tipCalcul): bool
    {
        return self::normalize($tipCalcul) === 'contor_configurabil';
    }

    public static function pausalCitireManualCuIndex(?string $denumire): bool
    {
        $denumire = strtolower(trim((string) $denumire));

        if ($denumire === '') {
            return false;
        }

        return str_contains($denumire, 'consum apa')
            || str_contains($denumire, 'canalizare');
    }

    public static function folosesteIndexLaCitire(?string $tipCalcul, ?string $denumire = null, mixed $citire = null): bool
    {
        if (self::isContor($tipCalcul) || self::isContorConfigurabil($tipCalcul)) {
            return true;
        }

        if (! self::isPausal($tipCalcul)) {
            return false;
        }

        if (self::pausalCitireManualCuIndex($denumire)) {
            return true;
        }

        if ($citire === null) {
            return false;
        }

        $indexVechi = is_array($citire)
            ? ($citire['index_vechi'] ?? null)
            : ($citire->index_vechi ?? null);
        $indexNou = is_array($citire)
            ? ($citire['index_nou'] ?? null)
            : ($citire->index_nou ?? null);

        return ($indexNou !== null && $indexNou !== '' && (float) $indexNou > 0)
            || ($indexVechi !== null && $indexVechi !== '' && (float) $indexVechi > 0);
    }

    public static function needsConfigurareContoare(?string $tipCalcul): bool
    {
        return self::isContorConfigurabil($tipCalcul) || self::isPausal($tipCalcul);
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
                    ->orWhere(function ($query): void {
                        $query->whereRaw('lower(trim(tip_calcul)) = ?', ['pausal'])
                            ->where(function ($query): void {
                                $query->whereRaw('lower(denumire) like ?', ['%consum apa%'])
                                    ->orWhereRaw('lower(denumire) like ?', ['%canalizare%']);
                            });
                    });
            })
            ->where(function ($query): void {
                $query->whereNull('tip_linie')
                    ->orWhere('tip_linie', '!=', 'header');
            })
            ->where('activ', true);
    }

    public static function applyLiniiConfigurareContoareScope($query)
    {
        return $query
            ->where(function ($query): void {
                $query->whereRaw('lower(trim(tip_calcul)) = ?', ['contor configurabil'])
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
        return self::applyLiniiConfigurareContoareScope($query);
    }
}
