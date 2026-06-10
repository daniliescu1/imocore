<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PvContorInitial extends Model
{
    protected $table = 'pv_contor_initial';

    protected $fillable = [
        'pv_predare_id',
        'tip_utilitate',
        'cod_contor',
        'index_initial',
        'fisier_path',
        'fisier_nume',
        'observatii',
    ];

    protected $casts = [
        'index_initial' => 'decimal:3',
    ];

    public function pvPredare(): BelongsTo
    {
        return $this->belongsTo(PvPredare::class);
    }
}
