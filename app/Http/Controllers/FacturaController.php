<?php

namespace App\Http\Controllers;

use App\Models\Anexa;
use App\Models\Factura;
use App\Models\Imobil;
use App\Models\SetareAplicatie;
use App\Models\Spatiu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class FacturaController extends Controller
{
    public function index(): Response
    {
        $facturi = Factura::query()
            ->with('anexa.contract.spatiu.imobil')
            ->latest()
            ->get()
            ->map(fn (Factura $factura): array => [
                'id' => $factura->id,
                'numar_factura' => $factura->numar_factura ?: '—',
                'anexa' => $factura->anexa?->luna ?: '—',
                'contract' => $factura->anexa?->contract?->numar_contract ?: '—',
                'imobil' => $factura->anexa?->contract?->spatiu?->imobil?->nume ?: '—',
                'spatiu' => $factura->anexa?->contract?->spatiu?->identificator ?: '—',
                'chirias' => $factura->anexa?->contract?->chirias ?: '—',
                'curs_eur' => $factura->curs_eur,
                'total' => $factura->total,
                'status' => $factura->status,
                'email_chirias' => $factura->email_chirias ?: '—',
            ]);

        $anexeNefacturate = Anexa::query()
            ->whereDoesntHave('factura')
            ->count();

        $curs = $this->cursEurBt();

        return Inertia::render('Facturare/Index', [
            'facturi' => $facturi,
            'anexeNefacturate' => $anexeNefacturate,
            'rezumatImobile' => $this->rezumatImobile((float) $curs['valoare']),
            'cursImplicit' => $curs['valoare'],
            'cursSursa' => $curs['sursa'],
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'curs_eur' => ['required', 'numeric', 'min:0'],
        ]);
        $cursEur = (float) $validated['curs_eur'];
        $anexe = Anexa::query()
            ->with('contract')
            ->whereDoesntHave('factura')
            ->orderBy('id')
            ->get();

        if ($anexe->isEmpty()) {
            return redirect('/facturare')->with('warning', 'Nu există anexe nefacturate pentru generare.');
        }

        foreach ($anexe as $anexa) {
            $chirieEur = (float) ($anexa->contract?->chirie ?? 0);
            $chirieLei = round($chirieEur * $cursEur, 2);
            $penalitati = 0;
            Factura::query()->create([
                'anexa_id' => $anexa->id,
                'numar_factura' => $this->nextInvoiceNumber(),
                'curs_eur' => $cursEur,
                'chirie_eur' => $chirieEur,
                'chirie_lei' => $chirieLei,
                'penalitati' => $penalitati,
                'total' => $chirieLei + (float) $anexa->total + $penalitati,
                'status' => 'draft',
                'email_chirias' => null,
            ]);
        }

        return redirect('/facturare')->with('success', "{$anexe->count()} facturi au fost generate.");
    }

    public function updateCurs(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'curs_eur' => ['required', 'numeric', 'min:0'],
        ]);

        SetareAplicatie::seteaza('curs_eur_facturare', $validated['curs_eur']);

        return redirect('/facturare')->with('success', 'Cursul valutar a fost salvat.');
    }

    public function show(Factura $factura): Response
    {
        $factura->load(['anexa.linii', 'anexa.contract.spatiu.imobil', 'anexa.contract.spatiu.locatorEntitate']);
        $anexa = $factura->anexa;
        $contract = $anexa?->contract;
        $spatiu = $contract?->spatiu;
        $imobil = $spatiu?->imobil;
        $lunaUtilitati = $this->numeLuna($anexa?->luna);
        $lunaChirie = $this->numeLunaUrmatoare($anexa?->luna);
        $anexaLinii = $anexa?->linii->values() ?? collect();
        $anexaLiniiServiciu = $anexaLinii->filter(fn ($linie): bool => ($linie->tip_linie ?: 'serviciu') !== 'header');
        $anexaSubtotal = $anexaLiniiServiciu->sum(fn ($linie): float => (float) $linie->valoare);
        $anexaTotalTva = $anexaLiniiServiciu->sum(fn ($linie): float => (float) ($linie->tva_21 ?? 0));

        return Inertia::render('Facturare/Show', [
            'factura' => [
                'id' => $factura->id,
                'numar_factura' => $factura->numar_factura,
                'curs_eur' => $factura->curs_eur,
                'chirie_eur' => $factura->chirie_eur,
                'chirie_lei' => $factura->chirie_lei,
                'penalitati' => $factura->penalitati,
                'total' => $factura->total,
                'status' => $factura->status,
                'email_chirias' => $factura->email_chirias,
                'luna' => $anexa?->luna,
                'luna_utilitati' => $lunaUtilitati,
                'luna_chirie' => $lunaChirie,
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
                'linii' => [
                    [
                        'nr_crt' => 1,
                        'denumire' => trim('Chirie spațiu '.$lunaChirie),
                        'cantitate' => 1,
                        'um' => 'LUNĂ',
                        'pret_unitar' => $factura->chirie_lei,
                        'valoare' => $factura->chirie_lei,
                        'tva' => null,
                    ],
                    [
                        'nr_crt' => 2,
                        'denumire' => trim('Utilități '.$lunaUtilitati),
                        'cantitate' => 1,
                        'um' => 'LUNĂ',
                        'pret_unitar' => $anexaSubtotal,
                        'valoare' => $anexaSubtotal,
                        'tva' => $anexaTotalTva,
                    ],
                    [
                        'nr_crt' => 3,
                        'denumire' => 'Penalități',
                        'cantitate' => null,
                        'um' => null,
                        'pret_unitar' => null,
                        'valoare' => $factura->penalitati,
                        'tva' => null,
                    ],
                ],
                'anexa_detaliu' => [
                    'numar' => '01',
                    'luna' => $anexa?->luna,
                    'luna_utilitati' => $lunaUtilitati,
                    'subtotal' => $anexaSubtotal,
                    'total_tva' => $anexaTotalTva,
                    'total' => $anexa?->total,
                    'linii' => $anexaLinii->map(fn ($linie): array => [
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
                    ])->all(),
                ],
            ],
        ]);
    }

    public function destroy(Factura $factura): RedirectResponse
    {
        $factura->delete();

        return redirect('/facturare')->with('success', 'Factura a fost ștearsă.');
    }

    private function nextInvoiceNumber(): string
    {
        $nextId = (int) (Factura::query()->max('id') ?? 0) + 1;

        return 'FACT-'.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
    }

    private function numeLuna(?string $luna): string
    {
        $luni = [
            '01' => 'ianuarie',
            '02' => 'februarie',
            '03' => 'martie',
            '04' => 'aprilie',
            '05' => 'mai',
            '06' => 'iunie',
            '07' => 'iulie',
            '08' => 'august',
            '09' => 'septembrie',
            '10' => 'octombrie',
            '11' => 'noiembrie',
            '12' => 'decembrie',
        ];

        $numarLuna = $luna ? substr($luna, -2) : null;

        return $luni[$numarLuna] ?? '';
    }

    private function numeLunaUrmatoare(?string $luna): string
    {
        if (! $luna) {
            return '';
        }

        $numarLuna = (int) substr($luna, -2);
        $numarLunaUrmatoare = $numarLuna === 12 ? 1 : $numarLuna + 1;

        return $this->numeLuna(str_pad((string) $numarLunaUrmatoare, 2, '0', STR_PAD_LEFT));
    }

    private function cursEurBt(): array
    {
        $cursSalvat = SetareAplicatie::valoare('curs_eur_facturare');

        if ($cursSalvat) {
            return [
                'valoare' => $cursSalvat,
                'sursa' => 'Curs introdus manual',
            ];
        }

        try {
            $html = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'ImoCore/1.0'])
                ->get('https://www.bancatransilvania.ro/curs-valutar')
                ->body();

            if (preg_match('/1\s*EUR\s*=\s*([0-9]+[,.][0-9]+)\s*RON/i', $html, $matches)) {
                return [
                    'valoare' => str_replace(',', '.', $matches[1]),
                    'sursa' => 'Banca Transilvania - curs vânzare EUR',
                ];
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return [
            'valoare' => Factura::query()->latest()->value('curs_eur') ?: 5,
            'sursa' => 'Ultimul curs salvat / fallback',
        ];
    }

    private function rezumatImobile(float $cursEur): array
    {
        return Imobil::query()
            ->withCount([
                'spatii as spatii_inchiriate_count' => fn ($query) => $query->where('status', 'inchiriat'),
            ])
            ->orderBy('nume')
            ->get()
            ->map(function (Imobil $imobil) use ($cursEur): array {
                $facturi = Factura::query()
                    ->with('anexa')
                    ->whereHas('anexa.contract.spatiu', fn ($query) => $query->where('imobil_id', $imobil->id))
                    ->get(['id', 'anexa_id', 'chirie_lei', 'total']);
                $totalChirieEur = Spatiu::query()
                    ->where('imobil_id', $imobil->id)
                    ->where('status', 'inchiriat')
                    ->get(['pret_lunar', 'indexare_2025', 'indexare_2026'])
                    ->sum(fn (Spatiu $spatiu): float => (float) ($spatiu->indexare_2026 ?: $spatiu->indexare_2025 ?: $spatiu->pret_lunar ?: 0));
                $anexeEmise = Anexa::query()
                    ->whereHas('contract.spatiu', fn ($query) => $query->where('imobil_id', $imobil->id))
                    ->count();

                return [
                    'id' => $imobil->id,
                    'nume' => $imobil->nume,
                    'localitate' => $imobil->localitate,
                    'spatii_inchiriate' => $imobil->spatii_inchiriate_count,
                    'anexe_emise' => $anexeEmise,
                    'facturi_emise' => $facturi->count(),
                    'total_chirie_eur' => $totalChirieEur,
                    'total_chirie_lei' => round($totalChirieEur * $cursEur, 2),
                    'total_utilitati' => $facturi->sum(fn (Factura $factura): float => (float) ($factura->anexa?->total ?? 0)),
                    'total_facturat' => $facturi->sum(fn (Factura $factura): float => (float) $factura->total),
                ];
            })
            ->all();
    }
}
