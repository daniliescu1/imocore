<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Spatiu extends Model
{
    public const ETAJ_PARCARE = 'Parcare';

    public const ETAJ_CASUTA_POSTALA = 'Casuta postala';

    public const ETAJ_SPATIU_DEPOZITARE = 'Spatiu depozitare';

    public const ETAJ_OPTIONS = [
        '-1',
        'Parter',
        '1',
        '2',
        '3',
        '4',
        '5',
        'Acoperiș',
        'Fațadă',
        self::ETAJ_PARCARE,
        self::ETAJ_CASUTA_POSTALA,
        self::ETAJ_SPATIU_DEPOZITARE,
    ];

    public const ETAJE_FARA_PERSOANE = [
        'Acoperiș',
        'Fațadă',
        self::ETAJ_PARCARE,
        self::ETAJ_CASUTA_POSTALA,
        self::ETAJ_SPATIU_DEPOZITARE,
    ];

    protected $table = 'spatii';

    protected $fillable = [
        'imobil_id',
        'ordine',
        'identificator',
        'suprafata_contractuala_mp',
        'corp',
        'etaj',
        'persoane_declarate',
        'regim_incalzire',
        'procent_incalzire_override',
        'retim_direct',
        'status',
        'activ',
        'arhivat_la',
        'pret_lunar',
        'indexare_2026',
        'moneda',
        'locator_id',
        'configurare_anexa_id',
        'locator',
        'chirias',
        'observatii',
        'de_lamurit',
        'de_lamurit_detaliu',
        'marcat_galben',
        'marcat_verde',
    ];

    protected $casts = [
        'suprafata_contractuala_mp' => 'decimal:2',
        'pret_lunar' => 'decimal:2',
        'indexare_2026' => 'decimal:2',
        'retim_direct' => 'boolean',
        'activ' => 'boolean',
        'de_lamurit' => 'boolean',
        'marcat_galben' => 'boolean',
        'marcat_verde' => 'boolean',
        'arhivat_la' => 'datetime',
        'ordine' => 'integer',
    ];

    public function getPersoaneStandardAttribute(): int
    {
        if ($this->status === 'administrativ' || $this->status === 'comun' || self::etajFaraPersoane($this->etaj)) {
            return 0;
        }

        return self::calculeazaPersoaneStandard($this->suprafata_contractuala_mp);
    }

    public static function etajFaraPersoane(?string $etaj): bool
    {
        return in_array($etaj, self::ETAJE_FARA_PERSOANE, true);
    }

    public static function esteParcare(?string $etaj): bool
    {
        return $etaj === self::ETAJ_PARCARE;
    }

    public static function normalizeMoneda(?string $etaj, ?string $moneda): string
    {
        if (! self::esteParcare($etaj)) {
            return 'EUR';
        }

        $moneda = strtoupper(trim((string) $moneda));

        return in_array($moneda, ['EUR', 'RON'], true) ? $moneda : 'RON';
    }

    public function monedaLabel(): string
    {
        return $this->moneda === 'RON' ? 'Lei' : 'EUR';
    }

    public static function calculeazaPersoaneStandard(float|string|null $suprafata): int
    {
        $suprafataMp = (float) ($suprafata ?: 0);

        if ($suprafataMp <= 0) {
            return 0;
        }

        return (int) ceil($suprafataMp / 10);
    }

    public function persoanePentruAnexa(): int
    {
        if ($this->persoane_declarate !== null) {
            return (int) $this->persoane_declarate;
        }

        if ($this->status === 'administrativ' || $this->status === 'comun' || self::etajFaraPersoane($this->etaj)) {
            return 0;
        }

        return $this->persoane_standard;
    }

    public function scopeInchiriat($query)
    {
        return $query->where('status', 'inchiriat');
    }

    public function scopeAlocateAnexei($query, int $configurareAnexaId)
    {
        return $query->where('configurare_anexa_id', $configurareAnexaId);
    }

    /**
     * @return list<int>
     */
    public static function idsInchiriateForAnexa(int $configurareAnexaId): array
    {
        return self::query()
            ->alocateAnexei($configurareAnexaId)
            ->inchiriat()
            ->orderBy('identificator')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public static function countInchiriateForAnexa(int $configurareAnexaId): int
    {
        return self::query()
            ->alocateAnexei($configurareAnexaId)
            ->inchiriat()
            ->count();
    }

    public static function countAlocateForAnexa(int $configurareAnexaId): int
    {
        return self::query()
            ->where('configurare_anexa_id', $configurareAnexaId)
            ->count();
    }

    public function chirieCurenta(): float
    {
        return (float) ($this->indexare_2026 ?: $this->pret_lunar ?: 0);
    }

    public function imobil(): BelongsTo
    {
        return $this->belongsTo(Imobil::class);
    }

    public function locatorEntitate(): BelongsTo
    {
        return $this->belongsTo(Locator::class, 'locator_id');
    }

    public function configurareAnexa(): BelongsTo
    {
        return $this->belongsTo(ConfigurareAnexaImobil::class, 'configurare_anexa_id');
    }

    public function rezervari(): HasMany
    {
        return $this->hasMany(Rezervare::class);
    }

    public function contracte(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function pvPredare(): HasMany
    {
        return $this->hasMany(PvPredare::class);
    }

    public function perioadeInchiriereFatada(): HasMany
    {
        return $this->hasMany(PerioadaInchiriereFatada::class);
    }
}
