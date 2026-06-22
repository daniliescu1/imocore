<?php

namespace App\Http\Controllers;

use App\Models\Anexa;
use App\Models\Contract;
use App\Models\Factura;
use App\Models\Imobil;
use App\Models\Locator;
use App\Models\SetareAplicatie;
use App\Models\Spatiu;
use App\Support\AnexaDocumentPayload;
use App\Support\DocumentFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FacturaController extends Controller
{
    private const TVA_CHIRIE_PROCENT = 21;

    public function index(): Response
    {
        $anexeFacturate = Anexa::query()
            ->whereHas('factura')
            ->count();
        $anexeNefacturate = Anexa::query()
            ->whereDoesntHave('factura')
            ->count();

        $curs = $this->cursEurBt();

        return Inertia::render('Facturare/Index', [
            'anexeFacturate' => $anexeFacturate,
            'anexeNefacturate' => $anexeNefacturate,
            'rezumatImobile' => Inertia::defer(fn () => $this->rezumatImobile((float) $curs['valoare']), 'summary'),
            'cursImplicit' => $curs['valoare'],
            'cursSursa' => $curs['sursa'],
        ]);
    }

    public function imobil(Request $request, Imobil $imobil): Response
    {
        $searchSpatiu = trim($request->string('search_spatiu')->toString());
        $searchChirias = trim($request->string('search_chirias')->toString());
        $curs = $this->cursEurBt();
        $facturi = $this->facturiQuery($imobil->id, $searchSpatiu, $searchChirias)
            ->latest()
            ->get()
            ->map(fn (Factura $factura): array => $this->mapFacturaForList($factura));

        $anexeFacturate = Anexa::query()
            ->whereHas('factura')
            ->whereHas('contract.spatiu', fn ($query) => $query->where('imobil_id', $imobil->id))
            ->count();
        $anexeNefacturate = Anexa::query()
            ->whereDoesntHave('factura')
            ->whereHas('contract.spatiu', fn ($query) => $query->where('imobil_id', $imobil->id))
            ->count();

        return Inertia::render('Facturare/Imobil', [
            'imobil' => [
                'id' => $imobil->id,
                'nume' => $imobil->nume,
                'localitate' => $imobil->localitate,
            ],
            'facturi' => $facturi,
            'anexeFacturate' => $anexeFacturate,
            'anexeNefacturate' => $anexeNefacturate,
            'cursImplicit' => $curs['valoare'],
            'cursSursa' => $curs['sursa'],
            'filters' => [
                'search_spatiu' => $searchSpatiu,
                'search_chirias' => $searchChirias,
            ],
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'curs_eur' => ['nullable', 'numeric', 'min:0'],
            'imobil_id' => ['nullable', 'integer', 'exists:imobile,id'],
        ]);
        $cursEur = isset($validated['curs_eur']) && $validated['curs_eur'] !== null && $validated['curs_eur'] !== ''
            ? (float) $validated['curs_eur']
            : (float) $this->cursEurBt()['valoare'];
        $imobilId = $validated['imobil_id'] ?? null;
        $redirectRoute = $imobilId
            ? ['facturare.imobil', ['imobil' => $imobilId]]
            : ['facturare.index', []];
        $anexe = Anexa::query()
            ->with(['contract.spatiu.locatorEntitate'])
            ->whereDoesntHave('factura')
            ->when($imobilId, fn ($query) => $query->whereHas(
                'contract.spatiu',
                fn ($spatiuQuery) => $spatiuQuery->where('imobil_id', $imobilId)
            ))
            ->orderBy('id')
            ->get();

        $contracteFaraAnexa = $this->contracteFacturabileFaraAnexaQuery($imobilId)->get();
        $generatedFromAnexe = 0;
        $generatedFromContracte = 0;

        foreach ($anexe as $anexa) {
            $this->createFacturaFromAnexa($anexa, $cursEur);
            $generatedFromAnexe++;
        }

        foreach ($contracteFaraAnexa as $contract) {
            foreach ($this->luniFacturabileNeacoperite($contract) as $luna) {
                $this->createFacturaFromContract($contract, $luna, $cursEur);
                $generatedFromContracte++;
            }
        }

        $generatedTotal = $generatedFromAnexe + $generatedFromContracte;

        if ($generatedTotal === 0) {
            return redirect()->route($redirectRoute[0], $redirectRoute[1])
                ->with('warning', 'Nu există anexe nefacturate sau contracte fără anexă de facturat.');
        }

        return redirect()->route($redirectRoute[0], $redirectRoute[1])
            ->with('success', "{$generatedTotal} facturi au fost generate.");
    }

    private function createFacturaFromAnexa(Anexa $anexa, float $cursEur): Factura
    {
        $chirie = (float) ($anexa->contract?->chirieFacturabilaPentruLunaAnexa($anexa->luna) ?? 0);
        $moneda = $anexa->contract?->moneda ?: 'EUR';
        $penalitati = 0;

        if ($moneda === 'RON') {
            $chirieEur = 0;
            $chirieLei = $chirie;
        } else {
            $chirieEur = $chirie;
            $chirieLei = round($chirieEur * $cursEur, 2);
        }

        $locator = $anexa->contract?->spatiu?->locatorEntitate;
        $chirieTva = $this->tvaChirieLei($chirieLei, $locator);
        $dataEmitere = now()->toDateString();

        return Factura::query()->create([
            'anexa_id' => $anexa->id,
            'contract_id' => null,
            'luna' => null,
            'numar_factura' => $this->nextInvoiceNumber(),
            'data_emitere' => $dataEmitere,
            'data_scadenta' => now()->addDays(5)->toDateString(),
            'curs_eur' => $cursEur,
            'chirie_eur' => $chirieEur,
            'chirie_lei' => $chirieLei,
            'penalitati' => $penalitati,
            'total' => $chirieLei + $chirieTva + (float) $anexa->total + $penalitati,
            'status' => 'draft',
            'email_chirias' => null,
        ]);
    }

    private function createFacturaFromContract(Contract $contract, string $luna, float $cursEur): Factura
    {
        $chirie = (float) $contract->chirieFacturabilaPentruLunaAnexa($luna);
        $moneda = $contract->moneda ?: 'EUR';
        $penalitati = 0;

        if ($moneda === 'RON') {
            $chirieEur = 0;
            $chirieLei = $chirie;
        } else {
            $chirieEur = $chirie;
            $chirieLei = round($chirieEur * $cursEur, 2);
        }

        $locator = $contract->spatiu?->locatorEntitate;
        $chirieTva = $this->tvaChirieLei($chirieLei, $locator);
        $dataEmitere = now()->toDateString();

        return Factura::query()->create([
            'anexa_id' => null,
            'contract_id' => $contract->id,
            'luna' => $luna,
            'numar_factura' => $this->nextInvoiceNumber(),
            'data_emitere' => $dataEmitere,
            'data_scadenta' => now()->addDays(5)->toDateString(),
            'curs_eur' => $cursEur,
            'chirie_eur' => $chirieEur,
            'chirie_lei' => $chirieLei,
            'penalitati' => $penalitati,
            'total' => $chirieLei + $chirieTva + $penalitati,
            'status' => 'draft',
            'email_chirias' => null,
        ]);
    }

    private function contracteFacturabileFaraAnexaQuery(?int $imobilId = null)
    {
        return Contract::query()
            ->with(['spatiu.locatorEntitate'])
            ->where('status', 'activ')
            ->whereHas('spatiu', fn ($query) => $query
                ->whereNull('configurare_anexa_id')
                ->where('status', 'inchiriat')
                ->when($imobilId, fn ($spatiuQuery) => $spatiuQuery->where('imobil_id', $imobilId)));
    }

    /**
     * @return list<string>
     */
    private function luniFacturabileNeacoperite(Contract $contract): array
    {
        if (! $contract->data_start) {
            return [];
        }

        $start = $contract->data_start->copy()->startOfMonth();
        $end = now()->subMonth()->startOfMonth();

        if ($start->gt($end)) {
            return [];
        }

        $luni = [];
        $current = $start->copy();

        while ($current->lte($end)) {
            $luna = $current->format('Y-m');

            $exists = Factura::query()
                ->where('contract_id', $contract->id)
                ->where('luna', $luna)
                ->exists();

            if (! $exists) {
                $luni[] = $luna;
            }

            $current->addMonth();
        }

        return $luni;
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
        $payload = $this->buildFacturaPayload($factura);

        return Inertia::render('Facturare/Show', [
            'factura' => $payload,
            'downloadUrl' => route('facturare.download', $factura),
        ]);
    }

    public function download(Factura $factura): HttpResponse
    {
        $payload = $this->buildFacturaPayload($factura);
        $numeFirma = (string) ($payload['locatar']['nume'] ?? $payload['contract']['chirias'] ?? '');
        $dataEmitere = $factura->data_emitere?->toDateString()
            ?? $factura->created_at?->toDateString();
        $filename = DocumentFormatter::facturaDownloadFilename($numeFirma, $dataEmitere);

        return Pdf::loadView('documents.factura', ['factura' => $payload])->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFacturaPayload(Factura $factura): array
    {
        $factura->load([
            'anexa.linii',
            'anexa.contract.spatiu.imobil',
            'anexa.contract.spatiu.locatorEntitate',
            'contract.spatiu.imobil',
            'contract.spatiu.locatorEntitate',
        ]);
        $anexa = $factura->anexa;
        $contract = $anexa?->contract ?? $factura->contract;
        $spatiu = $contract?->spatiu;
        $imobil = $spatiu?->imobil;
        $luna = $anexa?->luna ?? $factura->luna;
        $lunaUtilitati = $this->numeLuna($luna);
        $lunaChirie = $this->numeLunaUrmatoare($luna);
        $anUtilitati = $this->anulDinLuna($luna);
        $anChirie = $this->anulLunaUrmatoare($luna);
        $anexaLinii = $anexa?->linii->values() ?? collect();
        $anexaLiniiServiciu = $anexaLinii->filter(fn ($linie): bool => ($linie->tip_linie ?: 'serviciu') !== 'header');
        $locator = $spatiu?->locatorEntitate;
        $chirieTva = $this->tvaChirieLei((float) $factura->chirie_lei, $locator);
        $liniiFactura = [
            [
                'nr_crt' => 1,
                'denumire' => $this->denumireLinieChirieFactura($factura, $lunaChirie, $anChirie),
                'cantitate' => 1,
                'um' => 'LUNA',
                'pret_unitar' => $factura->chirie_lei,
                'valoare' => $factura->chirie_lei,
                'tva' => $chirieTva > 0 ? $chirieTva : null,
            ],
        ];

        foreach ($this->grupeazaUtilitatiPeTva($anexaLiniiServiciu) as $grupTva) {
            if ((float) $grupTva['valoare'] <= 0) {
                continue;
            }

            $liniiFactura[] = [
                'nr_crt' => count($liniiFactura) + 1,
                'denumire' => trim("Utilitati {$grupTva['procent']}% TVA {$lunaUtilitati}".($anUtilitati !== '' ? ' '.$anUtilitati : '')),
                'cantitate' => 1,
                'um' => 'LUNA',
                'pret_unitar' => $grupTva['valoare'],
                'valoare' => $grupTva['valoare'],
                'tva' => $grupTva['tva'],
            ];
        }

        $liniiFactura[] = [
            'nr_crt' => count($liniiFactura) + 1,
            'denumire' => 'Penalitati',
            'cantitate' => null,
            'um' => null,
            'pret_unitar' => null,
            'valoare' => $factura->penalitati,
            'tva' => null,
        ];

        return [
            'id' => $factura->id,
            'numar_factura' => $factura->numar_factura,
            'data_emitere' => $this->dateFactura($factura)['data_emitere'],
            'data_scadenta' => $this->dateFactura($factura)['data_scadenta'],
            'curs_eur' => $factura->curs_eur,
            'chirie_eur' => $factura->chirie_eur,
            'chirie_lei' => $factura->chirie_lei,
            'penalitati' => $factura->penalitati,
            'total' => $factura->total,
            'status' => $factura->status,
            'email_chirias' => $factura->email_chirias,
            'luna' => $luna,
            'luna_utilitati' => $lunaUtilitati,
            'luna_chirie' => $lunaChirie,
            'contract' => [
                'numar' => $contract?->numar_contract,
                'chirias' => $contract?->chirias,
            ],
            'locator' => $this->mapLocatorParty($spatiu?->locatorEntitate),
            'locatar' => $this->mapLocatarParty($contract),
            'spatiu' => [
                'identificator' => $spatiu?->identificator,
            ],
            'imobil' => [
                'id' => $imobil?->id,
                'nume' => $imobil?->nume,
                'adresa' => trim(implode(' ', array_filter([$imobil?->strada, $imobil?->numar]))),
                'localitate' => $imobil?->localitate,
            ],
            'linii' => $liniiFactura,
            'sumar' => $this->sumarFactura($factura, $anexaLiniiServiciu, $locator),
            'anexa_detaliu' => $anexa ? AnexaDocumentPayload::fromModel($anexa) : null,
        ];
    }

    public function destroyAllForImobil(Request $request, Imobil $imobil): RedirectResponse
    {
        $searchSpatiu = trim($request->string('search_spatiu')->toString());
        $searchChirias = trim($request->string('search_chirias')->toString());
        $query = $this->facturiQuery($imobil->id, $searchSpatiu, $searchChirias);
        $deleted = (clone $query)->count();
        $redirectParams = array_filter([
            'imobil' => $imobil->id,
            'search_spatiu' => $searchSpatiu,
            'search_chirias' => $searchChirias,
        ], fn ($value) => $value !== '' && $value !== null);

        if ($deleted === 0) {
            return redirect()
                ->route('facturare.imobil', $redirectParams)
                ->with('warning', 'Nu există facturi de șters.');
        }

        $query->delete();

        $message = ($searchSpatiu !== '' || $searchChirias !== '')
            ? "{$deleted} facturi filtrate au fost șterse."
            : "{$deleted} facturi au fost șterse.";

        return redirect()
            ->route('facturare.imobil', $redirectParams)
            ->with('success', $message);
    }

    public function destroy(Factura $factura): RedirectResponse
    {
        $factura->loadMissing(['anexa.contract.spatiu', 'contract.spatiu']);
        $imobilId = $factura->anexa?->contract?->spatiu?->imobil_id
            ?? $factura->contract?->spatiu?->imobil_id;

        $factura->delete();

        if ($imobilId) {
            return redirect()
                ->route('facturare.imobil', ['imobil' => $imobilId])
                ->with('success', 'Factura a fost ștearsă.');
        }

        return redirect()
            ->route('facturare.index')
            ->with('success', 'Factura a fost ștearsă.');
    }

    private function facturiQuery(?int $imobilId = null, string $searchSpatiu = '', string $searchChirias = '')
    {
        return Factura::query()
            ->with([
                'anexa.contract.spatiu.imobil',
                'anexa.contract.spatiu.configurareAnexa',
                'contract.spatiu.imobil',
                'contract.spatiu.configurareAnexa',
            ])
            ->when($imobilId, fn ($query) => $query->where(function ($query) use ($imobilId) {
                $query->whereHas(
                    'anexa.contract.spatiu',
                    fn ($spatiuQuery) => $spatiuQuery->where('imobil_id', $imobilId)
                )->orWhereHas(
                    'contract.spatiu',
                    fn ($spatiuQuery) => $spatiuQuery->where('imobil_id', $imobilId)
                );
            }))
            ->when($searchSpatiu !== '', fn ($query) => $query->where(function ($query) use ($searchSpatiu) {
                $query->whereHas(
                    'anexa.contract.spatiu',
                    fn ($spatiuQuery) => $spatiuQuery->where('identificator', 'like', '%'.$searchSpatiu.'%')
                )->orWhereHas(
                    'contract.spatiu',
                    fn ($spatiuQuery) => $spatiuQuery->where('identificator', 'like', '%'.$searchSpatiu.'%')
                );
            }))
            ->when($searchChirias !== '', fn ($query) => $query->where(function ($query) use ($searchChirias) {
                $query->whereHas(
                    'anexa.contract',
                    fn ($contractQuery) => $contractQuery->where('chirias', 'like', '%'.$searchChirias.'%')
                )->orWhereHas(
                    'contract',
                    fn ($contractQuery) => $contractQuery->where('chirias', 'like', '%'.$searchChirias.'%')
                );
            }));
    }

    private function mapFacturaForList(Factura $factura): array
    {
        $contract = $factura->anexa?->contract ?? $factura->contract;
        $luna = $factura->anexa?->luna ?? $factura->luna;
        $denumireAnexa = $contract?->spatiu?->configurareAnexa?->denumire;

        return [
            'id' => $factura->id,
            'numar_factura' => $factura->numar_factura ?: '—',
            'anexa' => $luna ?: '—',
            'denumire_anexa' => $denumireAnexa ?: '—',
            'contract' => $contract?->numar_contract ?: '—',
            'imobil' => $contract?->spatiu?->imobil?->nume ?: '—',
            'spatiu' => $contract?->spatiu?->identificator ?: '—',
            'chirias' => $contract?->chirias ?: '—',
            'curs_eur' => $factura->curs_eur,
            'total' => $factura->total,
            'status' => $factura->status,
            'email_facturare' => $contract?->emailFacturare() ?: '—',
            'doar_chirie' => $factura->anexa_id === null,
        ];
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

    private function grupeazaUtilitatiPeTva($linii): array
    {
        $grupuri = [];

        foreach ($linii as $linie) {
            $valoare = (float) ($linie->valoare ?? 0);
            $tva = (float) ($linie->tva_21 ?? 0);
            $procent = $valoare > 0 && $tva > 0
                ? (int) round($tva / $valoare * 100)
                : 0;

            if (! isset($grupuri[$procent])) {
                $grupuri[$procent] = [
                    'procent' => $procent,
                    'valoare' => 0.0,
                    'tva' => 0.0,
                ];
            }

            $grupuri[$procent]['valoare'] += $valoare;
            $grupuri[$procent]['tva'] += $tva;
        }

        return collect($grupuri)
            ->sortByDesc(fn (array $grup): int => $grup['procent'])
            ->values()
            ->map(fn (array $grup): array => [
                'procent' => $grup['procent'],
                'valoare' => round($grup['valoare'], 2),
                'tva' => round($grup['tva'], 2),
            ])
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\AnexaLinie>  $anexaLiniiServiciu
     * @return array{total_fara_tva: float, tva_21: float, tva_11: float, total: float}
     */
    private function sumarFactura(Factura $factura, $anexaLiniiServiciu, ?Locator $locator = null): array
    {
        $grupuriTva = $this->grupeazaUtilitatiPeTva($anexaLiniiServiciu);
        $tvaByProcent = collect($grupuriTva)->keyBy('procent');
        $utilitatiFaraTva = round(collect($grupuriTva)->sum('valoare'), 2);
        $tva21 = round(
            (float) ($tvaByProcent->get(21)['tva'] ?? 0) + $this->tvaChirieLei((float) $factura->chirie_lei, $locator),
            2
        );
        $tva11 = round((float) ($tvaByProcent->get(11)['tva'] ?? 0), 2);
        $totalFaraTva = round((float) $factura->chirie_lei + $utilitatiFaraTva + (float) $factura->penalitati, 2);

        return [
            'total_fara_tva' => $totalFaraTva,
            'tva_21' => $tva21,
            'tva_11' => $tva11,
            'total' => round($totalFaraTva + $tva21 + $tva11, 2),
        ];
    }

    private function tvaChirieLei(float $chirieLei, ?Locator $locator): float
    {
        if (! ($locator?->chirie_cu_tva ?? false) || $chirieLei <= 0) {
            return 0.0;
        }

        return round($chirieLei * self::TVA_CHIRIE_PROCENT / 100, 2);
    }

    /**
     * @return array{data_emitere: string, data_scadenta: string}
     */
    private function dateFactura(Factura $factura): array
    {
        $dataEmitere = $factura->data_emitere?->toDateString()
            ?? ($factura->created_at?->toDateString() ?? now()->toDateString());
        $dataScadenta = $factura->data_scadenta?->toDateString()
            ?? \Carbon\Carbon::parse($dataEmitere)->addDays(5)->toDateString();

        return [
            'data_emitere' => \Carbon\Carbon::parse($dataEmitere)->format('d.m.Y'),
            'data_scadenta' => \Carbon\Carbon::parse($dataScadenta)->format('d.m.Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLocatorParty(?Locator $locator): array
    {
        if ($locator === null) {
            return [
                'nume' => '—',
                'cui' => null,
                'reg_com' => null,
                'adresa' => null,
                'banca' => null,
                'cont_bancar' => null,
                'email' => null,
            ];
        }

        $cui = trim(($locator->cui_are_ro ? 'RO' : '').($locator->cui ?: ''));

        return [
            'nume' => $this->faraDiacriticeDisplay($locator->nume ?: '—'),
            'cui' => $cui !== '' ? $cui : null,
            'reg_com' => $this->faraDiacriticeDisplay($locator->registrul_comertului),
            'adresa' => $this->faraDiacriticeDisplay($locator->adresa),
            'banca' => $this->faraDiacriticeDisplay($locator->banca),
            'cont_bancar' => $this->faraDiacriticeDisplay($locator->cont_bancar),
            'email' => $this->faraDiacriticeDisplay($locator->email),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLocatarParty(?Contract $contract): array
    {
        if ($contract === null) {
            return [
                'tip' => 'pj',
                'nume' => '—',
                'identificator_label' => 'CUI',
                'identificator' => null,
                'ci' => null,
                'adresa' => null,
                'telefon' => null,
                'email' => null,
            ];
        }

        $date = is_array($contract->chirias_date) ? $contract->chirias_date : [];
        $tip = $contract->chirias_tip === 'pf' ? 'pf' : 'pj';

        if ($tip === 'pf') {
            $serieCi = trim((string) ($date['serie_ci'] ?? ''));
            $numarCi = trim((string) ($date['numar_ci'] ?? ''));
            $ci = trim($serieCi.' '.$numarCi);

            return [
                'tip' => 'pf',
                'nume' => $this->faraDiacriticeDisplay($contract->chirias ?: '—'),
                'identificator_label' => 'CNP',
                'identificator' => $this->faraDiacriticeDisplay(($date['cnp'] ?? null) ?: null),
                'ci' => $this->faraDiacriticeDisplay($ci !== '' ? $ci : null),
                'adresa' => $this->faraDiacriticeDisplay(($date['domiciliu'] ?? null) ?: null),
                'telefon' => $this->faraDiacriticeDisplay(($date['telefon'] ?? null) ?: null),
                'email' => $this->faraDiacriticeDisplay(($date['email'] ?? null) ?: null),
            ];
        }

        return [
            'tip' => 'pj',
            'nume' => $this->faraDiacriticeDisplay($contract->chirias ?: '—'),
            'identificator_label' => 'CUI',
            'identificator' => $this->faraDiacriticeDisplay(($date['cui'] ?? null) ?: null),
            'ci' => null,
            'adresa' => $this->faraDiacriticeDisplay(($date['sediu_social'] ?? null) ?: null),
            'telefon' => $this->faraDiacriticeDisplay(($date['telefon'] ?? null) ?: null),
            'email' => $this->faraDiacriticeDisplay(($date['email'] ?? null) ?: null),
        ];
    }

    private function faraDiacriticeDisplay(?string $value): ?string
    {
        if ($value === null || $value === '' || $value === '—') {
            return $value;
        }

        return DocumentFormatter::faraDiacritice($value);
    }

    private function denumireLinieChirieFactura(Factura $factura, string $lunaChirie, string $anChirie): string
    {
        $perioada = trim('Chirie spatiu '.$lunaChirie.($anChirie !== '' ? ' '.$anChirie : ''));
        $chirieEur = (float) $factura->chirie_eur;
        $chirieLei = (float) $factura->chirie_lei;

        if ($chirieEur > 0) {
            return $perioada.' · '.DocumentFormatter::amount($chirieEur).' EUR/luna';
        }

        if ($chirieLei > 0) {
            return $perioada.' · '.DocumentFormatter::amount($chirieLei).' lei/luna';
        }

        return $perioada;
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

    private function anulDinLuna(?string $luna): string
    {
        if (! $luna || strlen($luna) < 4) {
            return '';
        }

        return substr($luna, 0, 4);
    }

    private function anulLunaUrmatoare(?string $luna): string
    {
        if (! $luna || strlen($luna) < 7) {
            return '';
        }

        $an = (int) substr($luna, 0, 4);
        $numarLuna = (int) substr($luna, -2);

        return (string) ($numarLuna === 12 ? $an + 1 : $an);
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

        return [
            'valoare' => Factura::query()->latest()->value('curs_eur') ?: 5,
            'sursa' => 'Ultimul curs salvat / fallback',
        ];
    }

    private function rezumatImobile(float $cursEur): array
    {
        $facturiPeImobil = Factura::query()
            ->with(['anexa.contract.spatiu', 'contract.spatiu'])
            ->get()
            ->groupBy(fn (Factura $factura): ?int => $factura->anexa?->contract?->spatiu?->imobil_id
                ?? $factura->contract?->spatiu?->imobil_id)
            ->map(fn ($facturi) => (object) [
                'facturi_emise' => $facturi->count(),
                'total_utilitati' => round($facturi->sum(fn (Factura $factura): float => (float) ($factura->anexa?->total ?? 0)), 2),
                'total_facturat' => round($facturi->sum(fn (Factura $factura): float => (float) $factura->total), 2),
            ]);

        $anexePeImobil = Anexa::query()
            ->join('contracte', 'contracte.id', '=', 'anexe.contract_id')
            ->join('spatii', 'spatii.id', '=', 'contracte.spatiu_id')
            ->select('spatii.imobil_id')
            ->selectRaw('count(anexe.id) as anexe_emise')
            ->groupBy('spatii.imobil_id')
            ->get()
            ->keyBy('imobil_id');

        $chirieCurentaSql = "case when indexare_2026 is not null and indexare_2026 != 0 then indexare_2026 else coalesce(pret_lunar, 0) end";
        $chiriiPeImobil = Spatiu::query()
            ->where('status', 'inchiriat')
            ->select('imobil_id')
            ->selectRaw("sum(case when moneda = 'RON' then {$chirieCurentaSql} else 0 end) as total_chirie_lei")
            ->selectRaw("sum(case when moneda is null or moneda != 'RON' then {$chirieCurentaSql} else 0 end) as total_chirie_eur")
            ->groupBy('imobil_id')
            ->get()
            ->keyBy('imobil_id');

        return Imobil::query()
            ->withCount([
                'spatii as spatii_inchiriate_count' => fn ($query) => $query->where('status', 'inchiriat'),
            ])
            ->orderBy('nume')
            ->get()
            ->map(function (Imobil $imobil) use ($anexePeImobil, $chiriiPeImobil, $cursEur, $facturiPeImobil): array {
                $facturi = $facturiPeImobil->get($imobil->id);
                $anexe = $anexePeImobil->get($imobil->id);
                $chirii = $chiriiPeImobil->get($imobil->id);
                $totalChirieEur = (float) ($chirii?->total_chirie_eur ?? 0);
                $totalChirieLei = (float) ($chirii?->total_chirie_lei ?? 0);

                return [
                    'id' => $imobil->id,
                    'nume' => $imobil->nume,
                    'localitate' => $imobil->localitate,
                    'spatii_inchiriate' => $imobil->spatii_inchiriate_count,
                    'anexe_emise' => (int) ($anexe?->anexe_emise ?? 0),
                    'facturi_emise' => (int) ($facturi?->facturi_emise ?? 0),
                    'total_chirie_eur' => $totalChirieEur,
                    'total_chirie_lei' => round($totalChirieEur * $cursEur, 2) + $totalChirieLei,
                    'total_utilitati' => (float) ($facturi?->total_utilitati ?? 0),
                    'total_facturat' => (float) ($facturi?->total_facturat ?? 0),
                ];
            })
            ->all();
    }
}
