<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contor extends Model
{
    protected $table = 'contoare';

    protected $fillable = [
        'imobil_id',
        'spatiu_id',
        'tip_utilitate',
        'cod_contor',
        'nivel',
        'activ',
        'observatii',
    ];

    protected $casts = [
        'activ' => 'boolean',
    ];

    public function imobil(): BelongsTo
    {
        return $this->belongsTo(Imobil::class);
    }

    public function spatiu(): BelongsTo
    {
        return $this->belongsTo(Spatiu::class);
    }

    public function citiri(): HasMany
    {
        return $this->hasMany(CitireContor::class);
    }
}
