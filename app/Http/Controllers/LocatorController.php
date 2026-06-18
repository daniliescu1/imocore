<?php

namespace App\Http\Controllers;

use App\Models\Imobil;
use App\Models\Locator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LocatorController extends Controller
{
    public function index(): Response
    {
        $locatori = Locator::query()
            ->with(['imobil', 'spatii.imobil'])
            ->withCount('spatii')
            ->orderBy('nume')
            ->get()
            ->map(fn (Locator $locator): array => [
                'id' => $locator->id,
                'nume' => $locator->nume,
                'cui' => ($locator->cui_are_ro ? 'RO' : '').($locator->cui ?: ''),
                'chirie_cu_tva' => $locator->chirie_cu_tva ? 'Cu TVA' : 'Fără TVA',
                'imobil' => $locator->imobil?->nume ?: 'Global',
                'spatii_count' => $locator->spatii_count,
                'spatii' => $locator->spatii
                    ->map(fn ($spatiu): string => "{$spatiu->identificator} - {$spatiu->imobil?->nume}")
                    ->implode(', '),
            ]);

        return Inertia::render('Locatori/Index', [
            'locatori' => $locatori,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Locatori/Form', [
            'imobile' => $this->imobileForSelect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Locator::query()->create($this->validatedData($request));

        return redirect('/locatori')->with('success', 'Locatorul a fost adăugat.');
    }

    public function edit(Locator $locator): Response
    {
        return Inertia::render('Locatori/Form', [
            'locator' => [
                'id' => $locator->id,
                'nume' => $locator->nume,
                'imobil_id' => $locator->imobil_id,
                'cui_are_ro' => $locator->cui_are_ro,
                'cui' => $locator->cui,
                'registrul_comertului' => $locator->registrul_comertului,
                'adresa' => $locator->adresa,
                'banca' => $locator->banca,
                'cont_bancar' => $locator->cont_bancar,
                'email' => $locator->email,
                'chirie_cu_tva' => $locator->chirie_cu_tva,
            ],
            'imobile' => $this->imobileForSelect(),
        ]);
    }

    public function update(Request $request, Locator $locator): RedirectResponse
    {
        $locator->update($this->validatedData($request, $locator));

        return redirect('/locatori')->with('success', 'Locatorul a fost actualizat.');
    }

    private function validatedData(Request $request, ?Locator $locator = null): array
    {
        return $request->validate([
            'nume' => [
                'required',
                'string',
                'max:255',
                Rule::unique('locatori', 'nume')->ignore($locator?->id),
            ],
            'imobil_id' => ['nullable', 'exists:imobile,id'],
            'cui_are_ro' => ['boolean'],
            'cui' => ['nullable', 'string', 'max:50'],
            'registrul_comertului' => ['nullable', 'string', 'max:255'],
            'adresa' => ['nullable', 'string', 'max:500'],
            'banca' => ['nullable', 'string', 'max:255'],
            'cont_bancar' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'chirie_cu_tva' => ['boolean'],
        ]);
    }

    private function imobileForSelect()
    {
        return Imobil::query()
            ->orderBy('nume')
            ->get(['id', 'nume', 'localitate'])
            ->map(fn (Imobil $imobil): array => [
                'id' => $imobil->id,
                'label' => "{$imobil->nume} ({$imobil->localitate})",
            ]);
    }
}
