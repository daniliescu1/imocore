<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Imobil;
use App\Models\Locator;
use App\Models\Spatiu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function index(): Response
    {
        $contracte = Contract::query()
            ->with('spatiu.imobil')
            ->latest()
            ->get()
            ->map(fn (Contract $contract): array => [
                'id' => $contract->id,
                'numar_contract' => $contract->numar_contract,
                'imobil' => $contract->spatiu?->imobil?->nume ?: '—',
                'spatiu' => $contract->spatiu?->identificator ?: '—',
                'chirias' => $contract->chirias,
                'chirie' => $contract->chirie,
                'moneda' => $contract->moneda,
                'perioada' => optional($contract->data_start)->format('d.m.Y').' - '.(optional($contract->data_end)->format('d.m.Y') ?: 'nedeterminat'),
                'status' => $contract->status,
            ]);

        return Inertia::render('Contracte/Index', [
            'contracte' => $contracte,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Contracte/Form', [
            ...$this->formProps(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $persoaneDeclarate = $validated['persoane_declarate'] ?? null;
        unset($validated['persoane_declarate']);

        $contract = Contract::query()->create($validated);
        $this->updateLocatorNume($contract->spatiu, $request->string('locator_nume')->toString());

        $spatiu = $contract->spatiu;
        $esteAdministrativ = $spatiu->status === 'administrativ';
        $spatiu->update([
            'status' => $esteAdministrativ ? 'administrativ' : 'inchiriat',
            'chirias' => $contract->chirias,
            'persoane_declarate' => $esteAdministrativ ? null : $persoaneDeclarate,
        ]);
        $contract->spatiu->imobil->recalculeazaSpatii();

        return redirect('/contracte')->with('success', 'Contractul a fost adăugat.');
    }

    public function edit(Contract $contract): Response
    {
        $contract->load('spatiu.locatorEntitate');

        return Inertia::render('Contracte/Form', [
            ...$this->formProps(),
            'contract' => [
                'id' => $contract->id,
                'imobil_id' => $contract->spatiu?->imobil_id,
                'spatiu_id' => $contract->spatiu_id,
                'locator_nume' => $contract->spatiu?->locatorEntitate?->nume,
                'numar_contract' => $contract->numar_contract,
                'chirias' => $contract->chirias,
                'persoane_declarate' => $contract->spatiu?->persoane_declarate,
                'data_start' => optional($contract->data_start)->format('Y-m-d'),
                'data_end' => optional($contract->data_end)->format('Y-m-d'),
                'chirie' => $contract->chirie,
                'moneda' => $contract->moneda,
                'status' => $contract->status,
                'observatii' => $contract->observatii,
            ],
        ]);
    }

    public function update(Request $request, Contract $contract): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $persoaneDeclarate = $validated['persoane_declarate'] ?? null;
        unset($validated['persoane_declarate']);

        $contract->update($validated);
        $this->updateLocatorNume($contract->spatiu, $request->string('locator_nume')->toString());

        $spatiu = $contract->spatiu;
        $esteAdministrativ = $spatiu->status === 'administrativ';
        $spatiu->update([
            'status' => $esteAdministrativ
                ? 'administrativ'
                : ($contract->status === 'activ' ? 'inchiriat' : $spatiu->status),
            'chirias' => $contract->chirias,
            'persoane_declarate' => $esteAdministrativ ? null : $persoaneDeclarate,
        ]);
        $contract->spatiu->imobil->recalculeazaSpatii();

        return redirect('/contracte')->with('success', 'Contractul a fost actualizat.');
    }

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'spatiu_id' => ['required', 'exists:spatii,id'],
            'locator_nume' => ['nullable', 'string', 'max:255'],
            'numar_contract' => ['required', 'string', 'max:255'],
            'chirias' => ['required', 'string', 'max:255'],
            'persoane_declarate' => ['nullable', 'integer', 'min:0'],
            'data_start' => ['required', 'date'],
            'data_end' => ['nullable', 'date'],
            'chirie' => ['required', 'numeric', 'min:0'],
            'moneda' => ['nullable', 'string', 'size:3'],
            'status' => ['required', 'string', 'max:255'],
            'observatii' => ['nullable', 'string', 'max:5000'],
        ]);

        $validated['moneda'] = 'EUR';
        $validated['persoane_declarate'] = isset($validated['persoane_declarate'])
            ? (int) $validated['persoane_declarate']
            : null;
        unset($validated['locator_nume']);

        return $validated;
    }

    private function updateLocatorNume(Spatiu $spatiu, ?string $nume): void
    {
        $nume = trim((string) $nume);

        if ($nume === '') {
            return;
        }

        if ($spatiu->locator_id) {
            Locator::query()->whereKey($spatiu->locator_id)->update(['nume' => $nume]);
            $spatiu->update(['locator' => $nume]);
            return;
        }

        $locator = Locator::query()->firstOrCreate(['nume' => $nume]);
        $spatiu->update([
            'locator_id' => $locator->id,
            'locator' => $locator->nume,
        ]);
    }

    private function formProps(): array
    {
        return [
            'imobile' => Imobil::query()
                ->orderBy('nume')
                ->get(['id', 'nume', 'localitate'])
                ->map(fn (Imobil $imobil): array => [
                    'id' => $imobil->id,
                    'label' => "{$imobil->nume} ({$imobil->localitate})",
                ]),
            'spatii' => Spatiu::query()
                ->with(['imobil', 'configurareAnexa', 'locatorEntitate'])
                ->orderBy('identificator')
                ->get()
                ->map(function (Spatiu $spatiu): array {
                    $suprafata = $spatiu->suprafata_contractuala_mp;
                    $chirieCurenta = $spatiu->indexare_2026 ?: ($spatiu->indexare_2025 ?: $spatiu->pret_lunar);

                    return [
                        'id' => $spatiu->id,
                        'imobil_id' => $spatiu->imobil_id,
                        'label' => "{$spatiu->identificator} - {$spatiu->imobil?->nume}",
                        'identificator' => $spatiu->identificator,
                        'status' => $spatiu->status,
                        'locator_nume' => $spatiu->locatorEntitate?->nume ?: $spatiu->getAttribute('locator'),
                        'chirias' => $spatiu->chirias,
                        'persoane_declarate' => $spatiu->persoane_declarate,
                        'suprafata_contractuala_mp' => $suprafata,
                        'pret_lunar' => $spatiu->pret_lunar,
                        'indexare_2025' => $spatiu->indexare_2025,
                        'indexare_2026' => $spatiu->indexare_2026,
                        'chirie_curenta' => $chirieCurenta,
                        'moneda' => $spatiu->moneda ?: 'EUR',
                        'configurare_anexa' => $spatiu->configurareAnexa?->denumire,
                    ];
                }),
        ];
    }
}
