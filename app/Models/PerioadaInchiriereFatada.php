<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerioadaInchiriereFatada extends Model
{
    public const MINIM_ZILE = 30;

    protected $table = 'perioade_inchiriere_fatada';

    protected $fillable = [
        'spatiu_id',
        'data_start',
        'data_end',
        'chirias',
        'chirie_lunara',
        'moneda',
    ];

    protected $casts = [
        'data_start' => 'date',
        'data_end' => 'date',
        'chirie_lunara' => 'decimal:2',
    ];

    public function spatiu(): BelongsTo
    {
        return $this->belongsTo(Spatiu::class);
    }

    public function zileInchiriate(): int
    {
        return (int) $this->data_start->diffInDays($this->data_end) + 1;
    }

    public function chirieTotalaProportionala(): string
    {
        return self::calculeazaChirieProportionala(
            $this->data_start,
            $this->data_end,
            $this->chirie_lunara,
        );
    }

    public static function calculeazaChirieProportionala(Carbon|string $start, Carbon|string $end, float|string $chirieLunara): string
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();
        $chirieLunara = (float) $chirieLunara;
        $total = 0.0;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $total += $chirieLunara / $cursor->daysInMonth;
            $cursor->addDay();
        }

        return number_format($total, 2, '.', '');
    }

    public function contineData(Carbon|string $data): bool
    {
        $data = Carbon::parse($data)->startOfDay();

        return $data->betweenIncluded(
            $this->data_start->copy()->startOfDay(),
            $this->data_end->copy()->startOfDay(),
        );
    }

    public static function seSuprapune(int $spatiuId, Carbon|string $start, Carbon|string $end, ?int $exceptId = null): bool
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();

        return self::query()
            ->where('spatiu_id', $spatiuId)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->where('data_start', '<=', $end)
            ->where('data_end', '>=', $start)
            ->exists();
    }
}
