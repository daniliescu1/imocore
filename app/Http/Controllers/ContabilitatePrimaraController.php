<?php

namespace App\Http\Controllers;

use App\Models\Imobil;
use App\Models\Spatiu;
use App\Models\UtilitateLunara;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContabilitatePrimaraController extends Controller
{
    public function index(Request $request): Response
    {
        $luna = $request->string('luna')->toString() ?: now()->format('Y-m');

        $imobile = Imobil::query()->orderBy('nume')->get()->map(function (Imobil $imobil) use ($luna) {
            $spatiiInchiriate = Spatiu::query()->where('imobil_id', $imobil->id)->where('status', 'inchiriat')->get();
            $utilitati = UtilitateLunara::query()->where('imobil_id', $imobil->id)->where('luna', $luna)->get();

            $mpInchiriati = (float) $spatiiInchiriate->sum(fn (Spatiu $spatiu) => (float) $spatiu->suprafata_contractuala_mp);
            $mpIncalziti = (float) $spatiiInchiriate->sum(fn (Spatiu $spatiu) => $this->mpIncalziti($spatiu));
            $persoaneDeclarate = (int) $spatiiInchiriate
                ->filter(fn (Spatiu $spatiu) => $spatiu->status !== 'administrativ')
                ->sum('persoane_declarate');
            $persoaneStandard = (int) $spatiiInchiriate
                ->filter(fn (Spatiu $spatiu) => $spatiu->status !== 'administrativ')
                ->sum(fn (Spatiu $spatiu) => $spatiu->persoane_standard);

            return [
                'id' => $imobil->id,
                'nume' => $imobil->nume,
                'mp_inchiriati' => $mpInchiriati,
                'mp_incalziti' => $mpIncalziti,
                'persoane_declarate' => $persoaneDeclarate,
                'persoane_standard' => $persoaneStandard,
                'diferenta_persoane' => $persoaneStandard - $persoaneDeclarate,
                'total_utilitati' => (float) $utilitati->sum('cost_total'),
                'utilitati_neaprobate' => $utilitati->where('aprobat', false)->count(),
            ];
        });

        return Inertia::render('ContabilitatePrimara/Index', [
            'luna' => $luna,
            'imobile' => $imobile,
        ]);
    }

    private function mpIncalziti(Spatiu $spatiu): float
    {
        $mp = (float) $spatiu->suprafata_contractuala_mp;

        return match ($spatiu->regim_incalzire) {
            'neincalzit' => 0,
            'partial' => $mp * ((float) ($spatiu->procent_incalzire_override ?? 33) / 100),
            'manual' => $mp,
            default => $mp,
        };
    }
}
