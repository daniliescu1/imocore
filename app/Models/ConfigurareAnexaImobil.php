<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfigurareAnexaImobil extends Model
{
    protected $table = 'configurari_anexe_imobil';

    protected $fillable = [
        'imobil_id',
        'denumire',
        'implicit',
        'activ',
        'observatii',
    ];

    protected $casts = [
        'implicit' => 'boolean',
        'activ' => 'boolean',
    ];

    public function imobil(): BelongsTo
    {
        return $this->belongsTo(Imobil::class);
    }

    public function linii(): HasMany
    {
        return $this->hasMany(ConfigurareAnexaLinie::class, 'configurare_anexa_id')->orderBy('ordine')->orderBy('id');
    }
}
