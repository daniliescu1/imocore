<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PvPredare extends Model
{
    protected $table = 'pv_predare';

    protected $fillable = [
        'spatiu_id',
        'tip',
        'data_pv',
        'status',
        'observatii',
    ];

    protected $casts = [
        'data_pv' => 'date',
    ];

    public function spatiu(): BelongsTo
    {
        return $this->belongsTo(Spatiu::class);
    }

    public function contoareInitiale(): HasMany
    {
        return $this->hasMany(PvContorInitial::class);
    }
}
