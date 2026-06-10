<?php

namespace App\Http\Controllers;

use App\Models\Spatiu;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $totalSpatii = Spatiu::query()->count();
        $spatiiLibere = Spatiu::query()->where('status', 'liber');
        $spatiiInchiriateQuery = Spatiu::query()->inchiriat();
        $isOwner = $this->isOwner($request->user());

        $stats = [
            'total' => $totalSpatii,
            'libere' => (clone $spatiiLibere)->count(),
            'inchiriate' => (clone $spatiiInchiriateQuery)->count(),
            'libere_suma' => (float) (clone $spatiiLibere)->sum('pret_lunar'),
            'libere_mp' => (float) (clone $spatiiLibere)->sum('suprafata_contractuala_mp'),
        ];

        if ($isOwner) {
            $spatiiInchiriate = (clone $spatiiInchiriateQuery)->get();

            $stats['inchiriate_suma'] = (float) $spatiiInchiriate->sum(
                fn (Spatiu $spatiu): float => $spatiu->chirieCurenta()
            );
            $stats['inchiriate_mp'] = (float) $spatiiInchiriate->sum('suprafata_contractuala_mp');
        }

        return Inertia::render('Dashboard', [
            'today' => Carbon::now()->locale('ro')->isoFormat('dddd D MMMM YYYY'),
            'stats' => $stats,
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

    private function isOwner(?User $user): bool
    {
        if ($user === null) {
            return true;
        }

        return $user->isOwner();
    }
}
