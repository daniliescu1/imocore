<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CitireContor extends Model
{
    protected $table = 'citiri_contoare';

    protected $fillable = [
        'contor_id',
        'spatiu_id',
        'configurare_anexa_linie_id',
        'luna',
        'data_citire',
        'index_vechi',
        'index_nou',
        'consum',
        'fisier_path',
        'fisier_nume',
        'observatii',
    ];

    protected $casts = [
        'index_vechi' => 'decimal:3',
        'index_nou' => 'decimal:3',
        'consum' => 'decimal:3',
        'data_citire' => 'datetime',
    ];

    public function contor(): BelongsTo
    {
        return $this->belongsTo(Contor::class);
    }

    public function spatiu(): BelongsTo
    {
        return $this->belongsTo(Spatiu::class);
    }

    public function configurareAnexaLinie(): BelongsTo
    {
        return $this->belongsTo(ConfigurareAnexaLinie::class, 'configurare_anexa_linie_id');
    }
}
