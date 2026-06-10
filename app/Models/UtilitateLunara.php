<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilitateLunara extends Model
{
    protected $table = 'utilitati_lunare';

    protected $fillable = [
        'imobil_id',
        'luna',
        'tip_utilitate',
        'cantitate',
        'cost_total',
        'pret_unitar',
        'aprobat',
        'observatii',
    ];

    protected $casts = [
        'cantitate' => 'decimal:3',
        'cost_total' => 'decimal:2',
        'pret_unitar' => 'decimal:4',
        'aprobat' => 'boolean',
    ];

    public function imobil(): BelongsTo
    {
        return $this->belongsTo(Imobil::class);
    }
}
