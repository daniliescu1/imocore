<?php

namespace App\Http\Controllers;

use App\Models\Anexa;
use App\Models\AnexaLinie;
use App\Models\CitireContor;
use App\Models\Contract;
use App\Models\ConfigurareAnexaLinie;
use App\Models\Imobil;
use App\Models\Spatiu;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnexaController extends Controller
{
    public function index(): Response
    {
        $contracteEligibile = $this->contracteEligibileQuery()->count();

        return Inertia::render('Anexe/Index', [
            'rezumatImobile' => Inertia::defer(fn () => $this->rezumatImobile(), 'summary'),
            'lunaImplicita' => now()->format('Y-m'),
            'contracteEligibile' => $contracteEligibile,
        ]);
    }

    public function imobil(Imobil $imobil): Response
    {
        $contracteEligibile = $this->contracteEligibileQuery($imobil->id)->count();
        $anexe = $this->anexeQuery($imobil->id)->latest()->get()->map(fn (Anexa $anexa): array => $this->mapAnexaForList($anexa));

        return Inertia::render('Anexe/Imobil', [
            'imobil' => [
                'id' => $imobil->id,
                'nume' => $imobil->nume,
                'localitate' => $imobil->localitate,
            ],
            'anexe' => $anexe,
            'lunaImplicita' => now()->format('Y-m'),
            'contracteEligibile' => $contracteEligibile,
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'luna' => ['required', 'string', 'size:7'],
            'imobil_id' => ['nullable', 'integer', 'exists:imobile,id'],
        ]);
        $lunaFacturare = $validated['luna'];
        $lunaUtilitati = Carbon::createFromFormat('Y-m', $lunaFacturare)->subMonth()->format('Y-m');
        $imobilId = $validated['imobil_id'] ?? null;
        $redirectRoute = $imobilId
            ? ['anexe.imobil', ['imobil' => $imobilId]]
            : ['anexe.index', []];

        $contracte = $this->contracteEligibileQuery($imobilId)->get();

        if ($contracte->isEmpty()) {
            return redirect()->route($redirectRoute[0], $redirectRoute[1])
                ->with('warning', 'Nu există anexe de generat. Verifică dacă ai contracte active și dacă spațiile au o configurare de anexă selectată.');
        }

        $generated = 0;

        foreach ($contracte as $contract) {
            $spatiu = $contract->spatiu;
            $configurare = $spatiu?->configurareAnexa;

            if (! $spatiu || ! $configurare) {
                continue;
            }

            $anexa = Anexa::query()->updateOrCreate(
                [
                    'contract_id' => $contract->id,
                    'luna' => $lunaUtilitati,
                ],
                [
                    'status' => 'draft',
                    'total' => 0,
                ]
            );

            $anexa->linii()->delete();
            $total = 0;

            foreach ($configurare->linii()->where('activ', true)->orderBy('ordine')->orderBy('id')->get() as $linieConfigurata) {
                if ($linieConfigurata->tip_linie === 'header') {
                    $anexa->linii()->create([
                        'ordine' => $linieConfigurata->ordine,
                        'tip_linie' => 'header',
                        'nr_crt' => null,
                        'denumire' => '',
                    ]);

                    continue;
                }

                $linie = $this->linieGenerata($anexa, $spatiu->id, $linieConfigurata, $lunaUtilitati, $lunaFacturare);
                $total += (float) $linie->valoare + (float) ($linie->tva_21 ?? 0);
            }

            $anexa->update(['total' => $total]);
            $generated++;
        }

        return redirect()->route($redirectRoute[0], $redirectRoute[1])
            ->with('success', "{$generated} anexe au fost generate.");
    }

    private function anexeQuery(?int $imobilId = null)
    {
        return Anexa::query()
            ->with('contract.spatiu.imobil')
            ->when($imobilId, fn ($query) => $query->whereHas(
                'contract.spatiu',
                fn ($spatiuQuery) => $spatiuQuery->where('imobil_id', $imobilId)
            ));
    }

    private function mapAnexaForList(Anexa $anexa): array
    {
        return [
            'id' => $anexa->id,
            'contract' => $anexa->contract?->numar_contract ?: '—',
            'spatiu' => $anexa->contract?->spatiu?->identificator ?: '—',
            'chirias' => $anexa->contract?->chirias ?: '—',
            'imobil' => $anexa->contract?->spatiu?->imobil?->nume ?: '—',
            'luna' => $anexa->luna,
            'total' => $anexa->total,
            'status' => $anexa->status,
        ];
    }

    private function contracteEligibileQuery(?int $imobilId = null)
    {
        return Contract::query()
            ->with(['spatiu.configurareAnexa.linii', 'spatiu.imobil'])
            ->where('status', 'activ')
            ->whereHas('spatiu', fn ($query) => $query
                ->whereNotNull('configurare_anexa_id')
                ->when($imobilId, fn ($spatiuQuery) => $spatiuQuery->where('imobil_id', $imobilId)));
    }

    private function rezumatImobile(): array
    {
        $anexePeImobil = Anexa::query()
            ->join('contracte', 'contracte.id', '=', 'anexe.contract_id')
            ->join('spatii', 'spatii.id', '=', 'contracte.spatiu_id')
            ->select('spatii.imobil_id')
            ->selectRaw('count(anexe.id) as anexe_generate')
            ->selectRaw('coalesce(sum(anexe.total), 0) as total_generat')
            ->groupBy('spatii.imobil_id')
            ->get()
            ->keyBy('imobil_id');

        return Imobil::query()
            ->withCount([
                'spatii as spatii_inchiriate_count' => fn ($query) => $query->where('status', 'inchiriat'),
            ])
            ->orderBy('nume')
            ->get()
            ->map(function (Imobil $imobil) use ($anexePeImobil): array {
                $anexe = $anexePeImobil->get($imobil->id);

                return [
                    'id' => $imobil->id,
                    'nume' => $imobil->nume,
                    'localitate' => $imobil->localitate,
                    'spatii_inchiriate' => $imobil->spatii_inchiriate_count,
                    'anexe_generate' => (int) ($anexe?->anexe_generate ?? 0),
                    'total_generat' => (float) ($anexe?->total_generat ?? 0),
                ];
            })
            ->all();
    }

    public function show(Anexa $anexa): Response
    {
        $anexa->load(['linii', 'contract.spatiu.imobil', 'contract.spatiu.locatorEntitate']);
        $contract = $anexa->contract;
        $spatiu = $contract?->spatiu;
        $imobil = $spatiu?->imobil;
        $dataCitire = $this->dataCitirePentruAnexa($spatiu?->id, $anexa->luna);

        $liniiServiciu = $anexa->linii->filter(fn (AnexaLinie $linie): bool => ($linie->tip_linie ?: 'serviciu') !== 'header');
        $subtotal = $liniiServiciu->sum(fn (AnexaLinie $linie): float => (float) $linie->valoare);
        $totalTva = $liniiServiciu->sum(fn (AnexaLinie $linie): float => (float) ($linie->tva_21 ?? 0));

        return Inertia::render('Anexe/Show', [
            'anexa' => [
                'id' => $anexa->id,
                'numar' => '01',
                'luna' => $anexa->luna,
                'total' => $anexa->total,
                'subtotal' => $subtotal,
                'total_tva' => $totalTva,
                'status' => $anexa->status,
                'data_citire' => $dataCitire,
                'contract' => [
                    'numar' => $contract?->numar_contract,
                    'chirias' => $contract?->chirias,
                ],
                'spatiu' => [
                    'identificator' => $spatiu?->identificator,
                    'locator' => $spatiu?->locatorEntitate?->nume ?: $spatiu?->getAttribute('locator'),
                    'chirias' => $spatiu?->chirias ?: $contract?->chirias,
                ],
                'imobil' => [
                    'nume' => $imobil?->nume,
                    'adresa' => trim(implode(' ', array_filter([$imobil?->strada, $imobil?->numar]))),
                    'localitate' => $imobil?->localitate,
                ],
                'linii' => $anexa->linii->values()->map(fn (AnexaLinie $linie): array => [
                    'tip_linie' => $linie->tip_linie ?: 'serviciu',
                    'nr_crt' => $linie->nr_crt,
                    'denumire' => $linie->denumire,
                    'tip_calcul' => $linie->tip_calcul,
                    'coeficient' => $linie->coeficient,
                    'index_vechi' => $linie->index_vechi,
                    'index_nou' => $linie->index_nou,
                    'cantitate' => $linie->cantitate,
                    'um' => $linie->um,
                    'pret_unitar' => $linie->pret_unitar,
                    'valoare' => $linie->valoare,
                    'tva_21' => $linie->tva_21,
                ]),
            ],
        ]);
    }

    public function destroy(Anexa $anexa): RedirectResponse
    {
        $anexa->loadMissing('contract.spatiu');
        $imobilId = $anexa->contract?->spatiu?->imobil_id;

        $anexa->delete();

        if ($imobilId) {
            return redirect()
                ->route('anexe.imobil', ['imobil' => $imobilId])
                ->with('success', 'Anexa generată a fost ștearsă.');
        }

        return redirect()
            ->route('anexe.index')
            ->with('success', 'Anexa generată a fost ștearsă.');
    }

    private function linieGenerata(Anexa $anexa, int $spatiuId, ConfigurareAnexaLinie $linieConfigurata, string $lunaUtilitati, string $lunaFacturare): AnexaLinie
    {
        $spatiu = Spatiu::query()->findOrFail($spatiuId);
        $indexVechi = null;
        $indexNou = null;
        $cantitate = null;
        $coeficient = null;
        $tipCalcul = $linieConfigurata->tip_calcul;

        if ($this->tipCalculMpCoeficient($tipCalcul)) {
            $suprafataMp = (float) ($spatiu->suprafata_contractuala_mp ?? 0);
            $coeficient = $this->coeficientMpPentruLinie($linieConfigurata);
            $indexVechi = $suprafataMp > 0 ? $suprafataMp : null;
            $indexNou = $coeficient > 0 ? $coeficient : null;
            $cantitate = ($suprafataMp > 0 && $coeficient > 0)
                ? round($suprafataMp * $coeficient, 3)
                : null;
        } elseif ($this->tipCalculPeMp($tipCalcul)) {
            $suprafataMp = (float) ($spatiu->suprafata_contractuala_mp ?? 0);
            $indexVechi = null;
            $indexNou = null;
            $cantitate = $suprafataMp > 0 ? round($suprafataMp, 3) : null;
        } elseif ($tipCalcul === 'persoane') {
            $persoane = $spatiu->persoanePentruAnexa();
            $indexVechi = null;
            $indexNou = null;
            $cantitate = $persoane;
        } elseif ($tipCalcul === 'contor') {
            $citire = $this->citirePentruAnexa($spatiuId, $linieConfigurata->id, $lunaUtilitati, $lunaFacturare);

            if ($citire) {
                $indexVechi = $citire->index_vechi;
                $indexNou = $citire->index_nou;
                $cantitate = $citire->consum;
            }
        } elseif ($tipCalcul === 'fix' || $tipCalcul === 'manual') {
            $cantitate = $linieConfigurata->facturat;
        }

        $pretUnitar = $linieConfigurata->pret_unitar;
        $valoare = $linieConfigurata->valoare;

        if ($cantitate !== null && $pretUnitar !== null) {
            $valoare = (float) $cantitate * (float) $pretUnitar;
        }

        $valoare = (float) ($valoare ?? 0);
        $procentTva = (float) ($linieConfigurata->tva_21 ?? 0);
        $sumaTva = $procentTva > 0 ? round($valoare * $procentTva / 100, 2) : null;

        return $anexa->linii()->create([
            'ordine' => $linieConfigurata->ordine,
            'tip_linie' => 'serviciu',
            'nr_crt' => $linieConfigurata->nr_crt,
            'denumire' => $linieConfigurata->denumire,
            'um' => $linieConfigurata->um,
            'tip_calcul' => $linieConfigurata->tip_calcul,
            'index_vechi' => $indexVechi,
            'index_nou' => $indexNou,
            'cantitate' => $cantitate,
            'coeficient' => $coeficient,
            'pret_unitar' => $pretUnitar,
            'valoare' => $valoare,
            'tva_21' => $sumaTva,
        ]);
    }

    private function tipCalculPeMp(?string $tipCalcul): bool
    {
        return in_array($tipCalcul, ['mp', 'pe_mp'], true);
    }

    private function tipCalculMpCoeficient(?string $tipCalcul): bool
    {
        $normalized = str_replace([' ', '*', '×', '_', '-'], '', strtolower((string) $tipCalcul));

        return str_starts_with($normalized, 'mp') && str_contains($normalized, 'coeficient');
    }

    private function coeficientMpPentruLinie(ConfigurareAnexaLinie $linieConfigurata): float
    {
        $coeficient = (float) ($linieConfigurata->coeficient ?? 0);

        if ($coeficient > 0 && $coeficient <= 1) {
            return $coeficient;
        }

        $indexNou = (float) ($linieConfigurata->index_nou ?? 0);

        return $indexNou > 0 && $indexNou <= 1 ? $indexNou : 0.09;
    }

    private function citirePentruAnexa(int $spatiuId, int $linieId, string $lunaUtilitati, string $lunaFacturare): ?CitireContor
    {
        return CitireContor::query()
            ->where('spatiu_id', $spatiuId)
            ->where('configurare_anexa_linie_id', $linieId)
            ->whereIn('luna', array_unique([$lunaUtilitati, $lunaFacturare]))
            ->orderByRaw('case when luna = ? then 0 else 1 end', [$lunaUtilitati])
            ->first();
    }

    private function dataCitirePentruAnexa(?int $spatiuId, string $lunaUtilitati): ?string
    {
        if (! $spatiuId) {
            return null;
        }

        $lunaFacturare = Carbon::createFromFormat('Y-m', $lunaUtilitati)->addMonth()->format('Y-m');

        return CitireContor::query()
            ->where('spatiu_id', $spatiuId)
            ->whereIn('luna', array_unique([$lunaUtilitati, $lunaFacturare]))
            ->whereNotNull('data_citire')
            ->orderByRaw('case when luna = ? then 0 else 1 end', [$lunaUtilitati])
            ->orderByDesc('data_citire')
            ->value('data_citire');
    }
}
