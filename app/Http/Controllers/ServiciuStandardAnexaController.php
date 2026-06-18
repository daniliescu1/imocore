<?php

namespace App\Http\Controllers;

use App\Models\ConfigurareAnexaLinie;
use App\Models\ServiciuStandardAnexa;
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
                    'label' => $tip === ServiciuStandardAnexa::TIP_TVA
                        ? ServiciuStandardAnexa::tvaLabel($item->valoare)
                        : ($item->label ?: $item->valoare),
                    'coeficient' => $item->coeficient,
                ]),
        ]);
    }

    public function store(Request $request, string $tip): RedirectResponse
    {
        $this->assertTipValid($tip);

        $validated = $request->validate([
            'valoare' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'coeficient' => ['nullable', 'numeric', 'min:0'],
        ]);

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
            'coeficient' => ['nullable', 'numeric', 'min:0'],
            'activ' => ['nullable', 'boolean'],
        ]);

        $valoareNoua = ServiciuStandardAnexa::normalizeValoare($tip, $validated['valoare']);
        $valoareVeche = $serviciuStandard->valoare;
        $coeficient = $this->coeficientForStore($tip, $valoareNoua, $validated['coeficient'] ?? null);

        if ($valoareNoua !== $valoareVeche && $this->esteFolosit($tip, $valoareVeche)) {
            $this->actualizeazaLiniiConfigurate($tip, $valoareVeche, $valoareNoua);
        }

        if ($tip === ServiciuStandardAnexa::TIP_TIP_CALCUL && $valoareNoua === 'mp_coeficient' && $coeficient !== null) {
            ConfigurareAnexaLinie::query()
                ->where('tip_calcul', 'mp_coeficient')
                ->update(['coeficient' => $coeficient]);
        }

        $serviciuStandard->update([
            'valoare' => $valoareNoua,
            'label' => $this->labelForStore($tip, $valoareNoua, $validated['label'] ?? null),
            'coeficient' => $coeficient,
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

        return ServiciuStandardAnexa::tipCalculDefaults()[$valoare] ?? $valoare;
    }

    private function coeficientForStore(string $tip, string $valoare, mixed $coeficient): ?string
    {
        if ($tip !== ServiciuStandardAnexa::TIP_TIP_CALCUL || $valoare !== 'mp_coeficient') {
            return null;
        }

        if ($coeficient === null || trim((string) $coeficient) === '') {
            return null;
        }

        return (string) $coeficient;
    }

    private function esteFolosit(string $tip, string $valoare): bool
    {
        return match ($tip) {
            ServiciuStandardAnexa::TIP_DENUMIRE => ConfigurareAnexaLinie::query()->where('denumire', $valoare)->exists(),
            ServiciuStandardAnexa::TIP_UM => ConfigurareAnexaLinie::query()->where('um', $valoare)->exists(),
            ServiciuStandardAnexa::TIP_TVA => ConfigurareAnexaLinie::query()->where('tva_21', $valoare)->exists(),
            ServiciuStandardAnexa::TIP_TIP_CALCUL => ConfigurareAnexaLinie::query()->where('tip_calcul', $valoare)->exists(),
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
            default => null,
        };
    }
}
