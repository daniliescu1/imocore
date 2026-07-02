<?php

namespace App\Http\Controllers;

use App\Models\ConfigurareAnexaLinie;
use App\Models\Factura;
use App\Models\ServiciuStandardAnexa;
use App\Models\SetareAplicatie;
use App\Support\CoeficientCantitatePret;
use App\Support\PretServiciuStandard;
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
                ->orderBy('valoare')
                ->orderBy('ordine')
                ->orderBy('label')
                ->get()
                ->map(fn (ServiciuStandardAnexa $item): array => [
                    'id' => $item->id,
                    'valoare' => $tip === ServiciuStandardAnexa::TIP_TVA
                        ? ServiciuStandardAnexa::normalizeValoare($tip, $item->valoare)
                        : $item->valoare,
                    'denumire' => $tip === ServiciuStandardAnexa::TIP_PRET ? $item->valoare : null,
                    'label' => match ($tip) {
                        ServiciuStandardAnexa::TIP_TVA => ServiciuStandardAnexa::tvaLabel($item->valoare),
                        ServiciuStandardAnexa::TIP_PRET => ServiciuStandardAnexa::variantLabel($item),
                        default => $item->label ?: $item->valoare,
                    },
                    'coeficient' => $item->coeficient,
                    'coeficient_cantitate' => $tip === ServiciuStandardAnexa::TIP_PRET
                        ? CoeficientCantitatePret::toPercentForForm($item->coeficient_cantitate)
                        : null,
                    'moneda' => $tip === ServiciuStandardAnexa::TIP_PRET
                        ? PretServiciuStandard::normalizeMoneda($item->moneda)
                        : null,
                    'tva' => $tip === ServiciuStandardAnexa::TIP_PRET && $item->tva
                        ? ServiciuStandardAnexa::normalizeValoare(ServiciuStandardAnexa::TIP_TVA, (string) $item->tva)
                        : null,
                    'um' => $tip === ServiciuStandardAnexa::TIP_PRET && $item->um
                        ? trim((string) $item->um)
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
            'umOptiuni' => $tip === ServiciuStandardAnexa::TIP_PRET
                ? ServiciuStandardAnexa::query()
                    ->where('tip', ServiciuStandardAnexa::TIP_UM)
                    ->where('activ', true)
                    ->orderBy('ordine')
                    ->orderBy('label')
                    ->get()
                    ->map(fn (ServiciuStandardAnexa $item): array => [
                        'valoare' => $item->valoare,
                        'label' => $item->label ?: $item->valoare,
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
            'preturi.*.label' => ['nullable', 'string', 'max:255'],
            'preturi.*.coeficient' => ['nullable', 'string', 'max:32'],
            'preturi.*.coeficient_cantitate' => ['nullable', 'string', 'max:32'],
            'preturi.*.moneda' => ['nullable', 'string', 'in:RON,EUR'],
            'preturi.*.tva' => ['nullable', 'string', 'max:16'],
            'preturi.*.um' => ['nullable', 'string', 'max:32'],
        ]);

        $curs = PretServiciuStandard::cursEur();

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

            $um = trim((string) ($pret['um'] ?? ''));
            $um = $um === '' ? null : $um;
            $moneda = PretServiciuStandard::normalizeMoneda($pret['moneda'] ?? $item->moneda);
            $label = trim((string) ($pret['label'] ?? $item->label ?? 'Standard'));
            $label = $label === '' ? 'Standard' : $label;
            $coeficientCantitate = CoeficientCantitatePret::normalizeForSave($pret['coeficient_cantitate'] ?? $item->coeficient_cantitate);

            $item->update([
                'label' => $label,
                'coeficient' => $coeficient,
                'coeficient_cantitate' => $coeficientCantitate,
                'moneda' => $moneda,
                'tva' => $tva,
                'um' => $um,
            ]);

            PretServiciuStandard::propagateToLinii(
                $item->fresh(),
                $coeficient,
                $moneda,
                $tva,
                $um,
                $curs,
            );
        }

        return redirect()
            ->route('configurare-anexa.servicii-standard.index', ['tip' => ServiciuStandardAnexa::TIP_PRET])
            ->with('success', 'Prețurile au fost salvate.');
    }

    public function storePretVariant(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'valoare' => ['required', 'string', 'max:255'],
            'label' => ['required', 'string', 'max:255'],
            'coeficient' => ['nullable', 'string', 'max:32'],
            'coeficient_cantitate' => ['nullable', 'string', 'max:32'],
            'moneda' => ['nullable', 'string', 'in:RON,EUR'],
            'tva' => ['nullable', 'string', 'max:16'],
            'um' => ['nullable', 'string', 'max:32'],
        ]);

        $denumire = trim($validated['valoare']);
        abort_unless(
            ServiciuStandardAnexa::query()
                ->where('tip', ServiciuStandardAnexa::TIP_DENUMIRE)
                ->where('valoare', $denumire)
                ->where('activ', true)
                ->exists(),
            422,
            'Denumirea serviciului nu există în catalog.',
        );

        $label = trim($validated['label']);
        abort_unless(
            ! ServiciuStandardAnexa::query()
                ->where('tip', ServiciuStandardAnexa::TIP_PRET)
                ->where('valoare', $denumire)
                ->where('label', $label)
                ->exists(),
            422,
            'Există deja o variantă cu acest nume pentru serviciul ales.',
        );

        $raw = trim(str_replace(',', '.', (string) ($validated['coeficient'] ?? '')));
        $coeficient = $raw === '' ? null : ($raw && is_numeric($raw) ? $raw : null);
        $tva = trim((string) ($validated['tva'] ?? ''));
        $tva = $tva === ''
            ? null
            : ServiciuStandardAnexa::normalizeValoare(ServiciuStandardAnexa::TIP_TVA, $tva);
        $um = trim((string) ($validated['um'] ?? ''));
        $um = $um === '' ? null : $um;

        ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_PRET,
            'valoare' => $denumire,
            'label' => $label,
            'coeficient' => $coeficient,
            'coeficient_cantitate' => CoeficientCantitatePret::normalizeForSave($validated['coeficient_cantitate'] ?? null),
            'moneda' => PretServiciuStandard::normalizeMoneda($validated['moneda'] ?? null),
            'tva' => $tva,
            'um' => $um,
            'activ' => true,
        ]);

        return redirect()
            ->route('configurare-anexa.servicii-standard.index', ['tip' => ServiciuStandardAnexa::TIP_PRET])
            ->with('success', 'Varianta de preț a fost adăugată.');
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
        $label = $this->labelForStore($tip, $valoare, $validated['label'] ?? null);

        $record = ServiciuStandardAnexa::query()->updateOrCreate(
            ['tip' => $tip, 'valoare' => $valoare, 'label' => $label],
            [
                'coeficient' => $coeficient,
                'activ' => true,
            ]
        );

        if ($tip === ServiciuStandardAnexa::TIP_PRET && $coeficient !== null) {
            PretServiciuStandard::propagateToLinii(
                $record->fresh(),
                $coeficient,
                PretServiciuStandard::MONEDA_RON,
                null,
                null,
            );
        }

        if ($tip === ServiciuStandardAnexa::TIP_DENUMIRE) {
            ServiciuStandardAnexa::query()->firstOrCreate(
                ['tip' => ServiciuStandardAnexa::TIP_PRET, 'valoare' => $valoare, 'label' => 'Standard'],
                ['coeficient_cantitate' => 1, 'activ' => true]
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
            'moneda' => [$tip === ServiciuStandardAnexa::TIP_PRET ? 'nullable' : 'prohibited', 'string', 'in:RON,EUR'],
            'tva' => [$tip === ServiciuStandardAnexa::TIP_PRET ? 'nullable' : 'prohibited', 'string', 'max:16'],
            'um' => [$tip === ServiciuStandardAnexa::TIP_PRET ? 'nullable' : 'prohibited', 'string', 'max:32'],
            'activ' => ['nullable', 'boolean'],
        ]);

        $valoareNoua = ServiciuStandardAnexa::normalizeValoare($tip, $validated['valoare']);
        $valoareVeche = $serviciuStandard->valoare;
        $coeficient = $this->coeficientForStore($tip, $valoareNoua, $validated['coeficient'] ?? null);
        $tva = $tip === ServiciuStandardAnexa::TIP_PRET
            ? $this->tvaForPretStore($validated['tva'] ?? null)
            : null;
        $um = $tip === ServiciuStandardAnexa::TIP_PRET
            ? $this->umForPretStore($validated['um'] ?? null)
            : null;
        $moneda = $tip === ServiciuStandardAnexa::TIP_PRET
            ? PretServiciuStandard::normalizeMoneda($validated['moneda'] ?? $serviciuStandard->moneda)
            : null;

        if ($valoareNoua !== $valoareVeche && $this->esteFolosit($tip, $valoareVeche)) {
            $this->actualizeazaLiniiConfigurate($tip, $valoareVeche, $valoareNoua);
        }

        if ($tip === ServiciuStandardAnexa::TIP_TIP_CALCUL && $valoareNoua === 'mp_coeficient' && $coeficient !== null) {
            ConfigurareAnexaLinie::query()
                ->where('tip_calcul', 'mp_coeficient')
                ->update(['coeficient' => $coeficient]);
        }

        if ($tip === ServiciuStandardAnexa::TIP_DENUMIRE && $valoareNoua !== $valoareVeche) {
            ServiciuStandardAnexa::query()
                ->where('tip', ServiciuStandardAnexa::TIP_PRET)
                ->where('valoare', $valoareVeche)
                ->update(['valoare' => $valoareNoua]);
        }

        $updateValues = [
            'valoare' => $valoareNoua,
            'label' => $this->labelForStore($tip, $valoareNoua, $validated['label'] ?? null),
            'coeficient' => $coeficient,
            'activ' => (bool) ($validated['activ'] ?? $serviciuStandard->activ),
        ];

        if ($tip === ServiciuStandardAnexa::TIP_PRET) {
            $updateValues['moneda'] = $moneda;
            $updateValues['tva'] = $tva;
            $updateValues['um'] = $um;
        }

        $serviciuStandard->update($updateValues);

        if ($tip === ServiciuStandardAnexa::TIP_PRET) {
            PretServiciuStandard::propagateToLinii(
                $serviciuStandard->fresh(),
                $coeficient,
                $moneda,
                $tva,
                $um,
            );
        }

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

        if ($tip === ServiciuStandardAnexa::TIP_PRET) {
            $variantsCount = ServiciuStandardAnexa::query()
                ->where('tip', ServiciuStandardAnexa::TIP_PRET)
                ->where('valoare', $serviciuStandard->valoare)
                ->where('activ', true)
                ->count();

            if ($variantsCount <= 1) {
                return redirect()
                    ->route('configurare-anexa.servicii-standard.index', ['tip' => $tip])
                    ->with('warning', 'Nu poți șterge singura variantă de preț a serviciului.');
            }

            if ($serviciuStandard->liniiConfigurare()->exists()) {
                return redirect()
                    ->route('configurare-anexa.servicii-standard.index', ['tip' => $tip])
                    ->with('warning', 'Varianta e folosită în anexe configurate și nu poate fi ștearsă.');
            }

            $serviciuStandard->delete();

            return redirect()
                ->route('configurare-anexa.servicii-standard.index', ['tip' => $tip])
                ->with('success', 'Varianta de preț a fost ștearsă.');
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

    private function umForPretStore(mixed $um): ?string
    {
        $um = trim((string) ($um ?? ''));

        return $um === '' ? null : $um;
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
