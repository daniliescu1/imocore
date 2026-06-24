<?php

namespace App\Http\Controllers;

use App\Models\Anexa;
use App\Models\AnexaLinie;
use App\Models\CitireContor;
use App\Models\Contract;
use App\Models\ConfigurareAnexaLinie;
use App\Models\Imobil;
use App\Models\Spatiu;
use App\Support\AnexaDocumentPayload;
use App\Support\ContorConfigurabilSync;
use App\Support\DocumentFormatter;
use App\Support\GenerareAnexaLinieCalculator;
use App\Support\StrictSearch;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AnexaController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $localitate = trim($request->string('localitate')->toString());
        $contracteEligibile = $this->contracteEligibileQuery()->count();
        $localitati = Imobil::query()->select('localitate')->distinct()->orderBy('localitate')->pluck('localitate');

        if ($search !== '') {
            $anexe = $this->anexeQuery(null, $search, $localitate)
                ->latest()
                ->get()
                ->map(fn (Anexa $anexa): array => $this->mapAnexaForList($anexa));

            return Inertia::render('Anexe/Index', [
                'rezumatImobile' => [],
                'anexe' => $anexe,
                'localitati' => $localitati,
                'filters' => [
                    'search' => $search,
                    'localitate' => $localitate,
                ],
                'lunaImplicita' => now()->format('Y-m'),
                'contracteEligibile' => $contracteEligibile,
            ]);
        }

        return Inertia::render('Anexe/Index', [
            'rezumatImobile' => Inertia::defer(fn () => $this->rezumatImobile($localitate, ''), 'summary'),
            'anexe' => [],
            'localitati' => $localitati,
            'filters' => [
                'search' => '',
                'localitate' => $localitate,
            ],
            'lunaImplicita' => now()->format('Y-m'),
            'contracteEligibile' => $contracteEligibile,
        ]);
    }

    public function imobil(Request $request, Imobil $imobil): Response
    {
        $search = trim($request->string('search')->toString());
        $contracteEligibile = $this->contracteEligibileQuery($imobil->id)->count();
        $anexe = $this->anexeQuery($imobil->id, $search)->latest()->get()->map(fn (Anexa $anexa): array => $this->mapAnexaForList($anexa));

        return Inertia::render('Anexe/Imobil', [
            'imobil' => [
                'id' => $imobil->id,
                'nume' => $imobil->nume,
                'localitate' => $imobil->localitate,
            ],
            'anexe' => $anexe,
            'lunaImplicita' => now()->format('Y-m'),
            'contracteEligibile' => $contracteEligibile,
            'filters' => [
                'search' => $search,
            ],
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

        $imobilIds = $imobilId
            ? collect([$imobilId])
            : $contracte
                ->map(fn (Contract $contract): ?int => $contract->spatiu?->imobil_id)
                ->filter()
                ->unique()
                ->values();

        foreach ($imobilIds as $syncImobilId) {
            ContorConfigurabilSync::syncForImobil((int) $syncImobilId);
        }

        $generated = 0;

        foreach ($contracte as $contract) {
            $spatiu = $contract->spatiu;
            $configurare = $spatiu?->configurareAnexa;

            if (! $spatiu || ! $configurare || $spatiu->status !== 'inchiriat') {
                continue;
            }

            if ($this->anexaExistaPentruSpatiuSiLuna((int) $spatiu->id, $lunaUtilitati)) {
                continue;
            }

            $anexa = Anexa::query()->create([
                'contract_id' => $contract->id,
                'luna' => $lunaUtilitati,
                'status' => 'draft',
                'total' => 0,
            ]);

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

        if ($generated === 0 && $contracte->isNotEmpty()) {
            return redirect()->route($redirectRoute[0], $redirectRoute[1])
                ->with('warning', 'Anexele pentru luna selectată există deja. Șterge anexa existentă dacă vrei să generezi una nouă.');
        }

        return redirect()->route($redirectRoute[0], $redirectRoute[1])
            ->with('success', "{$generated} anexe au fost generate.");
    }

    private function anexeQuery(?int $imobilId = null, string $search = '', string $localitate = ''): Builder
    {
        $latestAnexaIds = $this->latestAnexaIdsQuery($imobilId, $search, $localitate);

        return Anexa::query()
            ->with('contract.spatiu.imobil')
            ->whereIn('id', $latestAnexaIds)
            ->when($imobilId, fn ($query) => $query->whereHas(
                'contract.spatiu',
                fn ($spatiuQuery) => $spatiuQuery->where('imobil_id', $imobilId)
            ))
            ->when($localitate !== '', fn ($query) => $query->whereHas(
                'contract.spatiu.imobil',
                fn ($imobilQuery) => $imobilQuery->where('localitate', $localitate)
            ))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                StrictSearch::whereColumnContains($query, 'luna', $search);
                $query->orWhereHas('contract', function ($contractQuery) use ($search): void {
                    StrictSearch::whereColumnContains($contractQuery, 'numar_contract', $search);
                    StrictSearch::orWhereColumnContains($contractQuery, 'chirias', $search);
                })->orWhereHas('contract.spatiu', fn ($spatiuQuery) => StrictSearch::whereSpatiuIdentificator($spatiuQuery, $search))
                    ->orWhereHas('contract.spatiu.imobil', fn ($imobilQuery) => StrictSearch::whereColumnContains($imobilQuery, 'nume', $search));
            }));
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

    private function contracteEligibileQuery(?int $imobilId = null): Builder
    {
        $latestContractIds = Contract::query()
            ->selectRaw('MAX(contracte.id) as id')
            ->join('spatii', 'spatii.id', '=', 'contracte.spatiu_id')
            ->where('contracte.status', 'activ')
            ->where('spatii.status', 'inchiriat')
            ->whereNotNull('spatii.configurare_anexa_id')
            ->when($imobilId, fn ($query) => $query->where('spatii.imobil_id', $imobilId))
            ->groupBy('contracte.spatiu_id')
            ->pluck('id');

        return Contract::query()
            ->with(['spatiu.configurareAnexa.linii', 'spatiu.imobil'])
            ->whereIn('id', $latestContractIds);
    }

    private function anexaExistaPentruSpatiuSiLuna(int $spatiuId, string $lunaUtilitati): bool
    {
        return Anexa::query()
            ->where('luna', $lunaUtilitati)
            ->whereHas('contract', fn ($query) => $query->where('spatiu_id', $spatiuId))
            ->exists();
    }

    /**
     * @return list<int>
     */
    private function latestAnexaIdsQuery(?int $imobilId = null, string $search = '', string $localitate = ''): array
    {
        return Anexa::query()
            ->join('contracte', 'contracte.id', '=', 'anexe.contract_id')
            ->join('spatii', 'spatii.id', '=', 'contracte.spatiu_id')
            ->join('imobile', 'imobile.id', '=', 'spatii.imobil_id')
            ->when($imobilId, fn ($query) => $query->where('spatii.imobil_id', $imobilId))
            ->when($localitate !== '', fn ($query) => $query->where('imobile.localitate', $localitate))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    StrictSearch::whereColumnContains($query, 'anexe.luna', $search);
                    StrictSearch::orWhereColumnContains($query, 'contracte.numar_contract', $search);
                    StrictSearch::orWhereColumnContains($query, 'contracte.chirias', $search);
                    StrictSearch::orWhereColumnContains($query, 'spatii.identificator', $search);
                    StrictSearch::orWhereColumnContains($query, 'imobile.nume', $search);
                });
            })
            ->selectRaw('MAX(anexe.id) as id')
            ->groupBy('contracte.spatiu_id', 'anexe.luna')
            ->pluck('id')
            ->all();
    }

    private function rezumatImobile(string $localitate = '', string $search = ''): array
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

        $query = Imobil::query()
            ->withCount([
                'spatii as spatii_inchiriate_count' => fn ($query) => $query->where('status', 'inchiriat'),
            ])
            ->orderBy('nume');

        if ($localitate !== '') {
            $query->where('localitate', $localitate);
        }

        if ($search !== '') {
            StrictSearch::whereColumnContains($query, 'nume', $search);
        }

        return $query
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
        return Inertia::render('Anexe/Show', [
            'anexa' => AnexaDocumentPayload::fromModel($anexa),
            'downloadUrl' => route('anexe.download', $anexa),
        ]);
    }

    public function download(Anexa $anexa): HttpResponse
    {
        $payload = AnexaDocumentPayload::fromModel($anexa);
        $numeFirma = (string) ($payload['spatiu']['chirias'] ?? $payload['contract']['chirias'] ?? '');
        $filename = DocumentFormatter::anexaDownloadFilename($numeFirma, $payload['luna'] ?? null);

        return Pdf::loadView('documents.anexa', ['anexa' => $payload])->download($filename);
    }

    public function destroyAllForImobil(Request $request, Imobil $imobil): RedirectResponse
    {
        $search = trim($request->string('search')->toString());
        $query = $this->anexeQuery($imobil->id, $search);
        $deleted = (clone $query)->count();
        $redirectParams = array_filter([
            'imobil' => $imobil->id,
            'search' => $search,
        ], fn ($value) => $value !== '' && $value !== null);

        if ($deleted === 0) {
            return redirect()
                ->route('anexe.imobil', $redirectParams)
                ->with('warning', 'Nu există anexe de șters.');
        }

        $query->delete();

        $message = $search !== ''
            ? "{$deleted} anexe filtrate au fost șterse."
            : "{$deleted} anexe au fost șterse.";

        return redirect()
            ->route('anexe.imobil', $redirectParams)
            ->with('success', $message);
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

        return $anexa->linii()->create(GenerareAnexaLinieCalculator::calculate(
            $spatiu,
            $linieConfigurata,
            $lunaUtilitati,
            $lunaFacturare,
        ));
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
