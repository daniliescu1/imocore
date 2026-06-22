<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    protected $table = 'contracte';

    protected $fillable = [
        'spatiu_id',
        'numar_contract',
        'chirias',
        'chirias_tip',
        'chirias_date',
        'data_start',
        'data_end',
        'chirie',
        'crestere_chirie_la',
        'data_crestere_chirie',
        'moneda',
        'status',
        'observatii',
    ];

    protected $casts = [
        'data_start' => 'date',
        'data_end' => 'date',
        'chirie' => 'decimal:2',
        'crestere_chirie_la' => 'decimal:2',
        'data_crestere_chirie' => 'date',
        'chirias_date' => 'array',
    ];

    public function spatiu(): BelongsTo
    {
        return $this->belongsTo(Spatiu::class);
    }

    public function folosesteCrestereChirieLa(mixed $date = null): bool
    {
        if (! $this->crestere_chirie_la || ! $this->data_crestere_chirie) {
            return false;
        }

        $targetDate = $this->normalizeDate($date);

        return $targetDate->startOfDay()->greaterThanOrEqualTo($this->data_crestere_chirie->copy()->startOfDay());
    }

    public function chirieAplicabilaLa(mixed $date = null): float
    {
        if ($this->folosesteCrestereChirieLa($date)) {
            return (float) $this->crestere_chirie_la;
        }

        return (float) ($this->chirie ?: 0);
    }

    public function chirieAplicabilaPentruLunaAnexa(?string $luna): float
    {
        if (! preg_match('/^\d{4}-\d{2}$/', (string) $luna)) {
            return $this->chirieAplicabilaLa();
        }

        $lunaChirie = Carbon::createFromFormat('Y-m', $luna)
            ->startOfMonth()
            ->addMonth();

        return $this->chirieAplicabilaLa($lunaChirie->endOfMonth());
    }

    public function chirieFacturabilaPentruLunaAnexa(?string $luna): float
    {
        $chirieContractuala = $this->chirieAplicabilaPentruLunaAnexa($luna);
        $chirieIndexata = (float) ($this->spatiu?->indexare_2026 ?? 0);

        if ($chirieIndexata > $chirieContractuala) {
            return $chirieIndexata;
        }

        return $chirieContractuala;
    }

    private function normalizeDate(mixed $date = null): Carbon
    {
        if ($date instanceof Carbon) {
            return $date->copy();
        }

        if ($date) {
            return Carbon::parse($date);
        }

        return Carbon::today();
    }
}
