<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Factura extends Model
{
    protected $table = 'facturi';

    protected $fillable = [
        'anexa_id',
        'numar_factura',
        'curs_eur',
        'chirie_eur',
        'chirie_lei',
        'total',
        'penalitati',
        'status',
        'email_chirias',
        'trimis_la',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'curs_eur' => 'decimal:4',
        'chirie_eur' => 'decimal:2',
        'chirie_lei' => 'decimal:2',
        'penalitati' => 'decimal:2',
        'trimis_la' => 'datetime',
    ];

    public function anexa(): BelongsTo
    {
        return $this->belongsTo(Anexa::class);
    }
}
