<?php

namespace App\Http\Controllers;

use App\Models\CitireContor;
use App\Models\CitireContorLunaInchisa;
use App\Models\ConfigurareAnexaLinie;
use App\Models\Imobil;
use App\Models\Spatiu;
use App\Support\TipCalculAnexa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CitireContorController extends Controller
{
    public function index(): Response
    {
        $imobile = Imobil::query()
            ->orderBy('nume')
            ->get(['id', 'nume', 'localitate'])
            ->map(fn (Imobil $imobil): array => [
                'id' => $imobil->id,
                'nume' => $imobil->nume,
                'localitate' => $imobil->localitate,
                'contoare_count' => $this->contoareCountForImobil($imobil->id),
                'ultima_luna_citita' => $this->ultimaLunaCititaForImobil($imobil->id),
            ]);

        return Inertia::render('CitiriContoare/Index', [
            'imobile' => $imobile,
        ]);
    }

    public function imobil(Request $request, Imobil $imobil): Response
    {
        $luniCitite = $this->luniCititePentruImobil($imobil);
        $mode = $request->string('mode')->toString() === 'new' ? 'new' : 'history';
        $lunaCeruta = $request->string('luna')->toString();
        $luniCititeValues = collect($luniCitite)->pluck('luna');
        $luna = $lunaCeruta
            ?: ($mode === 'new' ? $this->lunaUrmatoare($luniCitite[0]['luna'] ?? null) : ($luniCitite[0]['luna'] ?? now()->format('Y-m')));

        if ($mode === 'history' && ! $luniCititeValues->contains($luna)) {
            $mode = 'new';
        }

        $dataCitire = $request->string('data_citire')->toString()
            ?: ($mode === 'history' ? ($this->dataCitirePentruLuna($imobil, $luna) ?: "{$luna}-20T".now()->format('H:i')) : "{$luna}-20T".now()->format('H:i'));
        $lunaInchisa = $this->lunaInchisa($imobil->id, $luna);
        $spatii = $this->spatiiCuCitiriPentruImobil($imobil, $luna, $lunaInchisa);

        return Inertia::render('CitiriContoare/Imobil', [
            'imobil' => [
                'id' => $imobil->id,
                'nume' => $imobil->nume,
                'localitate' => $imobil->localitate,
            ],
            'luna' => $luna,
            'dataCitire' => $dataCitire,
            'mode' => $mode,
            'readOnly' => $lunaInchisa,
            'lunaInchisa' => $lunaInchisa,
            'areCitiriSalvate' => $this->areCitiriSalvatePentruLuna($imobil->id, $luna),
            'luniCitite' => $luniCitite,
            'luniSelectabile' => $this->luniSelectabile(),
            'spatii' => $spatii,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCitiriRequest($request);

        if ($redirect = $this->redirectDacaLunaInchisa($validated)) {
            return $redirect;
        }

        $citiri = $this->filtreazaCitiriEditabile($validated);

        if ($citiri === []) {
            return redirect()
                ->route('citiri-contoare.imobil', [
                    'imobil' => $validated['imobil_id'],
                    'luna' => $validated['luna'],
                    'mode' => 'history',
                ])
                ->with('warning', 'Nu există linii de citit care pot fi salvate pentru luna selectată.');
        }

        $this->persistCitiri(
            (int) $validated['imobil_id'],
            $validated['luna'],
            $validated['data_citire'],
            $citiri,
        );

        return redirect()
            ->route('citiri-contoare.imobil', [
                'imobil' => $validated['imobil_id'],
                'data_citire' => $validated['data_citire'],
                'luna' => $validated['luna'],
            ])
            ->with('success', 'Citirile contoarelor au fost salvate.');
    }

    public function inchide(Request $request): RedirectResponse
    {
        $validated = $this->validateCitiriRequest($request);

        if ($redirect = $this->redirectDacaLunaInchisa($validated)) {
            return $redirect;
        }

        $citiri = $this->filtreazaCitiriEditabile($validated);

        if ($citiri !== []) {
            $this->persistCitiri(
                (int) $validated['imobil_id'],
                $validated['luna'],
                $validated['data_citire'],
                $citiri,
            );
        }

        CitireContorLunaInchisa::query()->create([
            'imobil_id' => $validated['imobil_id'],
            'luna' => $validated['luna'],
            'inchis_at' => now(),
        ]);

        return redirect()
            ->route('citiri-contoare.imobil', [
                'imobil' => $validated['imobil_id'],
                'luna' => $validated['luna'],
                'mode' => 'history',
            ])
            ->with('success', 'Citirile lunii au fost salvate și închise.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCitiriRequest(Request $request): array
    {
        return $request->validate([
            'imobil_id' => ['required', 'exists:imobile,id'],
            'luna' => ['required', 'string', 'size:7'],
            'data_citire' => ['required', 'date'],
            'citiri' => ['nullable', 'array'],
            'citiri.*.spatiu_id' => ['required', 'exists:spatii,id'],
            'citiri.*.configurare_anexa_linie_id' => ['required', 'exists:configurare_anexa_linii,id'],
            'citiri.*.index_nou' => ['nullable', 'numeric', 'min:0'],
            'citiri.*.index_vechi' => ['nullable', 'numeric', 'min:0'],
            'citiri.*.consum' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function redirectDacaLunaInchisa(array $validated): ?RedirectResponse
    {
        if (! $this->lunaInchisa((int) $validated['imobil_id'], $validated['luna'])) {
            return null;
        }

        return redirect()
            ->route('citiri-contoare.imobil', [
                'imobil' => $validated['imobil_id'],
                'luna' => $validated['luna'],
                'mode' => 'history',
            ])
            ->with('warning', 'Citirile acestei luni sunt închise și nu mai pot fi modificate.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array<string, mixed>>
     */
    private function filtreazaCitiriEditabile(array $validated): array
    {
        if (! $this->existaLunaUlterioara($validated['imobil_id'], $validated['luna'])) {
            return array_values($validated['citiri'] ?? []);
        }

        return collect($validated['citiri'] ?? [])
            ->filter(function (array $citireData) use ($validated): bool {
                $spatiuId = (int) ($citireData['spatiu_id'] ?? 0);
                $linieId = (int) ($citireData['configurare_anexa_linie_id'] ?? 0);

                if ($spatiuId === 0 || $linieId === 0) {
                    return false;
                }

                return ! $this->linieAreCitireLunaUlterioara($spatiuId, $linieId, $validated['luna']);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $citiri
     */
    private function persistCitiri(int $imobilId, string $luna, string $dataCitire, array $citiri): void
    {
        $spatii = Spatiu::query()
            ->where('imobil_id', $imobilId)
            ->whereIn('id', collect($citiri)->pluck('spatiu_id'))
            ->get(['id', 'configurare_anexa_id'])
            ->keyBy('id');

        $linii = ConfigurareAnexaLinie::query()
            ->whereIn('id', collect($citiri)->pluck('configurare_anexa_linie_id'))
            ->get(['id', 'configurare_anexa_id', 'tip_calcul'])
            ->filter(fn (ConfigurareAnexaLinie $linie): bool => TipCalculAnexa::isCitire($linie->tip_calcul))
            ->keyBy('id');

        foreach ($citiri as $citireData) {
            $spatiu = $spatii->get((int) $citireData['spatiu_id']);
            $linie = $linii->get((int) $citireData['configurare_anexa_linie_id']);

            if (! $spatiu || ! $linie || (int) $spatiu->configurare_anexa_id !== (int) $linie->configurare_anexa_id) {
                continue;
            }

            $existingCitire = CitireContor::query()
                ->where('spatiu_id', $citireData['spatiu_id'])
                ->where('configurare_anexa_linie_id', $citireData['configurare_anexa_linie_id'])
                ->where('luna', $luna)
                ->first();

            if ($existingCitire !== null && $this->linieAreCitireLunaUlterioara(
                (int) $citireData['spatiu_id'],
                (int) $citireData['configurare_anexa_linie_id'],
                $luna
            )) {
                continue;
            }

            if (TipCalculAnexa::isPausal($linie->tip_calcul)) {
                $consum = (float) ($citireData['consum'] ?? 0);

                CitireContor::query()->updateOrCreate(
                    [
                        'spatiu_id' => $citireData['spatiu_id'],
                        'configurare_anexa_linie_id' => $citireData['configurare_anexa_linie_id'],
                        'luna' => $luna,
                    ],
                    [
                        'contor_id' => null,
                        'spatiu_id' => $citireData['spatiu_id'],
                        'data_citire' => $dataCitire,
                        'index_vechi' => 0,
                        'index_nou' => 0,
                        'consum' => max(0, $consum),
                    ]
                );

                continue;
            }

            $indexVechi = array_key_exists('index_vechi', $citireData) && $citireData['index_vechi'] !== null && $citireData['index_vechi'] !== ''
                ? (float) $citireData['index_vechi']
                : $this->ultimulIndexNou(
                    (int) $citireData['spatiu_id'],
                    (int) $citireData['configurare_anexa_linie_id'],
                    $luna
                );
            $indexNou = (float) ($citireData['index_nou'] ?? 0);

            CitireContor::query()->updateOrCreate(
                [
                    'spatiu_id' => $citireData['spatiu_id'],
                    'configurare_anexa_linie_id' => $citireData['configurare_anexa_linie_id'],
                    'luna' => $luna,
                ],
                [
                    'contor_id' => null,
                    'spatiu_id' => $citireData['spatiu_id'],
                    'data_citire' => $dataCitire,
                    'index_vechi' => $indexVechi,
                    'index_nou' => $indexNou,
                    'consum' => max(0, $indexNou - $indexVechi),
                ]
            );
        }
    }

    private function contoareCountForImobil(int $imobilId): int
    {
        $count = 0;

        Spatiu::query()
            ->where('imobil_id', $imobilId)
            ->whereNotNull('configurare_anexa_id')
            ->with(['configurareAnexa.linii' => fn ($query) => TipCalculAnexa::applyLiniiContorScope(
                $query->orderBy('ordine')->orderBy('id')
            )])
            ->each(function (Spatiu $spatiu) use (&$count): void {
                $count += $spatiu->configurareAnexa?->linii->count() ?? 0;
            });

        return $count;
    }

    private function ultimaLunaCititaForImobil(int $imobilId): ?string
    {
        return CitireContor::query()
            ->whereHas('spatiu', fn ($query) => $query->where('imobil_id', $imobilId))
            ->orderByDesc('luna')
            ->value('luna');
    }

    private function spatiiCuCitiriPentruImobil(Imobil $imobil, string $luna, bool $lunaInchisa): array
    {
        return Spatiu::query()
            ->with(['configurareAnexa.linii' => fn ($query) => TipCalculAnexa::applyLiniiContorScope(
                $query->orderBy('ordine')->orderBy('id')
            )])
            ->where('imobil_id', $imobil->id)
            ->whereNotNull('configurare_anexa_id')
            ->orderBy('identificator')
            ->get()
            ->map(function (Spatiu $spatiu) use ($luna, $lunaInchisa): array {
                $liniiContor = $spatiu->configurareAnexa
                    ? $spatiu->configurareAnexa->linii->map(function (ConfigurareAnexaLinie $linie) use ($spatiu, $luna, $lunaInchisa): array {
                        $citire = CitireContor::query()
                            ->where('spatiu_id', $spatiu->id)
                            ->where('configurare_anexa_linie_id', $linie->id)
                            ->where('luna', $luna)
                            ->first();

                        $ultimulIndexNou = $this->ultimulIndexNou($spatiu->id, $linie->id, $luna);
                        $editabila = $this->linieEditabila($spatiu->id, $linie->id, $luna, $lunaInchisa);

                        return [
                            'spatiu_id' => $spatiu->id,
                            'configurare_anexa_linie_id' => $linie->id,
                            'denumire' => $linie->denumire,
                            'tip_calcul' => $linie->tip_calcul,
                            'um' => $linie->um,
                            'index_vechi' => TipCalculAnexa::isPausal($linie->tip_calcul)
                                ? ''
                                : ($citire
                                    ? ($citire->index_vechi ?? '')
                                    : $ultimulIndexNou),
                            'index_nou' => TipCalculAnexa::isPausal($linie->tip_calcul)
                                ? ''
                                : ($citire?->index_nou ?? ''),
                            'consum' => $citire?->consum ?? '',
                            'citire_salvata' => $citire !== null,
                            'editabila' => $editabila,
                        ];
                    })->values()->all()
                    : [];

                return [
                    'id' => $spatiu->id,
                    'identificator' => $spatiu->identificator,
                    'chirias' => $spatiu->chirias,
                    'anexa' => $spatiu->configurareAnexa?->denumire,
                    'configurare_anexa_id' => $spatiu->configurare_anexa_id,
                    'liniiContor' => $liniiContor,
                ];
            })
            ->filter(fn (array $spatiu): bool => count($spatiu['liniiContor']) > 0)
            ->values()
            ->all();
    }

    private function luniCititePentruImobil(Imobil $imobil): array
    {
        return CitireContor::query()
            ->whereHas('spatiu', fn ($query) => $query->where('imobil_id', $imobil->id))
            ->select('luna')
            ->distinct()
            ->orderByDesc('luna')
            ->pluck('luna')
            ->map(fn (string $luna): array => [
                'luna' => $luna,
                'label' => substr($luna, 5, 2).'.'.substr($luna, 0, 4),
                'inchisa' => $this->lunaInchisa($imobil->id, $luna),
            ])
            ->all();
    }

    private function lunaInchisa(int $imobilId, string $luna): bool
    {
        return CitireContorLunaInchisa::query()
            ->where('imobil_id', $imobilId)
            ->where('luna', $luna)
            ->exists();
    }

    private function areCitiriSalvatePentruLuna(int $imobilId, string $luna): bool
    {
        return CitireContor::query()
            ->whereHas('spatiu', fn ($query) => $query->where('imobil_id', $imobilId))
            ->where('luna', $luna)
            ->exists();
    }

    private function luniSelectabile(): array
    {
        $an = (int) now()->format('Y');

        return collect(range(1, 12))
            ->map(fn (int $luna): array => [
                'luna' => sprintf('%d-%02d', $an, $luna),
                'label' => sprintf('%02d.%d', $luna, $an),
            ])
            ->all();
    }

    private function lunaUrmatoare(?string $luna): string
    {
        return $luna
            ? \Carbon\Carbon::createFromFormat('Y-m', $luna)->addMonth()->format('Y-m')
            : now()->format('Y-m');
    }

    private function dataCitirePentruLuna(Imobil $imobil, string $luna): ?string
    {
        $dataCitire = CitireContor::query()
            ->whereHas('spatiu', fn ($query) => $query->where('imobil_id', $imobil->id))
            ->where('luna', $luna)
            ->whereNotNull('data_citire')
            ->orderByDesc('data_citire')
            ->value('data_citire');

        return $dataCitire ? \Carbon\Carbon::parse($dataCitire)->format('Y-m-d\TH:i') : null;
    }

    private function existaLunaUlterioara(int $imobilId, string $luna): bool
    {
        return CitireContor::query()
            ->whereHas('spatiu', fn ($query) => $query->where('imobil_id', $imobilId))
            ->where('luna', '>', $luna)
            ->exists();
    }

    private function linieAreCitireLunaUlterioara(int $spatiuId, int $linieId, string $luna): bool
    {
        return CitireContor::query()
            ->where('spatiu_id', $spatiuId)
            ->where('configurare_anexa_linie_id', $linieId)
            ->where('luna', '>', $luna)
            ->exists();
    }

    private function linieEditabila(int $spatiuId, int $linieId, string $luna, bool $lunaInchisa): bool
    {
        if ($lunaInchisa) {
            return false;
        }

        return ! $this->linieAreCitireLunaUlterioara($spatiuId, $linieId, $luna);
    }

    private function ultimulIndexNou(int $spatiuId, int $linieId, string $luna): float
    {
        return (float) (CitireContor::query()
            ->where('spatiu_id', $spatiuId)
            ->where('configurare_anexa_linie_id', $linieId)
            ->where('luna', '<', $luna)
            ->orderByDesc('luna')
            ->value('index_nou') ?? 0);
    }
}
