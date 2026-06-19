<?php

namespace App\Http\Controllers;

use App\Models\ConfigurareAnexaImobil;
use App\Models\Factura;
use App\Models\Imobil;
use App\Models\ServiciuStandardAnexa;
use App\Models\SetareAplicatie;
use App\Models\Spatiu;
use App\Support\InternalReturnUrl;
use App\Support\SincronizareContoareDinAnexa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConfigurareAnexaController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedImobilId = $request->integer('imobil_id') ?: null;

        $query = ConfigurareAnexaImobil::query()
            ->with('imobil')
            ->withCount('linii')
            ->orderByDesc('implicit')
            ->orderBy('denumire');

        if ($selectedImobilId) {
            $query->where('imobil_id', $selectedImobilId);
        }

        return Inertia::render('ConfigurareAnexa/Index', [
            'anexe' => $query->get()->map(fn (ConfigurareAnexaImobil $configurare): array => [
                'id' => $configurare->id,
                'denumire' => $configurare->denumire,
                'imobil' => $configurare->imobil?->nume ?: '—',
                'imobil_id' => $configurare->imobil_id,
                'implicit' => $configurare->implicit,
                'activ' => $configurare->activ,
                'linii_count' => $configurare->linii_count,
            ]),
            'imobile' => $this->imobileForSelect(),
            'selectedImobilId' => $selectedImobilId,
            ...$this->cursEurForm(),
        ]);
    }

    public function updateCurs(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'curs_eur' => ['required', 'numeric', 'min:0'],
        ]);

        SetareAplicatie::seteaza('curs_eur_facturare', $validated['curs_eur']);

        return redirect()->back()->with('success', 'Cursul valutar a fost salvat.');
    }

    public function create(Request $request): Response
    {
        $imobilId = $request->integer('imobil_id') ?: null;
        $returnUrl = InternalReturnUrl::normalize($request->string('return_url')->toString());

        return Inertia::render('ConfigurareAnexa/Form', [
            'imobile' => $this->imobileForSelect(),
            'selectedImobilId' => $imobilId,
            'anexa' => null,
            'serviciiStandard' => ServiciuStandardAnexa::optionsForForm(),
            'returnUrl' => $returnUrl,
            'spatiuId' => $request->integer('spatiu_id') ?: null,
            'previewSpatiu' => $this->previewSpatiuForForm(null, $request->integer('spatiu_id') ?: null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'linii' => $this->normalizeLiniiTvaInput($request->input('linii', [])),
        ]);

        $validated = $request->validate($this->validationRules(requireImobil: true));
        $imobil = Imobil::query()->findOrFail($validated['imobil_id']);
        $this->assertDenumireUnicaPeImobil($validated['denumire'], $imobil->id);
        $configurare = $this->saveConfigurare($validated, $imobil);

        $returnUrl = InternalReturnUrl::normalize($request->input('return_url'));
        $spatiuId = $request->integer('spatiu_id') ?: null;

        if ($returnUrl && $spatiuId) {
            Spatiu::query()
                ->whereKey($spatiuId)
                ->where('imobil_id', $imobil->id)
                ->update(['configurare_anexa_id' => $configurare->id]);

            $spatiu = Spatiu::query()->find($spatiuId);
            if ($spatiu) {
                SincronizareContoareDinAnexa::syncForSpatiu($spatiu);
            }

            return redirect($returnUrl)->with('success', 'Anexa a fost adăugată și alocată spațiului.');
        }

        SincronizareContoareDinAnexa::syncForConfigurare($configurare);

        return redirect()
            ->route('configurare-anexa.edit', $configurare)
            ->with('success', 'Anexa a fost adăugată.');
    }

    public function edit(Request $request, ConfigurareAnexaImobil $configurare): Response
    {
        $configurare->load(['imobil', 'linii']);
        $returnUrl = InternalReturnUrl::normalize($request->string('return_url')->toString());
        $spatiiCount = Spatiu::query()->where('configurare_anexa_id', $configurare->id)->count();
        $spatiuId = $request->integer('spatiu_id') ?: null;
        $denumireSugestie = trim($request->string('denumire_sugestie')->toString());
        $isPersonalizare = $request->boolean('personalizare') || trim($configurare->denumire) === '';

        return Inertia::render('ConfigurareAnexa/Form', [
            'imobile' => $this->imobileForSelect(),
            'selectedImobilId' => $configurare->imobil_id,
            'anexa' => $this->configurareForForm($configurare),
            'serviciiStandard' => ServiciuStandardAnexa::optionsForForm(),
            'returnUrl' => $returnUrl,
            'spatiuId' => null,
            'previewSpatiu' => $this->previewSpatiuForForm($configurare, $spatiuId),
            'context' => [
                'spatii_count' => $spatiiCount,
            ],
            'personalizare' => [
                'activ' => $isPersonalizare,
                'denumire_sugestie' => $denumireSugestie !== '' ? $denumireSugestie : null,
            ],
        ]);
    }

    public function update(Request $request, ConfigurareAnexaImobil $configurare): RedirectResponse
    {
        $request->merge([
            'linii' => $this->normalizeLiniiTvaInput($request->input('linii', [])),
        ]);

        $validated = $request->validate($this->validationRules(requireImobil: true));
        $imobil = Imobil::query()->findOrFail($validated['imobil_id']);
        $this->assertDenumireUnicaPeImobil($validated['denumire'], $imobil->id, $configurare->id);
        $configurare = $this->saveConfigurare($validated, $imobil, $configurare);
        SincronizareContoareDinAnexa::syncForConfigurare($configurare);

        $returnUrl = InternalReturnUrl::normalize($request->input('return_url'));

        if ($returnUrl) {
            return redirect($returnUrl)->with('success', 'Anexa a fost actualizată.');
        }

        return redirect()
            ->route('configurare-anexa.edit', $configurare)
            ->with('success', 'Anexa a fost actualizată.');
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

    private function previewSpatiuForForm(?ConfigurareAnexaImobil $configurare, ?int $spatiuId = null): ?array
    {
        $spatiu = null;

        if ($spatiuId) {
            $spatiu = Spatiu::query()->find($spatiuId);
        }

        if (! $spatiu && $configurare) {
            $spatiu = Spatiu::query()
                ->where('configurare_anexa_id', $configurare->id)
                ->orderBy('identificator')
                ->first();
        }

        if (! $spatiu) {
            return null;
        }

        return [
            'id' => $spatiu->id,
            'identificator' => $spatiu->identificator,
            'suprafata_contractuala_mp' => $this->decimalForForm($spatiu->suprafata_contractuala_mp),
            'persoane_pentru_anexa' => $spatiu->persoanePentruAnexa(),
        ];
    }

    private function configurareForForm(ConfigurareAnexaImobil $configurare): array
    {
        return [
            'id' => $configurare->id,
            'imobil_id' => $configurare->imobil_id,
            'denumire' => $configurare->denumire,
            'implicit' => $configurare->implicit,
            'activ' => $configurare->activ,
            'observatii' => $configurare->observatii,
            'linii' => $configurare->linii->map(function ($linie): array {
                $tipCalcul = $this->normalizeTipCalcul($linie->tip_calcul);
                $coeficient = $this->coeficientForForm($linie->coeficient, $linie->index_nou);

                if ($tipCalcul === 'mp_coeficient') {
                    $coeficient = $coeficient ?: '0.09';
                }

                return [
                    'id' => $linie->id,
                    'tip_linie' => $linie->tip_linie ?: 'serviciu',
                    'denumire' => $linie->denumire,
                    'nr_crt' => $linie->nr_crt,
                    'index_vechi' => $this->indexForForm($linie, $tipCalcul),
                    'index_nou' => $this->indexNouForForm($linie, $tipCalcul),
                    'facturat' => $this->facturatForForm($linie, $tipCalcul),
                    'coeficient' => $coeficient,
                    'um' => $linie->um,
                    'pret_unitar' => $linie->pret_unitar,
                    'valoare' => $this->valoareForForm($linie, $tipCalcul),
                    'tva_21' => $this->tvaForForm($linie->tva_21),
                    'tip_calcul' => $tipCalcul,
                    'apare_cu_zero' => $linie->apare_cu_zero,
                    'activ' => $linie->activ,
                    'observatii' => $linie->observatii,
                ];
            })->values(),
        ];
    }

    private function validationRules(bool $requireImobil = false): array
    {
        return [
            'imobil_id' => [$requireImobil ? 'required' : 'nullable', 'exists:imobile,id'],
            'denumire' => ['required', 'string', 'max:255'],
            'implicit' => ['nullable', 'boolean'],
            'activ' => ['nullable', 'boolean'],
            'observatii' => ['nullable', 'string', 'max:1000'],
            'linii' => ['nullable', 'array'],
            'linii.*.id' => ['nullable', 'integer'],
            'linii.*.tip_linie' => ['nullable', 'in:serviciu,header'],
            'linii.*.denumire' => ['nullable', 'string', 'max:255'],
            'linii.*.nr_crt' => ['nullable', 'integer', 'min:0'],
            'linii.*.index_vechi' => ['nullable', 'string', 'max:255'],
            'linii.*.index_nou' => ['nullable', 'string', 'max:255'],
            'linii.*.facturat' => ['nullable', 'numeric'],
            'linii.*.coeficient' => ['nullable', 'numeric'],
            'linii.*.um' => ['nullable', 'string', 'max:50'],
            'linii.*.pret_unitar' => ['nullable', 'numeric'],
            'linii.*.valoare' => ['nullable', 'numeric'],
            'linii.*.tva_21' => ['nullable', 'numeric'],
            'linii.*.tip_calcul' => ['nullable', 'string', 'max:255'],
            'linii.*.apare_cu_zero' => ['nullable', 'boolean'],
            'linii.*.activ' => ['nullable', 'boolean'],
            'linii.*.observatii' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function saveConfigurare(array $data, Imobil $imobil, ?ConfigurareAnexaImobil $configurare = null): ConfigurareAnexaImobil
    {
        if ($configurare && $configurare->imobil_id !== $imobil->id) {
            $configurare->imobil()->associate($imobil);
        }

        $values = [
            'denumire' => trim((string) ($data['denumire'] ?? '')),
            'implicit' => (bool) ($data['implicit'] ?? false),
            'activ' => (bool) ($data['activ'] ?? true),
            'observatii' => $data['observatii'] ?? null,
        ];

        if ($configurare) {
            $configurare->fill($values)->save();
        } else {
            $configurare = $imobil->configurariAnexe()->create($values);
        }

        $lineKeepIds = [];

        foreach (($data['linii'] ?? []) as $index => $linieData) {
            $tipLinie = ($linieData['tip_linie'] ?? 'serviciu') === 'header' ? 'header' : 'serviciu';

            if (! $this->linieConfigurareAreDate($linieData, $tipLinie)) {
                continue;
            }

            $linie = isset($linieData['id'])
                ? $configurare->linii()->whereKey($linieData['id'])->first()
                : null;

            $tipCalcul = $tipLinie === 'header'
                ? 'manual'
                : $this->normalizeTipCalcul($linieData['tip_calcul'] ?? 'manual');

            $lineValues = $this->sanitizeLinieTemplateValues([
                'ordine' => $index + 1,
                'tip_linie' => $tipLinie,
                'denumire' => $tipLinie === 'header' ? '' : trim($linieData['denumire']),
                'nr_crt' => $tipLinie === 'header' ? null : ($linieData['nr_crt'] ?? null),
                'index_vechi' => $tipLinie === 'header' ? null : ($linieData['index_vechi'] ?? null),
                'index_nou' => $tipLinie === 'header' ? null : ($linieData['index_nou'] ?? null),
                'facturat' => $tipLinie === 'header' ? null : ($linieData['facturat'] ?? null),
                'coeficient' => $tipLinie === 'header' ? null : ($linieData['coeficient'] ?? null),
                'um' => $tipLinie === 'header' ? null : ($linieData['um'] ?? null),
                'pret_unitar' => $tipLinie === 'header' ? null : ($linieData['pret_unitar'] ?? null),
                'valoare' => $tipLinie === 'header' ? null : ($linieData['valoare'] ?? null),
                'tva_21' => $tipLinie === 'header' ? null : $this->normalizeTvaForSave($linieData['tva_21'] ?? null),
                'tip_calcul' => $tipCalcul,
                'apare_cu_zero' => $tipLinie === 'header' ? false : (bool) ($linieData['apare_cu_zero'] ?? true),
                'activ' => $tipLinie === 'header' ? true : (bool) ($linieData['activ'] ?? true),
                'observatii' => $tipLinie === 'header' ? null : ($linieData['observatii'] ?? null),
            ], $tipCalcul, $tipLinie);

            if ($linie) {
                $linie->update($lineValues);
            } else {
                $linie = $configurare->linii()->create($lineValues);
            }

            $lineKeepIds[] = $linie->id;
        }

        $configurare->linii()->whereNotIn('id', $lineKeepIds)->delete();

        if ($configurare->implicit) {
            $imobil->configurariAnexe()->where('id', '!=', $configurare->id)->update(['implicit' => false]);
        } elseif (! $imobil->configurariAnexe()->where('implicit', true)->exists()) {
            $configurare->update(['implicit' => true]);
        }

        return $configurare->refresh();
    }

    private function linieConfigurareAreDate(array $linieData, string $tipLinie): bool
    {
        if ($tipLinie === 'header') {
            return true;
        }

        if (trim((string) ($linieData['denumire'] ?? '')) !== '') {
            return true;
        }

        return $this->normalizeTipCalcul($linieData['tip_calcul'] ?? '') === 'mp_coeficient'
            && trim((string) ($linieData['coeficient'] ?? '')) !== '';
    }

    private function normalizeTipCalcul(?string $tipCalcul): string
    {
        $tipCalcul = trim((string) $tipCalcul);
        $normalized = str_replace([' ', '*', '×', '_', '-'], '', strtolower($tipCalcul));

        if (str_starts_with($normalized, 'mp') && str_contains($normalized, 'coeficient')) {
            return 'mp_coeficient';
        }

        return $tipCalcul ?: 'manual';
    }

    private function coeficientForForm(null|string|int|float $coeficient, null|string|int|float $fallbackIndexNou = null): string
    {
        $coeficientNormalizat = $this->decimalForForm($coeficient);

        if ($coeficientNormalizat !== '' && (float) $coeficientNormalizat > 0 && (float) $coeficientNormalizat <= 1) {
            return $coeficientNormalizat;
        }

        $fallback = (float) str_replace(',', '.', (string) $fallbackIndexNou);

        if ($fallback > 0 && $fallback <= 1) {
            return $this->decimalForForm($fallbackIndexNou);
        }

        return '';
    }

    private function decimalForForm(null|string|int|float $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return rtrim(rtrim(str_replace(',', '.', (string) $value), '0'), '.');
    }

    private function tvaForForm(null|string|int|float $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return ServiciuStandardAnexa::normalizeValoare(
            ServiciuStandardAnexa::TIP_TVA,
            (string) $value
        );
    }

    private function normalizeTvaForSave(null|string|int|float $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = ServiciuStandardAnexa::normalizeValoare(
            ServiciuStandardAnexa::TIP_TVA,
            (string) $value
        );

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeLiniiTvaInput(array $linii): array
    {
        return array_map(function (array $linie): array {
            if (array_key_exists('tva_21', $linie)) {
                $linie['tva_21'] = $this->normalizeTvaForSave($linie['tva_21']);
            }

            return $linie;
        }, $linii);
    }

    private function assertDenumireUnicaPeImobil(string $denumire, int $imobilId, ?int $ignoreId = null): void
    {
        $normalized = mb_strtolower(trim($denumire));

        if ($normalized === '') {
            return;
        }

        $duplicate = ConfigurareAnexaImobil::query()
            ->where('imobil_id', $imobilId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get()
            ->first(fn (ConfigurareAnexaImobil $configurare): bool => mb_strtolower(trim($configurare->denumire)) === $normalized);

        if ($duplicate) {
            throw ValidationException::withMessages([
                'denumire' => 'Există deja o anexă «'.trim($denumire).'». Alege alt nume.',
            ]);
        }
    }

    private function sanitizeLinieTemplateValues(array $lineValues, string $tipCalcul, string $tipLinie): array
    {
        if ($tipLinie === 'header') {
            return $lineValues;
        }

        $tip = $this->normalizeTipCalcul($tipCalcul);

        if ($tip === 'contor' || in_array($tip, ['mp', 'pe_mp'], true) || $tip === 'persoane' || $tip === 'mp_coeficient') {
            $lineValues['index_vechi'] = null;
            $lineValues['index_nou'] = null;
            $lineValues['facturat'] = null;
            $lineValues['valoare'] = null;
        }

        if ($tip === 'fix') {
            $lineValues['index_vechi'] = null;
            $lineValues['index_nou'] = null;
        }

        return $lineValues;
    }

    private function indexForForm($linie, string $tipCalcul): mixed
    {
        if ($this->linieTemplateFaraCantitati($tipCalcul)) {
            return '';
        }

        return $linie->index_vechi;
    }

    private function indexNouForForm($linie, string $tipCalcul): mixed
    {
        if ($this->linieTemplateFaraCantitati($tipCalcul)) {
            return '';
        }

        return $linie->index_nou;
    }

    private function facturatForForm($linie, string $tipCalcul): mixed
    {
        if ($this->linieTemplateFaraCantitati($tipCalcul)) {
            return '';
        }

        return $linie->facturat;
    }

    private function valoareForForm($linie, string $tipCalcul): mixed
    {
        if ($this->linieTemplateFaraCantitati($tipCalcul)) {
            return '';
        }

        return $linie->valoare;
    }

    private function linieTemplateFaraCantitati(string $tipCalcul): bool
    {
        $tip = $this->normalizeTipCalcul($tipCalcul);

        return $tip === 'contor'
            || in_array($tip, ['mp', 'pe_mp'], true)
            || $tip === 'persoane'
            || $tip === 'mp_coeficient';
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
