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
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

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

    public function destroyAllForImobil(Imobil $imobil): RedirectResponse
    {
        $deleted = $this->anexeQuery($imobil->id)->count();

        if ($deleted === 0) {
            return redirect()
                ->route('anexe.imobil', ['imobil' => $imobil->id])
                ->with('warning', 'Nu există anexe de șters pentru acest imobil.');
        }

        $this->anexeQuery($imobil->id)->delete();

        return redirect()
            ->route('anexe.imobil', ['imobil' => $imobil->id])
            ->with('success', "{$deleted} anexe au fost șterse.");
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
