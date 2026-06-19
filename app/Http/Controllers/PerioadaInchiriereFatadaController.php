<?php

namespace App\Http\Controllers;

use App\Models\PerioadaInchiriereFatada;
use App\Models\Spatiu;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PerioadaInchiriereFatadaController extends Controller
{
    public function store(Request $request, Spatiu $spatiu): RedirectResponse
    {
        $this->ensureSpatiuFatada($spatiu);

        $validated = $this->validatedData($request);

        $start = Carbon::parse($validated['data_start'])->startOfDay();
        $end = Carbon::parse($validated['data_end'])->startOfDay();
        $an = (int) $validated['an'];

        $this->validatePerioada($spatiu, $start, $end, $an);

        PerioadaInchiriereFatada::query()->create([
            'spatiu_id' => $spatiu->id,
            'data_start' => $start,
            'data_end' => $end,
            'chirias' => $validated['chirias'],
            'chirie_lunara' => $validated['chirie_lunara'],
            'moneda' => 'EUR',
        ]);

        $this->syncSpatiuDupaPerioada($spatiu, $validated['chirias'], $validated['chirie_lunara']);

        return redirect()
            ->route('spatii.edit', $spatiu)
            ->with('success', 'Perioada de închiriere a fost blocată.');
    }

    public function update(Request $request, Spatiu $spatiu, PerioadaInchiriereFatada $perioada): RedirectResponse
    {
        $this->ensureSpatiuFatada($spatiu);
        abort_unless($perioada->spatiu_id === $spatiu->id, 404);

        $validated = $this->validatedData($request);

        $start = Carbon::parse($validated['data_start'])->startOfDay();
        $end = Carbon::parse($validated['data_end'])->startOfDay();
        $an = (int) $validated['an'];

        $this->validatePerioada($spatiu, $start, $end, $an, $perioada->id);

        $perioada->update([
            'data_start' => $start,
            'data_end' => $end,
            'chirias' => $validated['chirias'],
            'chirie_lunara' => $validated['chirie_lunara'],
        ]);

        $this->syncSpatiuDupaPerioada($spatiu, $validated['chirias'], $validated['chirie_lunara']);

        return redirect()
            ->route('spatii.edit', $spatiu)
            ->with('success', 'Perioada de închiriere a fost actualizată.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        return Validator::make($request->all(), [
            'an' => ['required', 'integer', 'min:2000', 'max:2100'],
            'data_start' => ['required', 'date'],
            'data_end' => ['required', 'date', 'after_or_equal:data_start'],
            'chirias' => ['required', 'string', 'max:255'],
            'chirie_lunara' => ['required', 'numeric', 'min:0'],
        ])->validateWithBag('perioadeFatada');
    }

    private function ensureSpatiuFatada(Spatiu $spatiu): void
    {
        if ($spatiu->etaj !== 'Fațadă') {
            throw ValidationException::withMessages([
                'data_start' => 'Calendarul este disponibil doar pentru spațiile de pe fațadă.',
            ])->errorBag('perioadeFatada');
        }
    }

    private function validatePerioada(Spatiu $spatiu, Carbon $start, Carbon $end, int $an, ?int $exceptId = null): void
    {
        if ($start->year !== $an || $end->year !== $an) {
            throw ValidationException::withMessages([
                'data_start' => 'Perioada trebuie să fie complet în anul selectat.',
            ])->errorBag('perioadeFatada');
        }

        $zile = (int) $start->diffInDays($end) + 1;

        if ($zile < PerioadaInchiriereFatada::MINIM_ZILE) {
            throw ValidationException::withMessages([
                'data_end' => 'Perioada minimă de închiriere este de 30 de zile.',
            ])->errorBag('perioadeFatada');
        }

        if (PerioadaInchiriereFatada::seSuprapune($spatiu->id, $start, $end, $exceptId)) {
            throw ValidationException::withMessages([
                'data_start' => 'Perioada se suprapune cu o închiriere existentă.',
            ])->errorBag('perioadeFatada');
        }
    }

    private function syncSpatiuDupaPerioada(Spatiu $spatiu, string $chirias, float|string $chirieLunara): void
    {
        $spatiu->update([
            'status' => 'inchiriat',
            'chirias' => $chirias,
            'pret_lunar' => $chirieLunara,
        ]);

        $spatiu->imobil->recalculeazaSpatii();
    }
}
