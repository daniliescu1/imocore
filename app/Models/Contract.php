<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    protected $table = 'contracte';

    protected $fillable = [
        'spatiu_id',
        'numar_contract',
        'chirias',
        'data_start',
        'data_end',
        'chirie',
        'moneda',
        'status',
        'observatii',
    ];

    protected $casts = [
        'data_start' => 'date',
        'data_end' => 'date',
        'chirie' => 'decimal:2',
    ];

    public function spatiu(): BelongsTo
    {
        return $this->belongsTo(Spatiu::class);
    }
}
