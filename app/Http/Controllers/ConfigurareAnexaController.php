<?php

namespace App\Http\Controllers;

use App\Models\ConfigurareAnexaImobil;
use App\Models\Imobil;
use App\Models\ServiciuStandardAnexa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
    }

    public function create(Request $request): Response
    {
        $imobilId = $request->integer('imobil_id') ?: null;

        return Inertia::render('ConfigurareAnexa/Form', [
            'imobile' => $this->imobileForSelect(),
            'selectedImobilId' => $imobilId,
            'anexa' => null,
            'serviciiStandard' => ServiciuStandardAnexa::optionsForForm(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'linii' => $this->normalizeLiniiTvaInput($request->input('linii', [])),
        ]);

        $validated = $request->validate($this->validationRules(requireImobil: true));
        $imobil = Imobil::query()->findOrFail($validated['imobil_id']);
        $configurare = $this->saveConfigurare($validated, $imobil);

        return redirect()
            ->route('configurare-anexa.edit', $configurare)
            ->with('success', 'Anexa a fost adăugată.');
    }

    public function edit(ConfigurareAnexaImobil $configurare): Response
    {
        $configurare->load(['imobil', 'linii']);

        return Inertia::render('ConfigurareAnexa/Form', [
            'imobile' => $this->imobileForSelect(),
            'selectedImobilId' => $configurare->imobil_id,
            'anexa' => $this->configurareForForm($configurare),
            'serviciiStandard' => ServiciuStandardAnexa::optionsForForm(),
        ]);
    }

    public function update(Request $request, ConfigurareAnexaImobil $configurare): RedirectResponse
    {
        $request->merge([
            'linii' => $this->normalizeLiniiTvaInput($request->input('linii', [])),
        ]);

        $validated = $request->validate($this->validationRules(requireImobil: true));
        $imobil = Imobil::query()->findOrFail($validated['imobil_id']);
        $configurare = $this->saveConfigurare($validated, $imobil, $configurare);

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

    private function configurareForForm(ConfigurareAnexaImobil $configurare): array
    {
        return [
            'id' => $configurare->id,
            'imobil_id' => $configurare->imobil_id,
            'denumire' => $configurare->denumire,
            'implicit' => $configurare->implicit,
            'activ' => $configurare->activ,
            'observatii' => $configurare->observatii,
            'linii' => $configurare->linii->map(fn ($linie) => [
                'id' => $linie->id,
                'tip_linie' => $linie->tip_linie ?: 'serviciu',
                'denumire' => $linie->denumire,
                'nr_crt' => $linie->nr_crt,
                'index_vechi' => $linie->index_vechi,
                'index_nou' => $linie->index_nou,
                'facturat' => $linie->facturat,
                'coeficient' => $this->decimalForForm($linie->coeficient),
                'um' => $linie->um,
                'pret_unitar' => $linie->pret_unitar,
                'valoare' => $linie->valoare,
                'tva_21' => $this->tvaForForm($linie->tva_21),
                'tip_calcul' => $linie->tip_calcul,
                'apare_cu_zero' => $linie->apare_cu_zero,
                'activ' => $linie->activ,
                'observatii' => $linie->observatii,
            ])->values(),
        ];
    }

    private function validationRules(bool $requireImobil = false): array
    {
        return [
            'imobil_id' => [$requireImobil ? 'required' : 'nullable', 'exists:imobile,id'],
            'denumire' => ['nullable', 'string', 'max:255'],
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
            'denumire' => $this->denumireConfigurare($data),
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

            $lineValues = [
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
                'tip_calcul' => $tipLinie === 'header' ? 'manual' : ($linieData['tip_calcul'] ?? 'manual'),
                'apare_cu_zero' => $tipLinie === 'header' ? false : (bool) ($linieData['apare_cu_zero'] ?? true),
                'activ' => $tipLinie === 'header' ? true : (bool) ($linieData['activ'] ?? true),
                'observatii' => $tipLinie === 'header' ? null : ($linieData['observatii'] ?? null),
            ];

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

        return ($linieData['tip_calcul'] ?? '') === 'mp_coeficient'
            && trim((string) ($linieData['coeficient'] ?? '')) !== '';
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

    private function denumireConfigurare(array $configurare): string
    {
        $denumire = trim((string) ($configurare['denumire'] ?? ''));

        return $denumire !== '' ? $denumire : 'Anexă imobil';
    }
}
