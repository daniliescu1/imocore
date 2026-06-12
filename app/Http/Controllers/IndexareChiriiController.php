<?php

namespace App\Http\Controllers;

use App\Models\Imobil;
use App\Models\Spatiu;
use App\Support\DecimalInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexareChiriiController extends Controller
{
    public function index(Request $request): Response
    {
        $localitate = $request->string('localitate')->toString();
        $search = $request->string('search')->toString();

        $query = Spatiu::query()
            ->with('imobil')
            ->join('imobile', 'imobile.id', '=', 'spatii.imobil_id')
            ->select('spatii.*')
            ->orderBy('imobile.ordine')
            ->orderBy('imobile.id')
            ->orderBy('spatii.ordine')
            ->orderBy('spatii.id');

        if ($localitate !== '') {
            $query->where('imobile.localitate', $localitate);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('spatii.identificator', 'like', "%{$search}%")
                    ->orWhere('spatii.chirias', 'like', "%{$search}%")
                    ->orWhere('imobile.nume', 'like', "%{$search}%");
            });
        }

        $spatii = $query->get()->map(fn (Spatiu $spatiu): array => $this->mapSpatiuForIndexare($spatiu));

        return Inertia::render('IndexareChirii/Index', [
            'spatii' => $spatii,
            'localitati' => Imobil::query()->select('localitate')->distinct()->orderBy('localitate')->pluck('localitate'),
            'filters' => [
                'localitate' => $localitate,
                'search' => $search,
            ],
        ]);
    }

    public function update(Request $request, Spatiu $spatiu): RedirectResponse
    {
        $validated = $request->validate([
            'indexare_2026' => ['nullable', 'numeric', 'min:0'],
        ]);

        $spatiu->update([
            'indexare_2026' => DecimalInput::normalize($validated['indexare_2026'] ?? null),
        ]);

        return back()->with('success', 'Indexarea 2026 a fost salvată.');
    }

    private function mapSpatiuForIndexare(Spatiu $spatiu): array
    {
        $chirieCurenta = $spatiu->indexare_2026 ?: $spatiu->pret_lunar;
        $sursaChirieCurenta = $spatiu->indexare_2026
            ? 'Indexare 2026'
            : ($spatiu->pret_lunar ? 'Chirie lunară' : null);

        return [
            'id' => $spatiu->id,
            'imobil' => $spatiu->imobil?->nume ?: '—',
            'identificator' => $spatiu->identificator,
            'etaj' => $spatiu->etaj ?: '—',
            'suprafata_contractuala_mp' => $spatiu->suprafata_contractuala_mp,
            'pret_lunar' => $spatiu->pret_lunar,
            'indexare_2026' => $spatiu->indexare_2026,
            'chirie_lunara_curenta' => $chirieCurenta,
            'sursa_chirie_curenta' => $sursaChirieCurenta,
            'moneda' => $spatiu->moneda ?: 'EUR',
            'moneda_label' => $spatiu->monedaLabel(),
            'chirias' => $spatiu->chirias ?: '—',
            'status' => $spatiu->status,
        ];
    }
}
