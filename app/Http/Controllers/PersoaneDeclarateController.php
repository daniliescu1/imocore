<?php

namespace App\Http\Controllers;

use App\Models\Imobil;
use App\Models\Spatiu;
use App\Support\StrictSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersoaneDeclarateController extends Controller
{
    public function index(Request $request): Response
    {
        $localitate = $request->string('localitate')->toString();
        $search = $request->string('search')->toString();
        $declarate = $request->string('declarate')->toString();

        if (! in_array($declarate, ['', 'declarate', 'ne_declarate'], true)) {
            $declarate = '';
        }

        $query = Spatiu::query()
            ->with('imobil')
            ->join('imobile', 'imobile.id', '=', 'spatii.imobil_id')
            ->select('spatii.*')
            ->where('spatii.status', 'inchiriat')
            ->orderBy('imobile.ordine')
            ->orderBy('imobile.id')
            ->orderBy('spatii.ordine')
            ->orderBy('spatii.id');

        if ($localitate !== '') {
            $query->where('imobile.localitate', $localitate);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                StrictSearch::whereColumnContains($query, 'spatii.identificator', $search);
                StrictSearch::orWhereColumnContains($query, 'spatii.chirias', $search);
                StrictSearch::orWhereColumnContains($query, 'imobile.nume', $search);
            });
        }

        if ($declarate === 'declarate') {
            $query->whereNotNull('spatii.persoane_declarate');
        } elseif ($declarate === 'ne_declarate') {
            $query->whereNull('spatii.persoane_declarate');
        }

        $spatiiCollection = $query->get();
        $spatii = $spatiiCollection->map(fn (Spatiu $spatiu): array => $this->mapSpatiuForPersoaneDeclarate($spatiu));

        return Inertia::render('PersoaneDeclarate/Index', [
            'spatii' => $spatii,
            'localitati' => Imobil::query()->select('localitate')->distinct()->orderBy('localitate')->pluck('localitate'),
            'filters' => [
                'localitate' => $localitate,
                'search' => $search,
                'declarate' => $declarate,
            ],
            'rezumat' => [
                'spatii_inchiriate' => $spatiiCollection->count(),
                'spatii_cu_persoane_declarate' => $spatiiCollection
                    ->filter(fn (Spatiu $spatiu): bool => $spatiu->persoane_declarate !== null)
                    ->count(),
            ],
        ]);
    }

    public function update(Request $request, Spatiu $spatiu): RedirectResponse
    {
        $validated = $request->validate([
            'persoane_declarate' => ['nullable', 'integer', 'min:0'],
        ]);

        $persoaneDeclarate = filled($validated['persoane_declarate'] ?? null)
            ? (int) $validated['persoane_declarate']
            : null;

        $spatiu->update([
            'persoane_declarate' => $persoaneDeclarate,
        ]);

        return back()->with('success', 'Persoanele declarate au fost salvate.');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSpatiuForPersoaneDeclarate(Spatiu $spatiu): array
    {
        return [
            'id' => $spatiu->id,
            'imobil' => $spatiu->imobil?->nume ?: '—',
            'identificator' => $spatiu->identificator,
            'etaj' => $spatiu->etaj ?: '—',
            'persoane_calculate_automat' => $spatiu->persoane_standard,
            'persoane_declarate' => $spatiu->persoane_declarate,
            'chirias' => $spatiu->chirias ?: '—',
            'suprafata_contractuala_mp' => $spatiu->suprafata_contractuala_mp,
            'status' => $spatiu->status,
        ];
    }
}
