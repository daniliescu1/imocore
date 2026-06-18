<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiciuStandardAnexa extends Model
{
    public const TIP_DENUMIRE = 'denumire';

    public const TIP_UM = 'um';

    public const TIP_TVA = 'tva';

    public const TIP_TIP_CALCUL = 'tip_calcul';

    public const TIPURI = [
        self::TIP_DENUMIRE,
        self::TIP_UM,
        self::TIP_TVA,
        self::TIP_TIP_CALCUL,
    ];

    protected $table = 'servicii_standard_anexa';

    protected $fillable = [
        'tip',
        'valoare',
        'label',
        'coeficient',
        'activ',
        'ordine',
    ];

    protected $casts = [
        'activ' => 'boolean',
        'ordine' => 'integer',
        'coeficient' => 'decimal:4',
    ];

    public static function optionsForForm(): array
    {
        if (static::query()->count() === 0) {
            static::importFromExistingLines();
        }

        $grouped = static::query()
            ->where('activ', true)
            ->orderBy('ordine')
            ->orderBy('label')
            ->get()
            ->groupBy('tip');

        $options = [];
        foreach (self::TIPURI as $tip) {
            $options[$tip] = ($grouped->get($tip) ?? collect())
                ->map(fn (self $item): array => [
                    'valoare' => $item->tip === self::TIP_TVA
                        ? self::normalizeValoare(self::TIP_TVA, $item->valoare)
                        : $item->valoare,
                    'label' => $item->tip === self::TIP_TVA
                        ? self::tvaLabel($item->valoare)
                        : ($item->label ?: $item->valoare),
                    'coeficient' => $item->coeficient,
                ])
                ->values()
                ->all();
        }

        return $options;
    }

    public static function labelForTip(string $tip): string
    {
        return match ($tip) {
            self::TIP_DENUMIRE => 'Denumire serviciu',
            self::TIP_UM => 'UM',
            self::TIP_TVA => 'TVA',
            self::TIP_TIP_CALCUL => 'Tip calcul',
            default => $tip,
        };
    }

    public static function importFromExistingLines(): void
    {
        ConfigurareAnexaLinie::query()
            ->whereNotNull('denumire')
            ->where('denumire', '!=', '')
            ->distinct()
            ->pluck('denumire')
            ->each(function (string $denumire): void {
                $valoare = trim($denumire);
                static::query()->firstOrCreate(
                    ['tip' => self::TIP_DENUMIRE, 'valoare' => $valoare],
                    ['label' => $valoare, 'activ' => true]
                );
            });

        ConfigurareAnexaLinie::query()
            ->whereNotNull('um')
            ->where('um', '!=', '')
            ->distinct()
            ->pluck('um')
            ->each(function (string $um): void {
                $valoare = trim($um);
                static::query()->firstOrCreate(
                    ['tip' => self::TIP_UM, 'valoare' => $valoare],
                    ['label' => $valoare, 'activ' => true]
                );
            });

        ConfigurareAnexaLinie::query()
            ->whereNotNull('tva_21')
            ->distinct()
            ->pluck('tva_21')
            ->each(function ($tva): void {
                $valoare = static::normalizeValoare(self::TIP_TVA, (string) $tva);
                if ($valoare === '') {
                    return;
                }

                static::query()->firstOrCreate(
                    ['tip' => self::TIP_TVA, 'valoare' => $valoare],
                    ['label' => self::tvaLabel($valoare), 'activ' => true]
                );
            });

        foreach (static::tipCalculDefaults() as $valoare => $label) {
            static::query()->firstOrCreate(
                ['tip' => self::TIP_TIP_CALCUL, 'valoare' => $valoare],
                ['label' => $label, 'coeficient' => $valoare === 'mp_coeficient' ? '0.0900' : null, 'activ' => true]
            );
        }

        ConfigurareAnexaLinie::query()
            ->whereNotNull('tip_calcul')
            ->where('tip_calcul', '!=', '')
            ->distinct()
            ->pluck('tip_calcul')
            ->each(function (string $tipCalcul): void {
                $valoare = trim($tipCalcul);
                static::query()->firstOrCreate(
                    ['tip' => self::TIP_TIP_CALCUL, 'valoare' => $valoare],
                    ['label' => static::tipCalculDefaults()[$valoare] ?? ucfirst($valoare), 'activ' => true]
                );
            });
    }

    public static function tipCalculDefaults(): array
    {
        return [
            'manual' => 'Manual',
            'fix' => 'Fix',
            'mp' => 'Pe mp',
            'mp_coeficient' => 'Mp × coeficient',
            'persoane' => 'Pe persoane',
            'contor' => 'Contor',
            'zero' => '0 lei',
        ];
    }

    public static function normalizeValoare(string $tip, string $valoare): string
    {
        $valoare = trim($valoare);

        if ($tip === self::TIP_TVA) {
            $valoare = str_replace('%', '', $valoare);

            return rtrim(rtrim(str_replace(',', '.', $valoare), '0'), '.');
        }

        return $valoare;
    }

    public static function tvaLabel(string $valoare): string
    {
        $normalized = self::normalizeValoare(self::TIP_TVA, $valoare);

        return $normalized === '' ? '' : "{$normalized}%";
    }
}
