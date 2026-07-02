<?php

namespace App\Models;

use App\Support\CoeficientCantitatePret;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiciuStandardAnexa extends Model
{
    public const TIP_DENUMIRE = 'denumire';

    public const TIP_UM = 'um';

    public const TIP_TVA = 'tva';

    public const TIP_TIP_CALCUL = 'tip_calcul';

    public const TIP_PRET = 'pret';

    public const TIPURI = [
        self::TIP_DENUMIRE,
        self::TIP_UM,
        self::TIP_TVA,
        self::TIP_TIP_CALCUL,
        self::TIP_PRET,
    ];

    protected $table = 'servicii_standard_anexa';

    protected $fillable = [
        'tip',
        'valoare',
        'label',
        'coeficient',
        'coeficient_cantitate',
        'moneda',
        'tva',
        'um',
        'activ',
        'ordine',
    ];

    protected $casts = [
        'activ' => 'boolean',
        'ordine' => 'integer',
        'coeficient' => 'decimal:4',
        'coeficient_cantitate' => 'decimal:4',
    ];

    public function liniiConfigurare(): HasMany
    {
        return $this->hasMany(ConfigurareAnexaLinie::class, 'serviciu_standard_pret_id');
    }

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
                ->map(fn (self $item): array => self::mapOptionForForm($item))
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
            self::TIP_PRET => 'Prețuri',
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

        ConfigurareAnexaLinie::query()
            ->whereNotNull('denumire')
            ->where('denumire', '!=', '')
            ->whereNotNull('pret_unitar')
            ->select('denumire', 'pret_unitar', 'tva_21', 'um')
            ->distinct()
            ->orderBy('denumire')
            ->get()
            ->groupBy('denumire')
            ->each(function ($linii, string $denumire): void {
                $linie = $linii->first();
                $pret = (string) $linie->pret_unitar;
                $tva = $linie->tva_21 !== null && $linie->tva_21 !== ''
                    ? self::normalizeValoare(self::TIP_TVA, (string) $linie->tva_21)
                    : null;
                $um = $linie->um !== null && trim((string) $linie->um) !== ''
                    ? trim((string) $linie->um)
                    : null;

                static::query()->updateOrCreate(
                    ['tip' => self::TIP_PRET, 'valoare' => trim($denumire), 'label' => 'Standard'],
                    ['coeficient' => $pret, 'tva' => $tva, 'um' => $um, 'coeficient_cantitate' => 1, 'activ' => true]
                );
            });

        static::syncPreturiFromDenumire();
    }

    public static function syncPreturiFromDenumire(): void
    {
        $denumiri = static::query()
            ->where('tip', self::TIP_DENUMIRE)
            ->where('activ', true)
            ->orderBy('ordine')
            ->orderBy('label')
            ->get();

        foreach ($denumiri as $denumire) {
            static::query()->firstOrCreate(
                ['tip' => self::TIP_PRET, 'valoare' => $denumire->valoare, 'label' => 'Standard'],
                ['coeficient_cantitate' => 1, 'activ' => true]
            );
        }

        static::query()
            ->where('tip', self::TIP_PRET)
            ->whereNotIn('valoare', $denumiri->pluck('valoare'))
            ->update(['activ' => false]);
    }

    public static function pretPentruDenumire(string $denumire, ?int $pretId = null): ?string
    {
        $item = self::pretRecordPentruDenumire($denumire, $pretId);
        $pret = $item?->coeficient;

        return $pret !== null && $pret !== '' ? (string) $pret : null;
    }

    public static function monedaPentruDenumire(string $denumire, ?int $pretId = null): string
    {
        $item = self::pretRecordPentruDenumire($denumire, $pretId);

        return \App\Support\PretServiciuStandard::normalizeMoneda($item?->moneda);
    }

    public static function tvaPentruDenumire(string $denumire, ?int $pretId = null): ?string
    {
        $item = self::pretRecordPentruDenumire($denumire, $pretId);
        $tva = $item?->tva;

        if ($tva === null || $tva === '') {
            return null;
        }

        return self::normalizeValoare(self::TIP_TVA, (string) $tva);
    }

    public static function umPentruDenumire(string $denumire, ?int $pretId = null): ?string
    {
        $item = self::pretRecordPentruDenumire($denumire, $pretId);
        $um = $item?->um;

        if ($um === null || trim((string) $um) === '') {
            return null;
        }

        return trim((string) $um);
    }

    public static function coeficientCantitatePentruPret(?int $pretId, ?string $denumire = null): float
    {
        $item = $pretId
            ? static::query()->whereKey($pretId)->where('tip', self::TIP_PRET)->first()
            : ($denumire ? self::pretRecordPentruDenumire($denumire) : null);

        return CoeficientCantitatePret::toMultiplier($item?->coeficient_cantitate);
    }

    public static function pretRecordPentruDenumire(string $denumire, ?int $pretId = null): ?self
    {
        if ($pretId) {
            return static::query()
                ->whereKey($pretId)
                ->where('tip', self::TIP_PRET)
                ->where('valoare', $denumire)
                ->where('activ', true)
                ->first();
        }

        return static::query()
            ->where('tip', self::TIP_PRET)
            ->where('valoare', $denumire)
            ->where('activ', true)
            ->orderBy('ordine')
            ->orderBy('id')
            ->first();
    }

    public static function variantLabel(self $item): string
    {
        return trim((string) ($item->label ?: 'Standard'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapOptionForForm(self $item): array
    {
        return [
            'id' => $item->id,
            'valoare' => $item->tip === self::TIP_TVA
                ? self::normalizeValoare(self::TIP_TVA, $item->valoare)
                : $item->valoare,
            'denumire' => $item->tip === self::TIP_PRET ? $item->valoare : null,
            'label' => match ($item->tip) {
                self::TIP_TVA => self::tvaLabel($item->valoare),
                self::TIP_PRET => self::variantLabel($item),
                default => $item->label ?: $item->valoare,
            },
            'coeficient' => $item->coeficient,
            'coeficient_cantitate' => $item->tip === self::TIP_PRET
                ? CoeficientCantitatePret::toPercentForForm($item->coeficient_cantitate)
                : null,
            'moneda' => $item->tip === self::TIP_PRET
                ? \App\Support\PretServiciuStandard::normalizeMoneda($item->moneda)
                : null,
            'tva' => $item->tip === self::TIP_PRET && $item->tva
                ? self::normalizeValoare(self::TIP_TVA, (string) $item->tva)
                : null,
            'um' => $item->tip === self::TIP_PRET && $item->um
                ? trim((string) $item->um)
                : null,
        ];
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
            'contor_fix' => 'Contor Fix',
            'pausal' => 'Pausal',
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
