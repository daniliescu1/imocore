<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContorConfigurabil extends Model
{
    protected $table = 'contoare_configurabile';

    protected $fillable = [
        'imobil_id',
        'configurare_anexa_id',
        'configurare_anexa_linie_id',
        'foloseste_scaderi',
        'scaderi',
        'alocari',
    ];

    protected $casts = [
        'foloseste_scaderi' => 'boolean',
        'scaderi' => 'array',
        'alocari' => 'array',
    ];

    public function imobil(): BelongsTo
    {
        return $this->belongsTo(Imobil::class);
    }

    public function configurareAnexa(): BelongsTo
    {
        return $this->belongsTo(ConfigurareAnexaImobil::class, 'configurare_anexa_id');
    }

    public function configurareAnexaLinie(): BelongsTo
    {
        return $this->belongsTo(ConfigurareAnexaLinie::class, 'configurare_anexa_linie_id');
    }

    /**
     * @return list<int>
     */
    public function alocariIds(): array
    {
        return collect($this->alocari ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @return list<array{spatiu_id: int, configurare_anexa_linie_id: int}>
     */
    public function scaderiNormalizate(): array
    {
        return collect($this->scaderi ?? [])
            ->map(function (array $item): ?array {
                $spatiuId = (int) ($item['spatiu_id'] ?? 0);
                $linieId = (int) ($item['configurare_anexa_linie_id'] ?? 0);

                if ($spatiuId === 0 || $linieId === 0) {
                    return null;
                }

                return [
                    'spatiu_id' => $spatiuId,
                    'configurare_anexa_linie_id' => $linieId,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function esteConfigurat(): bool
    {
        return $this->alocariIds() !== [];
    }
}
