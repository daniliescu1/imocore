<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CitireContorLunaInchisa extends Model
{
    protected $table = 'citiri_contoare_luni_inchise';

    protected $fillable = [
        'imobil_id',
        'luna',
        'inchis_at',
    ];

    protected $casts = [
        'inchis_at' => 'datetime',
    ];

    public function imobil(): BelongsTo
    {
        return $this->belongsTo(Imobil::class);
    }
}
