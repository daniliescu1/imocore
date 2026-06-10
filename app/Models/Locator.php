<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Locator extends Model
{
    protected $table = 'locatori';

    protected $fillable = [
        'imobil_id',
        'nume',
        'cui_are_ro',
        'cui',
        'registrul_comertului',
        'adresa',
        'banca',
        'cont_bancar',
        'chirie_cu_tva',
    ];

    protected $casts = [
        'cui_are_ro' => 'boolean',
        'chirie_cu_tva' => 'boolean',
    ];

    public function imobil(): BelongsTo
    {
        return $this->belongsTo(Imobil::class);
    }

    public function spatii(): HasMany
    {
        return $this->hasMany(Spatiu::class);
    }
}
