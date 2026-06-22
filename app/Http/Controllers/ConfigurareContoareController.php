<?php

namespace App\Http\Controllers;

use App\Models\CitireContor;
use App\Models\ConfigurareAnexaLinie;
use App\Models\ContorConfigurabil;
use App\Models\Imobil;
use App\Models\Spatiu;
use App\Support\ContorConfigurabilCalculator;
use App\Support\ContorConfigurabilSync;
use App\Support\TipCalculAnexa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ConfigurareContoareController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ConfigurareContoare/Index', [
            'imobile' => $this->imobileCuContoareConfigurabile(),
        ]);
    }

    public function imobil(Imobil $imobil): Response
    {
        ContorConfigurabilSync::syncForImobil($imobil->id);

        $contoare = ContorConfigurabil::query()
            ->with(['configurareAnexaLinie', 'configurareAnexa'])
            ->where('imobil_id', $imobil->id)
            ->orderBy('configurare_anexa_id')
            ->orderBy('id')
            ->get()
            ->map(fn (ContorConfigurabil $regula): array => $this->mapRegulaLista($regula));

        return Inertia::render('ConfigurareContoare/Imobil', [
            'imobil' => [
                'id' => $imobil->id,
                'nume' => $imobil->nume,
                'localitate' => $imobil->localitate,
            ],
            'contoare' => $contoare,
        ]);
    }

    public function contor(Imobil $imobil, ContorConfigurabil $contorConfigurabil): Response
    {
        abort_unless((int) $contorConfigurabil->imobil_id === (int) $imobil->id, 404);

        ContorConfigurabilSync::syncForImobil($imobil->id);
        $contorConfigurabil->refresh()->load(['configurareAnexaLinie', 'configurareAnexa']);

        return Inertia::render('ConfigurareContoare/Contor', [
            'imobil' => [
                'id' => $imobil->id,
                'nume' => $imobil->nume,
                'localitate' => $imobil->localitate,
            ],
            'contor' => $this->mapRegula($contorConfigurabil),
        ]);
    }

    public function update(Request $request, ContorConfigurabil $contorConfigurabil): RedirectResponse
    {
        $validated = $request->validate([
            'foloseste_scaderi' => ['required', 'boolean'],
            'scaderi' => ['nullable', 'array'],
            'scaderi.*.spatiu_id' => ['required', 'integer', 'exists:spatii,id'],
            'scaderi.*.configurare_anexa_linie_id' => ['required', 'integer', 'exists:configurare_anexa_linii,id'],
            'alocari' => [Rule::requiredIf(fn (): bool => $request->boolean('foloseste_scaderi')), 'nullable', 'array', 'min:1'],
            'alocari.*' => ['required', 'integer', 'exists:spatii,id'],
        ]);

        $spatiiAnexa = Spatiu::query()
            ->where('imobil_id', $contorConfigurabil->imobil_id)
            ->where('configurare_anexa_id', $contorConfigurabil->configurare_anexa_id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $folosesteScaderi = (bool) $validated['foloseste_scaderi'];
        $alocari = $folosesteScaderi
            ? array_values(array_unique(array_map('intval', $validated['alocari'] ?? [])))
            : $spatiiAnexa;

        foreach ($alocari as $spatiuId) {
            abort_unless(in_array((int) $spatiuId, $spatiiAnexa, true), 422, 'Spațiile alocate trebuie să aibă aceeași anexă ca contorul configurabil.');
        }

        $scaderi = [];

        if ($folosesteScaderi) {
            foreach ($validated['scaderi'] ?? [] as $scadere) {
                abort_unless(in_array((int) $scadere['spatiu_id'], $spatiiAnexa, true), 422, 'Scăderile trebuie să folosească spații cu aceeași anexă ca contorul configurabil.');

                $linieScadere = ConfigurareAnexaLinie::query()->find((int) $scadere['configurare_anexa_linie_id']);

                abort_unless(
                    $linieScadere
                    && (int) $linieScadere->configurare_anexa_id === (int) $contorConfigurabil->configurare_anexa_id
                    && TipCalculAnexa::isContor($linieScadere->tip_calcul),
                    422,
                    'Scăderile trebuie să folosească servicii de tip Contor din aceeași anexă.',
                );
            }

            $scaderi = array_values($validated['scaderi'] ?? []);
        }

        $contorConfigurabil->update([
            'foloseste_scaderi' => $folosesteScaderi,
            'scaderi' => $scaderi,
            'alocari' => $alocari,
        ]);

        return redirect()
            ->route('configurare-contoare.contor', [
                'imobil' => $contorConfigurabil->imobil_id,
                'contorConfigurabil' => $contorConfigurabil->id,
            ])
            ->with('success', 'Regula contorului configurabil a fost salvată.');
    }

    /**
     * @return list<array{id: int, nume: string, localitate: string, contoare_configurabile_count: int}>
     */
    private function imobileCuContoareConfigurabile(): array
    {
        return Imobil::query()
            ->whereHas('spatii', function ($query): void {
                $query->whereNotNull('configurare_anexa_id')
                    ->whereHas('configurareAnexa.linii', fn ($linii) => TipCalculAnexa::applyLiniiConfigurareContoareScope($linii));
            })
            ->orderBy('nume')
            ->get(['id', 'nume', 'localitate'])
            ->map(fn (Imobil $imobil): array => [
                'id' => $imobil->id,
                'nume' => $imobil->nume,
                'localitate' => $imobil->localitate,
                'contoare_configurabile_count' => $this->contoareConfigurabileCountForImobil($imobil->id),
            ])
            ->filter(fn (array $imobil): bool => $imobil['contoare_configurabile_count'] > 0)
            ->values()
            ->all();
    }

    private function contoareConfigurabileCountForImobil(int $imobilId): int
    {
        return ConfigurareAnexaLinie::query()
            ->whereHas('configurare', fn ($query) => $query
                ->where('imobil_id', $imobilId)
                ->whereHas('spatii'))
            ->where(function ($query): void {
                TipCalculAnexa::applyLiniiConfigurareContoareScope($query);
            })
            ->count();
    }

    private function mapRegulaLista(ContorConfigurabil $regula): array
    {
        $linie = $regula->configurareAnexaLinie;
        $anexa = $regula->configurareAnexa;
        $alocari = $regula->alocariEfectiveIds();

        return [
            'id' => $regula->id,
            'denumire' => $linie?->denumire ?: '—',
            'anexa' => $anexa?->denumire ?: '—',
            'um' => $linie?->um ?: '—',
            'tip_calcul' => $linie?->tip_calcul ?: '—',
            'tip_label' => $this->tipCalculLabel($linie?->tip_calcul),
            'configurata' => $alocari !== [],
            'alocari_count' => count($alocari),
            'ultima_citire' => $this->ultimaCitirePentruRegula($regula->configurare_anexa_linie_id, $linie?->tip_calcul),
        ];
    }

    private function mapRegula(ContorConfigurabil $regula): array
    {
        $linie = $regula->configurareAnexaLinie;
        $anexa = $regula->configurareAnexa;
        $spatiiAnexaIds = $this->spatiiIdsForAnexa($regula->configurare_anexa_id);
        $liniiContorIds = $this->liniiContorIdsForAnexa($regula->configurare_anexa_id);

        $ultimaCitire = $this->ultimaCitirePentruRegula($regula->configurare_anexa_linie_id, $linie?->tip_calcul);
        $alocari = $regula->alocariEfectiveIds();

        return [
            'id' => $regula->id,
            'denumire' => $linie?->denumire ?: '—',
            'anexa' => $anexa?->denumire ?: '—',
            'um' => $linie?->um ?: '—',
            'tip_calcul' => $linie?->tip_calcul ?: '—',
            'tip_label' => $this->tipCalculLabel($linie?->tip_calcul),
            'is_pausal' => TipCalculAnexa::isPausal($linie?->tip_calcul),
            'configurare_anexa_id' => $regula->configurare_anexa_id,
            'configurare_anexa_linie_id' => $regula->configurare_anexa_linie_id,
            'foloseste_scaderi' => (bool) $regula->foloseste_scaderi,
            'scaderi' => collect($regula->scaderiNormalizate())
                ->filter(fn (array $scadere): bool => in_array($scadere['spatiu_id'], $spatiiAnexaIds, true)
                    && in_array($scadere['configurare_anexa_linie_id'], $liniiContorIds, true))
                ->values()
                ->all(),
            'alocari' => $alocari,
            'configurata' => $alocari !== [],
            'formula' => $regula->foloseste_scaderi
                ? (TipCalculAnexa::isPausal($linie?->tip_calcul)
                    ? '(cantitate pausal − sumă scăderi) / nr. spații alocate'
                    : '(consum contor − sumă scăderi) / nr. spații alocate')
                : (TipCalculAnexa::isPausal($linie?->tip_calcul)
                    ? 'cantitate pausal / nr. spații anexă'
                    : 'consum contor / nr. spații anexă'),
            'spatiiOptions' => $this->spatiiOptionsForAnexa($regula->configurare_anexa_id),
            'liniiScadereOptions' => $this->liniiScadereOptionsForAnexa($regula->configurare_anexa_id),
            'citiriScadere' => $this->citiriScaderePentruAnexa($regula->configurare_anexa_id, $ultimaCitire['luna'] ?? null),
            'ultima_citire' => $ultimaCitire,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function citiriScaderePentruAnexa(int $configurareAnexaId, ?string $luna): array
    {
        if ($luna === null || $luna === '') {
            return [];
        }

        $lunaFacturare = ContorConfigurabilCalculator::lunaFacturareDinUtilitati($luna);
        $citiri = [];

        foreach ($this->spatiiIdsForAnexa($configurareAnexaId) as $spatiuId) {
            foreach ($this->liniiContorIdsForAnexa($configurareAnexaId) as $linieId) {
                $consum = ContorConfigurabilCalculator::consumCitireSpatiu(
                    $spatiuId,
                    $linieId,
                    $luna,
                    $lunaFacturare,
                );

                if ($consum > 0) {
                    $citiri["{$spatiuId}-{$linieId}"] = $consum;
                }
            }
        }

        return $citiri;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ultimaCitirePentruRegula(int $linieId, ?string $tipCalcul = null): ?array
    {
        $citire = CitireContor::query()
            ->whereNull('spatiu_id')
            ->where('configurare_anexa_linie_id', $linieId)
            ->orderByDesc('luna')
            ->first();

        if (! $citire) {
            return null;
        }

        $isPausal = TipCalculAnexa::isPausal($tipCalcul);

        return [
            'luna' => $citire->luna,
            'luna_label' => substr($citire->luna, 5, 2).'.'.substr($citire->luna, 0, 4),
            'index_vechi' => $isPausal ? null : $citire->index_vechi,
            'index_nou' => $isPausal ? null : $citire->index_nou,
            'consum' => $citire->consum,
            'data_citire' => $citire->data_citire?->format('d.m.Y H:i'),
            'is_pausal' => $isPausal,
        ];
    }

    /**
     * @return list<int>
     */
    private function spatiiIdsForAnexa(int $configurareAnexaId): array
    {
        return Spatiu::query()
            ->where('configurare_anexa_id', $configurareAnexaId)
            ->orderBy('identificator')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function spatiiOptionsForAnexa(int $configurareAnexaId): array
    {
        return Spatiu::query()
            ->where('configurare_anexa_id', $configurareAnexaId)
            ->orderBy('identificator')
            ->get(['id', 'identificator', 'chirias'])
            ->map(fn (Spatiu $spatiu): array => [
                'id' => $spatiu->id,
                'label' => trim($spatiu->identificator.' · '.($spatiu->chirias ?: '—')),
            ])
            ->all();
    }

    /**
     * @return list<int>
     */
    private function liniiContorIdsForAnexa(int $configurareAnexaId): array
    {
        return ConfigurareAnexaLinie::query()
            ->where('configurare_anexa_id', $configurareAnexaId)
            ->where(function ($query): void {
                $query->whereRaw('lower(trim(tip_calcul)) = ?', ['contor']);
            })
            ->where(function ($query): void {
                $query->whereNull('tip_linie')
                    ->orWhere('tip_linie', '!=', 'header');
            })
            ->where('activ', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<array{spatiu_id: int, spatiu_label: string, linii: list<array{id: int, label: string}>}>
     */
    private function liniiScadereOptionsForAnexa(int $configurareAnexaId): array
    {
        $liniiContor = ConfigurareAnexaLinie::query()
            ->where('configurare_anexa_id', $configurareAnexaId)
            ->where(function ($query): void {
                $query->whereRaw('lower(trim(tip_calcul)) = ?', ['contor']);
            })
            ->where(function ($query): void {
                $query->whereNull('tip_linie')
                    ->orWhere('tip_linie', '!=', 'header');
            })
            ->where('activ', true)
            ->orderBy('ordine')
            ->orderBy('id')
            ->get(['id', 'denumire', 'tip_calcul'])
            ->map(fn (ConfigurareAnexaLinie $linie): array => [
                'id' => $linie->id,
                'label' => trim($linie->denumire.' · '.$this->tipCalculLabel($linie->tip_calcul)),
                'tip_calcul' => $linie->tip_calcul,
            ])
            ->values()
            ->all();

        if ($liniiContor === []) {
            return [];
        }

        return Spatiu::query()
            ->where('configurare_anexa_id', $configurareAnexaId)
            ->orderBy('identificator')
            ->get(['id', 'identificator'])
            ->map(fn (Spatiu $spatiu): array => [
                'spatiu_id' => $spatiu->id,
                'spatiu_label' => $spatiu->identificator,
                'linii' => $liniiContor,
            ])
            ->values()
            ->all();
    }

    private function tipCalculLabel(?string $tipCalcul): string
    {
        return match (TipCalculAnexa::normalize($tipCalcul)) {
            'contor' => 'Contor',
            'pausal' => 'Pausal',
            'contor_configurabil' => 'Contor configurabil',
            'mp_coeficient' => 'Mp × coeficient',
            'mp' => 'Pe mp',
            'persoane' => 'Pe persoane',
            'manual' => 'Manual',
            default => ucfirst(trim((string) $tipCalcul)) ?: 'Manual',
        };
    }
}
