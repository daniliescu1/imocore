<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Spatiu extends Model
{
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
        'indexare_2025',
        'indexare_2026',
        'moneda',
        'locator_id',
        'configurare_anexa_id',
        'locator',
        'chirias',
        'observatii',
    ];

    protected $casts = [
        'suprafata_contractuala_mp' => 'decimal:2',
        'pret_lunar' => 'decimal:2',
        'indexare_2025' => 'decimal:2',
        'indexare_2026' => 'decimal:2',
        'retim_direct' => 'boolean',
        'activ' => 'boolean',
        'arhivat_la' => 'datetime',
        'ordine' => 'integer',
    ];

    public function getPersoaneStandardAttribute(): int
    {
        if ($this->status === 'administrativ') {
            return 0;
        }

        return (int) floor(((float) $this->suprafata_contractuala_mp) / 10);
    }

    public function persoanePentruAnexa(): int
    {
        if ($this->status === 'administrativ') {
            return 0;
        }

        if ($this->persoane_declarate !== null) {
            return (int) $this->persoane_declarate;
        }

        return $this->persoane_standard;
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
}
