<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Imobil extends Model
{
    protected $table = 'imobile';

    public const CAMPURI_SPATIU_OBLIGATORII = [
        'imobil_id',
        'identificator',
        'status',
    ];

    public const CAMPURI_FORMULAR = [
        'nume' => 'Nume imobil',
        'strada' => 'Stradă',
        'numar' => 'Număr',
        'localitate' => 'Localitate',
        'judet' => 'Județ',
        'cod_postal' => 'Cod poștal',
        'numere_cf' => 'Numere CF',
        'campuri_spatiu_vizibile' => 'Câmpuri vizibile formular spațiu',
        'observatii' => 'Observații',
    ];

    public const CAMPURI_SPATIU_CONFIGURABILE = [
        'suprafata_contractuala_mp' => 'Suprafață mp',
        'corp' => 'Corp',
        'etaj' => 'Etaj',
        'persoane_standard' => 'Persoane standard calculate',
        'pret_lunar' => 'Chirie lunară',
        'indexare_2025' => 'Indexare 2025',
        'indexare_2026' => 'Indexare 2026',
        'pret_mp_ultima_indexare' => 'Preț / mp ultima indexare',
        'regim_incalzire' => 'Regim încălzire',
        'procent_incalzire_override' => 'Procent încălzire parțială',
        'locator_id' => 'Locator existent',
        'configurare_anexa_id' => 'Configurare anexă',
        'chirias' => 'Chiriaș',
        'observatii' => 'Observații',
    ];

    protected $fillable = [
        'nume',
        'strada',
        'numar',
        'localitate',
        'judet',
        'cod_postal',
        'numere_cf',
        'campuri_spatiu_vizibile',
        'spatii_total',
        'spatii_libere',
        'spatii_inchiriate',
        'spatii_comune',
        'observatii',
    ];

    protected $casts = [
        'spatii_total' => 'integer',
        'spatii_libere' => 'integer',
        'spatii_inchiriate' => 'integer',
        'spatii_comune' => 'integer',
        'numere_cf' => 'array',
        'campuri_spatiu_vizibile' => 'array',
    ];

    public function spatii(): HasMany
    {
        return $this->hasMany(Spatiu::class);
    }

    public function locatori(): HasMany
    {
        return $this->hasMany(Locator::class);
    }

    public function reguli(): HasOne
    {
        return $this->hasOne(ReguliImobil::class);
    }

    public function configurariAnexe(): HasMany
    {
        return $this->hasMany(ConfigurareAnexaImobil::class);
    }

    public function recalculeazaSpatii(): void
    {
        $counts = $this->spatii()->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = 'liber' then 1 else 0 end) as libere")
            ->selectRaw("sum(case when status = 'inchiriat' then 1 else 0 end) as inchiriate")
            ->selectRaw("sum(case when status = 'comun' then 1 else 0 end) as comune")
            ->first();

        $this->forceFill([
            'spatii_total' => (int) ($counts->total ?? 0),
            'spatii_libere' => (int) ($counts->libere ?? 0),
            'spatii_inchiriate' => (int) ($counts->inchiriate ?? 0),
            'spatii_comune' => (int) ($counts->comune ?? 0),
        ])->save();
    }

    public static function campuriSpatiuConfigurabilePentruForm(): array
    {
        return collect(self::CAMPURI_SPATIU_CONFIGURABILE)
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    public static function campuriSpatiuImplicite(): array
    {
        return array_keys(self::CAMPURI_SPATIU_CONFIGURABILE);
    }

    public static function normalizeazaCampuriSpatiuVizibile(?array $campuri): array
    {
        if ($campuri === null) {
            return self::campuriSpatiuImplicite();
        }

        $permise = array_keys(self::CAMPURI_SPATIU_CONFIGURABILE);

        return collect($campuri)
            ->filter(fn ($camp): bool => is_string($camp) && in_array($camp, $permise, true))
            ->unique()
            ->values()
            ->all();
    }

    public function campuriSpatiuVizibilePentruForm(): array
    {
        return self::normalizeazaCampuriSpatiuVizibile($this->campuri_spatiu_vizibile);
    }
}