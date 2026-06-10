<?php

namespace App\Http\Controllers;

use App\Models\Spatiu;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $totalSpatii = Spatiu::query()->count();
        $spatiiLibere = Spatiu::query()->where('status', 'liber');
        $spatiiInchiriate = Spatiu::query()->where('status', 'inchiriat')->count();

        return Inertia::render('Dashboard', [
            'today' => Carbon::now()->locale('ro')->isoFormat('dddd D MMMM YYYY'),
            'stats' => [
                'total' => $totalSpatii,
                'libere' => (clone $spatiiLibere)->count(),
                'inchiriate' => $spatiiInchiriate,
                'libere_suma' => (float) (clone $spatiiLibere)->sum('pret_lunar'),
                'libere_mp' => (float) (clone $spatiiLibere)->sum('suprafata_contractuala_mp'),
            ],
            'freeSpaces' => (clone $spatiiLibere)
                ->with('imobil')
                ->orderBy('identificator')
                ->limit(10)
                ->get()
                ->map(fn (Spatiu $spatiu) => [
                    'spatiu' => $spatiu->identificator,
                    'imobil' => $spatiu->imobil?->nume ?: '—',
                    'suprafata' => $spatiu->suprafata_contractuala_mp ? "{$spatiu->suprafata_contractuala_mp} mp" : '—',
                    'pret' => $spatiu->pret_lunar ? "{$spatiu->pret_lunar} {$spatiu->moneda}" : '—',
                    'data_liber' => '—',
                ]),
            'overdue' => [],
            'endings' => [],
        ]);
    }
}
