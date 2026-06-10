<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigurareAnexaLinie extends Model
{
    protected $table = 'configurare_anexa_linii';

    protected $fillable = [
        'configurare_anexa_id',
        'ordine',
        'tip_linie',
        'denumire',
        'nr_crt',
        'index_vechi',
        'index_nou',
        'facturat',
        'coeficient',
        'um',
        'pret_unitar',
        'valoare',
        'tva_21',
        'tip_calcul',
        'apare_cu_zero',
        'activ',
        'observatii',
    ];

    protected $casts = [
        'nr_crt' => 'integer',
        'facturat' => 'decimal:3',
        'coeficient' => 'decimal:4',
        'pret_unitar' => 'decimal:4',
        'valoare' => 'decimal:2',
        'tva_21' => 'decimal:2',
        'apare_cu_zero' => 'boolean',
        'activ' => 'boolean',
    ];

    public function configurare(): BelongsTo
    {
        return $this->belongsTo(ConfigurareAnexaImobil::class, 'configurare_anexa_id');
    }
}
