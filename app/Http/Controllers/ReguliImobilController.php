<?php

namespace App\Http\Controllers;

use App\Models\Imobil;
use App\Models\ReguliImobil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReguliImobilController extends Controller
{
    public function index(): Response
    {
        $imobile = Imobil::query()
            ->with('reguli')
            ->orderBy('nume')
            ->get()
            ->map(fn (Imobil $imobil) => [
                'id' => $imobil->id,
                'nume' => $imobil->nume,
                'localitate' => $imobil->localitate,
                'reguli' => $this->ensureReguli($imobil)->only([
                    'metoda_curent',
                    'procent_pierdere_curent',
                    'metoda_apa',
                    'metoda_canalizare',
                    'coeficient_apa_pluviala',
                    'coeficient_apa_pluviala_aprobat',
                    'procent_incalzire_partial',
                    'metoda_spatii_comune',
                    'metoda_retim',
                ]),
            ]);

        return Inertia::render('ReguliImobile/Index', ['imobile' => $imobile]);
    }

    public function update(Request $request, Imobil $imobil): RedirectResponse
    {
        $reguli = $this->ensureReguli($imobil);
        $validated = $request->validate([
            'metoda_curent' => ['required', 'string', 'max:255'],
            'procent_pierdere_curent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'metoda_apa' => ['required', 'string', 'max:255'],
            'metoda_canalizare' => ['required', 'string', 'max:255'],
            'coeficient_apa_pluviala' => ['nullable', 'numeric', 'min:0'],
            'coeficient_apa_pluviala_aprobat' => ['boolean'],
            'procent_incalzire_partial' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'metoda_spatii_comune' => ['required', 'string', 'max:255'],
            'metoda_retim' => ['required', 'string', 'max:255'],
            'motiv' => ['nullable', 'string', 'max:1000'],
        ]);

        $motiv = $validated['motiv'] ?? null;
        unset($validated['motiv']);
        $validated['coeficient_apa_pluviala_aprobat'] = (bool) ($validated['coeficient_apa_pluviala_aprobat'] ?? false);

        foreach ($validated as $field => $newValue) {
            $oldValue = $reguli->{$field};
            if ((string) $oldValue !== (string) $newValue) {
                $reguli->auditLogs()->create([
                    'actiune' => 'actualizare_regula_imobil',
                    'camp' => $field,
                    'valoare_veche' => $oldValue,
                    'valoare_noua' => $newValue,
                    'motiv' => $motiv,
                    'user_name' => 'Owner',
                ]);
            }
        }

        $reguli->update($validated);

        return redirect('/reguli-imobile')->with('success', 'Regulile au fost actualizate.');
    }

    private function ensureReguli(Imobil $imobil): ReguliImobil
    {
        return $imobil->reguli()->firstOrCreate([], [
            'metoda_curent' => 'standard',
            'procent_pierdere_curent' => 0,
            'metoda_apa' => 'contoare_si_persoane',
            'metoda_canalizare' => 'ca_apa',
            'procent_incalzire_partial' => 33,
            'metoda_spatii_comune' => 'sub_50_persoane_peste_50_mp',
            'metoda_retim' => 'persoane',
        ]);
    }
}
