<?php

namespace App\Http\Controllers;

use App\Models\ConfigurareAnexaLinie;
use App\Models\Factura;
use App\Models\ServiciuStandardAnexa;
use App\Models\SetareAplicatie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiciuStandardAnexaController extends Controller
{
    public function index(string $tip): Response|RedirectResponse
    {
        if (! in_array($tip, ServiciuStandardAnexa::TIPURI, true)) {
            return redirect()->route('configurare-anexa.servicii-standard.index', [
                'tip' => ServiciuStandardAnexa::TIP_DENUMIRE,
            ]);
        }

        if (ServiciuStandardAnexa::query()->count() === 0) {
            ServiciuStandardAnexa::importFromExistingLines();
        }

        if ($tip === ServiciuStandardAnexa::TIP_PRET) {
            ServiciuStandardAnexa::syncPreturiFromDenumire();
        }

        return Inertia::render('ConfigurareAnexa/ServiciiStandard', [
            'tipActiv' => $tip,
            'tipuri' => collect(ServiciuStandardAnexa::TIPURI)
                ->map(fn (string $tipItem): array => [
                    'key' => $tipItem,
                    'label' => ServiciuStandardAnexa::labelForTip($tipItem),
                    'href' => route('configurare-anexa.servicii-standard.index', ['tip' => $tipItem]),
                ])
                ->all(),
            'valori' => ServiciuStandardAnexa::query()
                ->where('tip', $tip)
                ->where('activ', true)
                ->orderBy('ordine')
                ->orderBy('label')
                ->get()
                ->map(fn (ServiciuStandardAnexa $item): array => [
                    'id' => $item->id,
                    'valoare' => $tip === ServiciuStandardAnexa::TIP_TVA
                        ? ServiciuStandardAnexa::normalizeValoare($tip, $item->valoare)
                        : $item->valoare,
                    'label' => match ($tip) {
                        ServiciuStandardAnexa::TIP_TVA => ServiciuStandardAnexa::tvaLabel($item->valoare),
                        ServiciuStandardAnexa::TIP_PRET => $item->label ?: $item->valoare,
                        default => $item->label ?: $item->valoare,
                    },
                    'coeficient' => $item->coeficient,
                    'tva' => $tip === ServiciuStandardAnexa::TIP_PRET && $item->tva
                        ? ServiciuStandardAnexa::normalizeValoare(ServiciuStandardAnexa::TIP_TVA, (string) $item->tva)
                        : null,
                ]),
            'tvaOptiuni' => $tip === ServiciuStandardAnexa::TIP_PRET
                ? ServiciuStandardAnexa::query()
                    ->where('tip', ServiciuStandardAnexa::TIP_TVA)
                    ->where('activ', true)
                    ->orderBy('ordine')
                    ->orderBy('label')
                    ->get()
                    ->map(fn (ServiciuStandardAnexa $item): array => [
                        'valoare' => ServiciuStandardAnexa::normalizeValoare(ServiciuStandardAnexa::TIP_TVA, $item->valoare),
                        'label' => ServiciuStandardAnexa::tvaLabel($item->valoare),
                    ])
                    ->values()
                    ->all()
                : [],
            ...$this->cursEurForm(),
        ]);
    }

    public function updateBulkPreturi(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preturi' => ['required', 'array'],
            'preturi.*.id' => ['required', 'integer'],
            'preturi.*.coeficient' => ['nullable', 'string', 'max:32'],
            'preturi.*.tva' => ['nullable', 'string', 'max:16'],
        ]);

        foreach ($validated['preturi'] as $pret) {
            $item = ServiciuStandardAnexa::query()
                ->where('tip', ServiciuStandardAnexa::TIP_PRET)
                ->where('id', $pret['id'])
                ->first();

            if (! $item) {
                continue;
            }

            $raw = trim(str_replace(',', '.', (string) ($pret['coeficient'] ?? '')));
            $coeficient = $raw === '' ? null : $raw;

            if ($coeficient !== null && ! is_numeric($coeficient)) {
                continue;
            }

            $tva = trim((string) ($pret['tva'] ?? ''));
            $tva = $tva === ''
                ? null
                : ServiciuStandardAnexa::normalizeValoare(ServiciuStandardAnexa::TIP_TVA, $tva);

            $item->update([
                'coeficient' => $coeficient,
                'tva' => $tva,
            ]);

            ConfigurareAnexaLinie::query()
                ->where('denumire', $item->valoare)
                ->update([
                    'pret_unitar' => $coeficient,
                    'tva_21' => $tva,
                ]);
        }

        return redirect()
            ->route('configurare-anexa.servicii-standard.index', ['tip' => ServiciuStandardAnexa::TIP_PRET])
            ->with('success', 'Prețurile au fost salvate.');
    }

    public function store(Request $request, string $tip): RedirectResponse
    {
        $this->assertTipValid($tip);

        $validated = $request->validate([
            'valoare' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'coeficient' => [$tip === ServiciuStandardAnexa::TIP_PRET ? 'required' : 'nullable', 'numeric', 'min:0'],
        ]);

        if ($tip === ServiciuStandardAnexa::TIP_PRET && ! ServiciuStandardAnexa::query()
            ->where('tip', ServiciuStandardAnexa::TIP_DENUMIRE)
            ->where('valoare', $validated['valoare'])
            ->where('activ', true)
            ->exists()) {
            return redirect()
                ->route('configurare-anexa.servicii-standard.index', ['tip' => $tip])
                ->with('warning', 'Prețul trebuie setat pentru o denumire de serviciu existentă.');
        }

        $valoare = ServiciuStandardAnexa::normalizeValoare($tip, $validated['valoare']);
        $coeficient = $this->coeficientForStore($tip, $valoare, $validated['coeficient'] ?? null);

        ServiciuStandardAnexa::query()->updateOrCreate(
            ['tip' => $tip, 'valoare' => $valoare],
            [
                'label' => $this->labelForStore($tip, $valoare, $validated['label'] ?? null),
                'coeficient' => $coeficient,
                'activ' => true,
            ]
        );

        if ($tip === ServiciuStandardAnexa::TIP_PRET && $coeficient !== null) {
            ConfigurareAnexaLinie::query()
                ->where('denumire', $valoare)
                ->update(['pret_unitar' => $coeficient]);
        }

        if ($tip === ServiciuStandardAnexa::TIP_DENUMIRE) {
            ServiciuStandardAnexa::query()->firstOrCreate(
                ['tip' => ServiciuStandardAnexa::TIP_PRET, 'valoare' => $valoare],
                ['label' => $this->labelForStore($tip, $valoare, $validated['label'] ?? null), 'activ' => true]
            );
        }

        return redirect()
            ->route('configurare-anexa.servicii-standard.index', ['tip' => $tip])
            ->with('success', 'Valoarea a fost adăugată.');
    }

    public function update(Request $request, string $tip, ServiciuStandardAnexa $serviciuStandard): RedirectResponse
    {
        $this->assertTipValid($tip);

        if ($serviciuStandard->tip !== $tip) {
            abort(404);
        }

        $validated = $request->validate([
            'valoare' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'coeficient' => [$tip === ServiciuStandardAnexa::TIP_PRET ? 'required' : 'nullable', 'numeric', 'min:0'],
            'tva' => [$tip === ServiciuStandardAnexa::TIP_PRET ? 'nullable' : 'prohibited', 'string', 'max:16'],
            'activ' => ['nullable', 'boolean'],
        ]);

        $valoareNoua = ServiciuStandardAnexa::normalizeValoare($tip, $validated['valoare']);
        $valoareVeche = $serviciuStandard->valoare;
        $coeficient = $this->coeficientForStore($tip, $valoareNoua, $validated['coeficient'] ?? null);
        $tva = $tip === ServiciuStandardAnexa::TIP_PRET
            ? $this->tvaForPretStore($validated['tva'] ?? null)
            : null;

        if ($valoareNoua !== $valoareVeche && $this->esteFolosit($tip, $valoareVeche)) {
            $this->actualizeazaLiniiConfigurate($tip, $valoareVeche, $valoareNoua);
        }

        if ($tip === ServiciuStandardAnexa::TIP_TIP_CALCUL && $valoareNoua === 'mp_coeficient' && $coeficient !== null) {
            ConfigurareAnexaLinie::query()
                ->where('tip_calcul', 'mp_coeficient')
                ->update(['coeficient' => $coeficient]);
        }

        if ($tip === ServiciuStandardAnexa::TIP_PRET) {
            $linieUpdates = array_filter([
                'pret_unitar' => $coeficient,
                'tva_21' => $tva,
            ], fn ($value) => $value !== null);

            if ($linieUpdates !== []) {
                ConfigurareAnexaLinie::query()
                    ->where('denumire', $valoareNoua)
                    ->update($linieUpdates);
            }
        }

        if ($tip === ServiciuStandardAnexa::TIP_DENUMIRE && $valoareNoua !== $valoareVeche) {
            ServiciuStandardAnexa::query()
                ->where('tip', ServiciuStandardAnexa::TIP_PRET)
                ->where('valoare', $valoareVeche)
                ->update([
                    'valoare' => $valoareNoua,
                    'label' => $this->labelForStore($tip, $valoareNoua, $validated['label'] ?? null),
                ]);
        }

        $serviciuStandard->update([
            'valoare' => $valoareNoua,
            'label' => $this->labelForStore($tip, $valoareNoua, $validated['label'] ?? null),
            'coeficient' => $coeficient,
            'tva' => $tva,
            'activ' => (bool) ($validated['activ'] ?? $serviciuStandard->activ),
        ]);

        return redirect()
            ->route('configurare-anexa.servicii-standard.index', ['tip' => $tip])
            ->with('success', 'Valoarea a fost actualizată.');
    }

    public function destroy(string $tip, ServiciuStandardAnexa $serviciuStandard): RedirectResponse
    {
        $this->assertTipValid($tip);

        if ($serviciuStandard->tip !== $tip) {
            abort(404);
        }

        if ($this->esteFolosit($tip, $serviciuStandard->valoare)) {
            $serviciuStandard->update(['activ' => false]);

            return redirect()
                ->route('configurare-anexa.servicii-standard.index', ['tip' => $tip])
                ->with('warning', 'Valoarea este folosită în anexe configurate și a fost marcată inactivă.');
        }

        $serviciuStandard->delete();

        return redirect()
            ->route('configurare-anexa.servicii-standard.index', ['tip' => $tip])
            ->with('success', 'Valoarea a fost ștearsă.');
    }

    private function assertTipValid(string $tip): void
    {
        if (! in_array($tip, ServiciuStandardAnexa::TIPURI, true)) {
            abort(404);
        }
    }

    private function labelForStore(string $tip, string $valoare, ?string $label): string
    {
        if ($label !== null && trim($label) !== '') {
            return trim($label);
        }

        if ($tip === ServiciuStandardAnexa::TIP_TVA) {
            return ServiciuStandardAnexa::tvaLabel($valoare);
        }

        if ($tip === ServiciuStandardAnexa::TIP_PRET) {
            return $valoare;
        }

        return ServiciuStandardAnexa::tipCalculDefaults()[$valoare] ?? $valoare;
    }

    private function tvaForPretStore(mixed $tva): ?string
    {
        $tva = trim((string) ($tva ?? ''));

        if ($tva === '') {
            return null;
        }

        return ServiciuStandardAnexa::normalizeValoare(ServiciuStandardAnexa::TIP_TVA, $tva);
    }

    private function coeficientForStore(string $tip, string $valoare, mixed $coeficient): ?string
    {
        if ($tip === ServiciuStandardAnexa::TIP_TIP_CALCUL && $valoare === 'mp_coeficient') {
            if ($coeficient === null || trim((string) $coeficient) === '') {
                return null;
            }

            return (string) $coeficient;
        }

        if ($tip === ServiciuStandardAnexa::TIP_PRET) {
            if ($coeficient === null || trim((string) $coeficient) === '') {
                return null;
            }

            return (string) $coeficient;
        }

        return null;
    }

    private function esteFolosit(string $tip, string $valoare): bool
    {
        return match ($tip) {
            ServiciuStandardAnexa::TIP_DENUMIRE => ConfigurareAnexaLinie::query()->where('denumire', $valoare)->exists(),
            ServiciuStandardAnexa::TIP_UM => ConfigurareAnexaLinie::query()->where('um', $valoare)->exists(),
            ServiciuStandardAnexa::TIP_TVA => ConfigurareAnexaLinie::query()->where('tva_21', $valoare)->exists(),
            ServiciuStandardAnexa::TIP_TIP_CALCUL => ConfigurareAnexaLinie::query()->where('tip_calcul', $valoare)->exists(),
            ServiciuStandardAnexa::TIP_PRET => ConfigurareAnexaLinie::query()->where('denumire', $valoare)->exists(),
            default => false,
        };
    }

    private function actualizeazaLiniiConfigurate(string $tip, string $valoareVeche, string $valoareNoua): void
    {
        $query = ConfigurareAnexaLinie::query();

        match ($tip) {
            ServiciuStandardAnexa::TIP_DENUMIRE => $query->where('denumire', $valoareVeche)->update(['denumire' => $valoareNoua]),
            ServiciuStandardAnexa::TIP_UM => $query->where('um', $valoareVeche)->update(['um' => $valoareNoua]),
            ServiciuStandardAnexa::TIP_TVA => $query->where('tva_21', $valoareVeche)->update(['tva_21' => $valoareNoua]),
            ServiciuStandardAnexa::TIP_TIP_CALCUL => $query->where('tip_calcul', $valoareVeche)->update(['tip_calcul' => $valoareNoua]),
            ServiciuStandardAnexa::TIP_PRET => $query->where('denumire', $valoareVeche)->update(['denumire' => $valoareNoua]),
            default => null,
        };
    }

    private function cursEurForm(): array
    {
        $cursSalvat = SetareAplicatie::valoare('curs_eur_facturare');

        if ($cursSalvat) {
            return [
                'cursImplicit' => $cursSalvat,
                'cursSursa' => 'Curs introdus manual',
            ];
        }

        return [
            'cursImplicit' => Factura::query()->latest()->value('curs_eur') ?: 5,
            'cursSursa' => 'Ultimul curs salvat / fallback',
        ];
    }
}
