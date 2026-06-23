<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnexaLinie extends Model
{
    protected $table = 'anexa_linii';

    protected $fillable = [
        'anexa_id',
        'ordine',
        'tip_linie',
        'nr_crt',
        'denumire',
        'um',
        'tip_calcul',
        'index_vechi',
        'index_nou',
        'cantitate',
        'coeficient',
        'pret_unitar',
        'moneda',
        'valoare',
        'tva_21',
        'observatii',
    ];

    protected $casts = [
        'cantitate' => 'decimal:3',
        'coeficient' => 'decimal:4',
        'index_vechi' => 'decimal:3',
        'index_nou' => 'decimal:3',
        'pret_unitar' => 'decimal:4',
        'valoare' => 'decimal:2',
        'tva_21' => 'decimal:2',
    ];

    public function anexa(): BelongsTo
    {
        return $this->belongsTo(Anexa::class);
    }
}
