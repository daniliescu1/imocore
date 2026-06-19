<?php

namespace App\Http\Controllers;

use App\Models\PerioadaInchiriereFatada;
use App\Models\Spatiu;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PerioadaInchiriereFatadaController extends Controller
{
    public function store(Request $request, Spatiu $spatiu): Response|RedirectResponse
    {
        $this->ensureSpatiuFatada($spatiu);

        $validated = $request->validate([
            'an' => ['required', 'integer', 'min:2000', 'max:2100'],
            'data_start' => ['required', 'date'],
            'data_end' => ['required', 'date', 'after_or_equal:data_start'],
            'chirias' => ['required', 'string', 'max:255'],
            'chirie_lunara' => ['required', 'numeric', 'min:0'],
        ]);

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

        $this->syncChiriasCurent($spatiu);

        return $this->editResponse($request, $spatiu, 'Perioada de închiriere a fost blocată.');
    }

    public function update(Request $request, Spatiu $spatiu, PerioadaInchiriereFatada $perioada): Response|RedirectResponse
    {
        $this->ensureSpatiuFatada($spatiu);
        abort_unless($perioada->spatiu_id === $spatiu->id, 404);

        $validated = $request->validate([
            'an' => ['required', 'integer', 'min:2000', 'max:2100'],
            'data_start' => ['required', 'date'],
            'data_end' => ['required', 'date', 'after_or_equal:data_start'],
            'chirias' => ['required', 'string', 'max:255'],
            'chirie_lunara' => ['required', 'numeric', 'min:0'],
        ]);

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

        $this->syncChiriasCurent($spatiu);

        return $this->editResponse($request, $spatiu, 'Perioada de închiriere a fost actualizată.');
    }

    private function editResponse(Request $request, Spatiu $spatiu, string $successMessage): Response|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            $request->session()->flash('success', $successMessage);

            return Inertia::render(
                'Spatii/Create',
                app(SpatiuController::class)->editPageProps($request, $spatiu->fresh()),
            );
        }

        return redirect()
            ->route('spatii.edit', $spatiu)
            ->with('success', $successMessage);
    }

    private function ensureSpatiuFatada(Spatiu $spatiu): void
    {
        if ($spatiu->etaj !== 'Fațadă') {
            throw ValidationException::withMessages([
                'data_start' => 'Calendarul este disponibil doar pentru spațiile de pe fațadă.',
            ]);
        }
    }

    private function validatePerioada(Spatiu $spatiu, Carbon $start, Carbon $end, int $an, ?int $exceptId = null): void
    {
        if ($start->year !== $an || $end->year !== $an) {
            throw ValidationException::withMessages([
                'data_start' => 'Perioada trebuie să fie complet în anul selectat.',
            ]);
        }

        $zile = (int) $start->diffInDays($end) + 1;

        if ($zile < PerioadaInchiriereFatada::MINIM_ZILE) {
            throw ValidationException::withMessages([
                'data_end' => 'Perioada minimă de închiriere este de 30 de zile.',
            ]);
        }

        if (PerioadaInchiriereFatada::seSuprapune($spatiu->id, $start, $end, $exceptId)) {
            throw ValidationException::withMessages([
                'data_start' => 'Perioada se suprapune cu o închiriere existentă.',
            ]);
        }
    }

    private function syncChiriasCurent(Spatiu $spatiu): void
    {
        $perioadaCurenta = PerioadaInchiriereFatada::query()
            ->where('spatiu_id', $spatiu->id)
            ->whereDate('data_start', '<=', now())
            ->whereDate('data_end', '>=', now())
            ->orderBy('data_start')
            ->first();

        $spatiu->update([
            'chirias' => $perioadaCurenta?->chirias,
            'pret_lunar' => $perioadaCurenta?->chirie_lunara,
        ]);
    }
}
