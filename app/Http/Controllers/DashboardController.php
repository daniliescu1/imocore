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
        ];

        $stats = array_merge($stats, $this->sumStatsByMoneda((clone $spatiiLibere)->get(), 'libere'));

        if ($isOwner) {
            $stats = array_merge($stats, $this->sumStatsByMoneda((clone $spatiiInchiriateQuery)->get(), 'inchiriate'));
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
                    'pret' => $spatiu->pret_lunar ? "{$spatiu->pret_lunar} {$spatiu->monedaLabel()}" : '—',
                    'data_liber' => '—',
                ]),
            'overdue' => [],
            'endings' => [],
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Spatiu>  $spatii
     * @return array<string, float>
     */
    private function sumStatsByMoneda($spatii, string $prefix): array
    {
        $eur = $spatii->filter(fn (Spatiu $spatiu): bool => $spatiu->moneda !== 'RON');
        $lei = $spatii->filter(fn (Spatiu $spatiu): bool => $spatiu->moneda === 'RON');

        return [
            "{$prefix}_suma_eur" => (float) $eur->sum(fn (Spatiu $spatiu): float => $this->chiriePentruStat($spatiu)),
            "{$prefix}_mp_eur" => (float) $eur->sum('suprafata_contractuala_mp'),
            "{$prefix}_suma_lei" => (float) $lei->sum(fn (Spatiu $spatiu): float => $this->chiriePentruStat($spatiu)),
            "{$prefix}_mp_lei" => (float) $lei->sum('suprafata_contractuala_mp'),
        ];
    }

    private function chiriePentruStat(Spatiu $spatiu): float
    {
        if ($spatiu->status === 'liber') {
            return (float) ($spatiu->pret_lunar ?: 0);
        }

        return $spatiu->chirieCurenta();
    }

    private function isOwner(?User $user): bool
    {
        if ($user === null) {
            return true;
        }

        return $user->isOwner();
    }
}
