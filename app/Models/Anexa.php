<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Anexa extends Model
{
    protected $table = 'anexe';

    protected $fillable = ['contract_id', 'luna', 'total', 'status'];

    protected $casts = ['total' => 'decimal:2'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function linii(): HasMany
    {
        return $this->hasMany(AnexaLinie::class)->orderBy('ordine')->orderBy('id');
    }

    public function factura(): HasOne
    {
        return $this->hasOne(Factura::class);
    }
}
