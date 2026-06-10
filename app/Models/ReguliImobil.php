<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ReguliImobil extends Model
{
    protected $table = 'reguli_imobile';

    protected $fillable = [
        'imobil_id',
        'metoda_curent',
        'procent_pierdere_curent',
        'metoda_apa',
        'metoda_canalizare',
        'coeficient_apa_pluviala',
        'coeficient_apa_pluviala_aprobat',
        'procent_incalzire_partial',
        'incalzire_start',
        'incalzire_end',
        'metoda_spatii_comune',
        'metoda_retim',
        'config',
    ];

    protected $casts = [
        'procent_pierdere_curent' => 'decimal:2',
        'coeficient_apa_pluviala' => 'decimal:4',
        'coeficient_apa_pluviala_aprobat' => 'boolean',
        'procent_incalzire_partial' => 'decimal:2',
        'incalzire_start' => 'date',
        'incalzire_end' => 'date',
        'config' => 'array',
    ];

    public function imobil(): BelongsTo
    {
        return $this->belongsTo(Imobil::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
