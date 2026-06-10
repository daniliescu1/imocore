<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rezervare extends Model
{
    protected $table = 'rezervari';

    protected $fillable = [
        'spatiu_id',
        'prospect',
        'garantie',
        'moneda',
        'data_rezervare',
        'termen_semnare',
        'garantie_incasata',
        'status',
        'observatii',
    ];

    protected $casts = [
        'garantie' => 'decimal:2',
        'data_rezervare' => 'date',
        'termen_semnare' => 'date',
        'garantie_incasata' => 'boolean',
    ];

    public function spatiu(): BelongsTo
    {
        return $this->belongsTo(Spatiu::class);
    }
}
