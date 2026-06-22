<?php

namespace App\Http\Controllers;

use App\Models\Contor;
use App\Models\PerioadaInchiriereFatada;
use App\Models\ConfigurareAnexaImobil;
use App\Models\ConfigurareAnexaLinie;
use App\Models\Contract;
use App\Models\Imobil;
use App\Models\Locator;
use App\Models\Spatiu;
use App\Support\AnexaPreviewPayload;
use App\Support\DecimalInput;
use App\Support\InternalReturnUrl;
use App\Support\SincronizareContoareDinAnexa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpatiuController extends Controller
{
    public function index(Request $request): Response
    {
        $localitate = $request->string('localitate')->toString();
        $search = $request->string('search')->toString();
        $status = $this->normalizeStatusFilter($request->string('status')->toString());
        $documente = $this->normalizeDocumenteFilter($request->string('documente')->toString());
        $etaj = $this->normalizeEtajFilter($request->string('etaj')->toString());
        $imobilId = $request->integer('imobil_id') ?: null;
        $globalSpatiiList = $request->boolean('global');

        if ($imobilId) {
            return $this->indexSpatiiForImobil($imobilId, $search, $status, $documente);
        }

        if ($globalSpatiiList || $status !== '') {
            return $this->indexSpatiiGlobalFilter($localitate, $search, $status, $etaj);
        }

        return $this->indexImobileList($localitate, $search);
    }

    private function indexImobileList(string $localitate, string $search): Response
    {
        $query = Imobil::query()
            ->withCount([
                'spatii as spatii_total_live',
                'spatii as spatii_libere_live' => fn ($query) => $query->where('status', 'liber'),
                'spatii as spatii_inchiriate_live' => fn ($query) => $query->where('status', 'inchiriat'),
                'spatii as spatii_comune_live' => fn ($query) => $query->where('status', 'comun'),
                'spatii as spatii_administrative_live' => fn ($query) => $query->where('status', 'administrativ'),
            ])
            ->orderBy('ordine')
            ->orderBy('id');

        if ($localitate !== '') {
            $query->where('localitate', $localitate);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('nume', 'like', "%{$search}%")
                    ->orWhereHas('spatii', function ($query) use ($search) {
                        $query->where('identificator', 'like', "%{$search}%")
                            ->orWhere('locator', 'like', "%{$search}%")
                            ->orWhere('chirias', 'like', "%{$search}%");
                    });
            });
        }

        $imobile = $query->get()->map(fn (Imobil $imobil): array => [
            'id' => $imobil->id,
            'nume' => $imobil->nume,
            'localitate' => $imobil->localitate,
            'spatii_total' => $imobil->spatii_total_live,
            'spatii_libere' => $imobil->spatii_libere_live,
            'spatii_inchiriate' => $imobil->spatii_inchiriate_live,
            'spatii_comune' => $imobil->spatii_comune_live,
            'spatii_administrative' => $imobil->spatii_administrative_live,
        ]);

        return Inertia::render('Spatii/Index', [
            'imobile' => $imobile,
            'imobil' => null,
            'spatii' => [],
            'localitati' => Imobil::query()->select('localitate')->distinct()->orderBy('localitate')->pluck('localitate'),
            'filters' => [
                'localitate' => $localitate,
                'search' => $search,
                'status' => '',
                'documente' => '',
                'etaj' => '',
                'imobil_id' => null,
            ],
        ]);
    }

    private function indexSpatiiForImobil(int $imobilId, string $search, string $status, string $documente): Response
    {
        $imobil = Imobil::query()->findOrFail($imobilId);

        $query = Spatiu::query()
            ->with([
                'imobil',
                'locatorEntitate',
                'contracte' => fn ($query) => $query->where('status', 'activ')->latest('id'),
            ])
            ->withExists(['contracte as are_contract_inregistrat'])
            ->withExists(['contracte as are_contract_activ' => fn ($query) => $query->where('status', 'activ')])
            ->where('imobil_id', $imobil->id)
            ->orderBy('ordine')
            ->orderBy('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $this->applyDocumenteFilter($query, $documente);

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('identificator', 'like', "%{$search}%")
                    ->orWhere('locator', 'like', "%{$search}%")
                    ->orWhere('chirias', 'like', "%{$search}%");
            });
        }

        $spatii = $query->get()->map(fn (Spatiu $spatiu): array => $this->mapSpatiuForList($spatiu));

        return Inertia::render('Spatii/Index', [
            'imobile' => [],
            'imobil' => [
                'id' => $imobil->id,
                'nume' => $imobil->nume,
                'localitate' => $imobil->localitate,
            ],
            'spatii' => $spatii,
            'localitati' => Imobil::query()->select('localitate')->distinct()->orderBy('localitate')->pluck('localitate'),
            'filters' => [
                'localitate' => '',
                'search' => $search,
                'status' => $status,
                'documente' => $documente,
                'etaj' => '',
                'imobil_id' => $imobil->id,
            ],
        ]);
    }

    private function indexSpatiiGlobalFilter(string $localitate, string $search, string $status, string $etaj): Response
    {
        $query = Spatiu::query()
            ->with([
                'imobil',
                'locatorEntitate',
                'contracte' => fn ($query) => $query->where('status', 'activ')->latest('id'),
            ])
            ->withExists(['contracte as are_contract_inregistrat'])
            ->withExists(['contracte as are_contract_activ' => fn ($query) => $query->where('status', 'activ')]);

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($localitate !== '') {
            $query->whereHas('imobil', fn ($imobilQuery) => $imobilQuery->where('localitate', $localitate));
        }

        if ($etaj !== '') {
            $query->where('etaj', $etaj);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('identificator', 'like', "%{$search}%")
                    ->orWhere('locator', 'like', "%{$search}%")
                    ->orWhere('chirias', 'like', "%{$search}%")
                    ->orWhereHas('imobil', fn ($imobilQuery) => $imobilQuery->where('nume', 'like', "%{$search}%"));
            });
        }

        $spatii = $query
            ->join('imobile', 'spatii.imobil_id', '=', 'imobile.id')
            ->orderBy('imobile.ordine')
            ->orderBy('imobile.id')
            ->orderBy('spatii.ordine')
            ->orderBy('spatii.id')
            ->select('spatii.*')
            ->get()
            ->map(fn (Spatiu $spatiu): array => $this->mapSpatiuForList($spatiu));

        return Inertia::render('Spatii/Index', [
            'imobile' => [],
            'imobil' => null,
            'spatii' => $spatii,
            'localitati' => Imobil::query()->select('localitate')->distinct()->orderBy('localitate')->pluck('localitate'),
            'filters' => [
                'localitate' => $localitate,
                'search' => $search,
                'status' => $status,
                'documente' => '',
                'etaj' => $etaj,
                'global' => true,
                'imobil_id' => null,
            ],
        ]);
    }

    private function normalizeEtajFilter(string $etaj): string
    {
        $allowed = Spatiu::ETAJ_OPTIONS;

        return in_array($etaj, $allowed, true) ? $etaj : '';
    }

    private function normalizeDocumenteFilter(string $documente): string
    {
        $allowed = ['fara_anexa', 'fara_contract', 'cu_contract', 'cu_anexa'];

        return in_array($documente, $allowed, true) ? $documente : '';
    }

    private function applyDocumenteFilter($query, string $documente): void
    {
        match ($documente) {
            'fara_anexa' => $query
                ->whereNotIn('status', ['administrativ', 'comun'])
                ->whereNull('configurare_anexa_id'),
            'fara_contract' => $query
                ->where('status', 'inchiriat')
                ->whereDoesntHave('contracte'),
            'cu_contract' => $query->whereHas('contracte'),
            'cu_anexa' => $query->whereNotNull('configurare_anexa_id'),
            default => null,
        };
    }

    private function normalizeRegimIncalzireFilter(string $regimIncalzire): string
    {
        $allowed = ['integral', 'partial', 'neincalzit', 'manual'];

        return in_array($regimIncalzire, $allowed, true) ? $regimIncalzire : '';
    }

    private function normalizeStatusFilter(string $status): string
    {
        $allowed = ['liber', 'rezervat', 'inchiriat', 'comun', 'administrativ'];

        return in_array($status, $allowed, true) ? $status : '';
    }

    private function mapSpatiuForList(Spatiu $spatiu): array
    {
        $suprafata = $spatiu->suprafata_contractuala_mp;
        $contractActiv = $spatiu->contracte->first();
        $chirieCurenta = $contractActiv
            ? $contractActiv->chirieAplicabilaLa()
            : ($spatiu->indexare_2026 ?: $spatiu->pret_lunar);
        $sursaChirieCurenta = $contractActiv
            ? ($contractActiv->folosesteCrestereChirieLa() ? 'Creștere chirie' : 'Contract activ')
            : ($spatiu->indexare_2026 ? 'Indexare 2026' : null);
        $pretMpCurent = $suprafata && $chirieCurenta
            ? number_format((float) $chirieCurenta / (float) $suprafata, 2, '.', '')
            : null;

        return [
            'id' => $spatiu->id,
            'imobil_id' => $spatiu->imobil_id,
            'identificator' => $spatiu->identificator,
            'etaj' => $spatiu->etaj ?: '—',
            'imobil' => $spatiu->imobil?->nume ?: '—',
            'localitate' => $spatiu->imobil?->localitate ?: '—',
            'suprafata_contractuala_mp' => $suprafata,
            'status' => $spatiu->status,
            'pret_lunar' => $spatiu->pret_lunar,
            'indexare_2026' => $spatiu->indexare_2026,
            'chirie_lunara_curenta' => $chirieCurenta,
            'sursa_chirie_curenta' => $sursaChirieCurenta,
            'pret_mp_curent' => $pretMpCurent,
            'moneda' => $spatiu->moneda,
            'moneda_label' => $spatiu->monedaLabel(),
            'locator' => $spatiu->locatorEntitate?->nume ?: ($spatiu->getAttribute('locator') ?: '—'),
            'chirias' => $spatiu->chirias ?: '—',
            'de_lamurit' => (bool) $spatiu->de_lamurit,
            'de_lamurit_detaliu' => $spatiu->de_lamurit ? ($spatiu->de_lamurit_detaliu ?: null) : null,
            'marcat_galben' => (bool) $spatiu->marcat_galben,
            'marcat_verde' => (bool) $spatiu->marcat_verde,
            'necesita_anexa' => ! in_array($spatiu->status, ['administrativ', 'comun'], true),
            'are_anexa_alocata' => $spatiu->configurare_anexa_id !== null,
            'are_contract_inregistrat' => (bool) ($spatiu->are_contract_inregistrat ?? false),
            'are_contract_activ' => (bool) ($spatiu->are_contract_activ ?? false),
        ];
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Spatii/Create', [
            'imobile' => $this->imobileForSelect(),
            'locatori' => $this->locatoriForSelect(),
            'configurariAnexe' => $this->configurariAnexeForSelect(),
            'campuriSpatiuVizibile' => $this->campuriSpatiuVizibileForSelect(),
            'initialImobilId' => $request->integer('imobil_id') ?: null,
            'perioadeFatada' => [],
        ]);
    }

    public function edit(Request $request, Spatiu $spatiu): Response
    {
        return Inertia::render('Spatii/Create', $this->editPageProps($request, $spatiu));
    }

    /**
     * @return array<string, mixed>
     */
    public function editPageProps(Request $request, Spatiu $spatiu): array
    {
        $contract = $spatiu->contracte()
            ->where('status', 'activ')
            ->orderByDesc('id')
            ->first()
            ?? $spatiu->contracte()->orderByDesc('id')->first();

        $showDocumente = $this->showDocumenteForSpatiu($spatiu);

        return [
            'imobile' => $this->imobileForSelect(),
            'locatori' => $this->locatoriForSelect(),
            'configurariAnexe' => $this->configurariAnexeForSelect(),
            'campuriSpatiuVizibile' => $this->campuriSpatiuVizibileForSelect(),
            'canDeleteSpatii' => $this->isOwner($request),
            'showDocumente' => $showDocumente,
            'contractActiv' => $contract ? [
                'id' => $contract->id,
                'numar_contract' => $contract->numar_contract,
                'chirias' => $contract->chirias,
                'chirie' => $contract->chirie,
                'moneda' => $contract->moneda,
                'status' => $contract->status,
                'perioada' => optional($contract->data_start)->format('d.m.Y').' - '.(optional($contract->data_end)->format('d.m.Y') ?: 'nedeterminat'),
            ] : null,
            'spatiu' => [
                'id' => $spatiu->id,
                'imobil_id' => $spatiu->imobil_id,
                'identificator' => $spatiu->identificator,
                'suprafata_contractuala_mp' => $spatiu->suprafata_contractuala_mp,
                'corp' => $spatiu->corp,
                'etaj' => $spatiu->etaj,
                'persoane_standard' => $spatiu->persoane_standard,
                'regim_incalzire' => $spatiu->regim_incalzire,
                'procent_incalzire_override' => $spatiu->procent_incalzire_override,
                'retim_direct' => $spatiu->retim_direct,
                'status' => $spatiu->status,
                'pret_lunar' => $spatiu->pret_lunar,
                'indexare_2026' => $spatiu->indexare_2026,
                'moneda' => $spatiu->moneda,
                'moneda_label' => $spatiu->monedaLabel(),
                'locator_id' => $spatiu->locator_id,
                'configurare_anexa_id' => $spatiu->configurare_anexa_id,
                'chirias' => $spatiu->chirias,
                'observatii' => $spatiu->observatii,
                'de_lamurit' => (bool) $spatiu->de_lamurit,
                'de_lamurit_detaliu' => $spatiu->de_lamurit_detaliu,
                'marcat_galben' => (bool) $spatiu->marcat_galben,
                'marcat_verde' => (bool) $spatiu->marcat_verde,
            ],
            'perioadeFatada' => $this->perioadeFatadaForSpatiu($spatiu),
        ];
    }

    /**
     * @return list<array{id: int, data_start: string, data_end: string, chirias: string, chirie_lunara: string, moneda: string}>
     */
    public function perioadeFatadaForSpatiu(Spatiu $spatiu): array
    {
        if ($spatiu->etaj !== 'Fațadă') {
            return [];
        }

        return $spatiu->perioadeInchiriereFatada()
            ->orderBy('data_start')
            ->get()
            ->map(fn (PerioadaInchiriereFatada $perioada): array => [
                'id' => $perioada->id,
                'data_start' => $perioada->data_start->format('Y-m-d'),
                'data_end' => $perioada->data_end->format('Y-m-d'),
                'chirias' => $perioada->chirias,
                'chirie_lunara' => $perioada->chirie_lunara,
                'moneda' => $perioada->moneda,
            ])
            ->values()
            ->all();
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'imobil_id' => ['required', 'exists:imobile,id'],
            'ordine' => ['required', 'array', 'min:1'],
            'ordine.*' => ['integer', 'distinct', 'exists:spatii,id'],
        ]);

        $imobilId = (int) $validated['imobil_id'];
        $ids = collect($validated['ordine'])->map(fn ($id) => (int) $id)->values();

        $spatiiCount = Spatiu::query()
            ->where('imobil_id', $imobilId)
            ->whereIn('id', $ids)
            ->count();

        abort_unless($spatiiCount === $ids->count(), 422, 'Ordinea conține spații care nu aparțin imobilului selectat.');

        foreach ($ids as $index => $spatiuId) {
            Spatiu::query()
                ->whereKey($spatiuId)
                ->where('imobil_id', $imobilId)
                ->update(['ordine' => $index + 1]);
        }

        return back();
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $validated['ordine'] = $this->nextOrdineForImobil((int) $validated['imobil_id']);

        $spatiu = Spatiu::create($validated);
        $this->syncPersoaneForAdministrativ($spatiu);
        $this->syncPersoaneForComun($spatiu);
        SincronizareContoareDinAnexa::syncForSpatiu($spatiu->fresh());
        $spatiu->imobil->recalculeazaSpatii();

        return redirect($this->spatiiIndexUrl($spatiu->imobil_id))->with('success', 'Spațiul a fost adăugat.');
    }

    public function update(Request $request, Spatiu $spatiu): RedirectResponse
    {
        $oldImobil = $spatiu->imobil;
        $spatiu->update($this->validatedData($request, $spatiu));
        $this->syncPersoaneForAdministrativ($spatiu->fresh());
        $this->syncPersoaneForComun($spatiu->fresh());
        SincronizareContoareDinAnexa::syncForSpatiu($spatiu->fresh());

        $spatiu->refresh()->imobil->recalculeazaSpatii();

        if ($oldImobil->isNot($spatiu->imobil)) {
            $oldImobil->recalculeazaSpatii();
        }

        return redirect($this->spatiiIndexUrl($spatiu->imobil_id))->with('success', 'Spațiul a fost actualizat.');
    }

    public function destroy(Request $request, Spatiu $spatiu): RedirectResponse
    {
        abort_unless($this->isOwner($request), 403);

        $imobilId = $spatiu->imobil_id;
        $imobil = $spatiu->imobil;

        Contor::query()->where('spatiu_id', $spatiu->id)->delete();
        $spatiu->delete();

        $imobil->recalculeazaSpatii();

        return redirect($this->spatiiIndexUrl($imobilId))->with('success', 'Spațiul a fost șters.');
    }

    public function cloneAnexaIndividuala(Request $request, Spatiu $spatiu): RedirectResponse
    {
        abort_unless($spatiu->configurare_anexa_id, 422, 'Spațiul nu are o anexă alocată.');

        $sursa = ConfigurareAnexaImobil::query()
            ->with('linii')
            ->findOrFail($spatiu->configurare_anexa_id);

        $denumireSugestie = trim($sursa->denumire.' · '.$spatiu->identificator);
        $anexaAnterioaraId = $sursa->id;

        $noua = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $spatiu->imobil_id,
            'denumire' => $denumireSugestie,
            'implicit' => false,
            'activ' => true,
            'observatii' => $sursa->observatii,
        ]);

        foreach ($sursa->linii as $linie) {
            $noua->linii()->create($this->linieConfigurareCopie($linie));
        }

        $spatiu->update(['configurare_anexa_id' => $noua->id]);
        SincronizareContoareDinAnexa::syncForSpatiu($spatiu->fresh());

        $returnUrl = InternalReturnUrl::normalize($request->input('return_url'))
            ?: route('spatii.edit', $spatiu);

        return redirect()
            ->route('configurare-anexa.edit', [
                'configurare' => $noua,
                'return_url' => $returnUrl,
                'personalizare' => 1,
                'spatiu_id' => $spatiu->id,
                'anexa_anterioara_id' => $anexaAnterioaraId,
                'denumire_sugestie' => $denumireSugestie,
            ])
            ->with('success', 'Anexa a fost copiată. Pune un nume pentru varianta acestui spațiu.');
    }

    public function previewAnexa(Request $request, Spatiu $spatiu): Response
    {
        $validated = $request->validate([
            'configurare_anexa_id' => ['nullable', 'integer', 'exists:configurari_anexe_imobil,id'],
            'luna' => ['nullable', 'string', 'size:7'],
        ]);

        $configurareId = (int) ($validated['configurare_anexa_id'] ?? $spatiu->configurare_anexa_id ?: 0);

        abort_unless($configurareId > 0, 422, 'Alege o anexă înainte de previzualizare.');

        $configurare = ConfigurareAnexaImobil::query()
            ->whereKey($configurareId)
            ->where('imobil_id', $spatiu->imobil_id)
            ->firstOrFail();

        $contract = $spatiu->contracte()
            ->where('status', 'activ')
            ->orderByDesc('id')
            ->first();

        $returnUrl = InternalReturnUrl::normalize($request->string('return_url')->toString())
            ?: route('spatii.edit', $spatiu);

        return Inertia::render('Anexe/Show', [
            'anexa' => AnexaPreviewPayload::forSpatiu(
                $spatiu,
                $configurare,
                $contract,
                $validated['luna'] ?? null,
            ),
            'downloadUrl' => null,
            'previewMode' => true,
            'returnUrl' => $returnUrl,
            'returnLabel' => 'Înapoi la spațiu',
        ]);
    }

    public function updateAnexa(Request $request, Spatiu $spatiu): RedirectResponse
    {
        $validated = $request->validate([
            'configurare_anexa_id' => ['nullable', 'exists:configurari_anexe_imobil,id'],
        ]);

        $configurareAnexaId = $validated['configurare_anexa_id'] ?? null;

        if ($configurareAnexaId) {
            $belongsToImobil = ConfigurareAnexaImobil::query()
                ->whereKey($configurareAnexaId)
                ->where('imobil_id', $spatiu->imobil_id)
                ->exists();

            abort_unless($belongsToImobil, 422, 'Configurarea de anexă nu aparține imobilului spațiului.');
        }

        $spatiu->update(['configurare_anexa_id' => $configurareAnexaId]);
        SincronizareContoareDinAnexa::syncForSpatiu($spatiu->fresh());

        return back()->with('success', 'Anexa spațiului a fost actualizată.');
    }

    public function updateMarcaj(Request $request, Spatiu $spatiu): RedirectResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'in:marcat_galben,marcat_verde,de_lamurit'],
            'value' => ['required', 'boolean'],
        ]);

        $updates = [
            'marcat_galben' => false,
            'marcat_verde' => false,
            'de_lamurit' => false,
        ];

        if ($validated['value']) {
            $updates[$validated['field']] = true;
        }

        if (! ($updates['de_lamurit'] ?? false)) {
            $updates['de_lamurit_detaliu'] = null;
        }

        $spatiu->update($updates);

        return back();
    }

    private function validatedData(Request $request, ?Spatiu $spatiu = null): array
    {
        $request->merge($this->normalizeDecimalFields($request->all()));

        if ($request->input('regim_incalzire') === 'manual') {
            $request->merge(['regim_incalzire' => 'integral']);
        }

        $validated = $request->validate([
            'imobil_id' => ['required', 'exists:imobile,id'],
            'identificator' => ['required', 'string', 'max:255'],
            'suprafata_contractuala_mp' => ['nullable', 'numeric', 'min:0'],
            'corp' => ['nullable', 'string', 'max:255'],
            'etaj' => ['nullable', 'string', 'in:'.implode(',', Spatiu::ETAJ_OPTIONS)],
            'regim_incalzire' => ['nullable', 'in:integral,partial,neincalzit'],
            'procent_incalzire_override' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'retim_direct' => ['boolean'],
            'status' => ['required', 'in:liber,rezervat,inchiriat,comun,administrativ'],
            'pret_lunar' => ['nullable', 'numeric', 'min:0'],
            'indexare_2026' => ['nullable', 'numeric', 'min:0'],
            'moneda' => ['nullable', 'string', 'in:EUR,RON'],
            'locator_id' => ['nullable', 'exists:locatori,id'],
            'configurare_anexa_id' => ['nullable', 'exists:configurari_anexe_imobil,id'],
            'chirias' => ['nullable', 'string', 'max:255'],
            'observatii' => ['nullable', 'string', 'max:5000'],
            'de_lamurit' => ['boolean'],
            'de_lamurit_detaliu' => ['nullable', 'string', 'max:2000'],
            'marcat_galben' => ['boolean'],
            'marcat_verde' => ['boolean'],
        ]);

        $validated['retim_direct'] = (bool) ($validated['retim_direct'] ?? false);
        $validated['de_lamurit'] = (bool) ($validated['de_lamurit'] ?? false);
        $validated['marcat_galben'] = (bool) ($validated['marcat_galben'] ?? false);
        $validated['marcat_verde'] = (bool) ($validated['marcat_verde'] ?? false);
        $validated = $this->normalizeMarcaje($validated);
        $validated = $this->normalizeDeLamuritDetaliu($validated);
        $validated = $this->normalizeSpatiuByStatus($validated, $spatiu);

        if (blank($validated['etaj'] ?? null)) {
            $validated['etaj'] = 'Parter';
        }

        $validated['moneda'] = Spatiu::normalizeMoneda(
            $validated['etaj'] ?? null,
            $validated['moneda'] ?? null
        );

        if ($spatiu && (int) $validated['imobil_id'] !== (int) $spatiu->imobil_id) {
            $validated['ordine'] = $this->nextOrdineForImobil((int) $validated['imobil_id']);
        }

        if (! empty($validated['configurare_anexa_id'])) {
            $belongsToImobil = ConfigurareAnexaImobil::query()
                ->whereKey($validated['configurare_anexa_id'])
                ->where('imobil_id', $validated['imobil_id'])
                ->exists();

            abort_unless($belongsToImobil, 422, 'Configurarea de anexă nu aparține imobilului ales.');
        }

        $validated['locator'] = ($validated['locator_id'] ?? null)
            ? Locator::query()->whereKey($validated['locator_id'])->value('nume')
            : null;

        if (Spatiu::etajFaraPersoane($validated['etaj'] ?? null)) {
            $validated['persoane_declarate'] = 0;
            $validated['regim_incalzire'] = 'neincalzit';
            $validated['procent_incalzire_override'] = null;

            if (($validated['status'] ?? '') !== 'inchiriat') {
                $validated['configurare_anexa_id'] = null;
            }
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeDecimalFields(array $input): array
    {
        foreach ([
            'suprafata_contractuala_mp',
            'pret_lunar',
            'indexare_2026',
            'procent_incalzire_override',
        ] as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $input[$field] = DecimalInput::normalize($input[$field]);
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeMarcaje(array $validated): array
    {
        $fields = ['marcat_galben', 'marcat_verde', 'de_lamurit'];
        $active = null;

        foreach ($fields as $field) {
            if ($validated[$field] ?? false) {
                $active = $field;
                break;
            }
        }

        foreach ($fields as $field) {
            $validated[$field] = $field === $active;
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeDeLamuritDetaliu(array $validated): array
    {
        if (! ($validated['de_lamurit'] ?? false)) {
            $validated['de_lamurit_detaliu'] = null;

            return $validated;
        }

        $detaliu = trim((string) ($validated['de_lamurit_detaliu'] ?? ''));
        $validated['de_lamurit_detaliu'] = $detaliu !== '' ? $detaliu : null;

        return $validated;
    }

    private function normalizeSpatiuByStatus(array $validated, ?Spatiu $spatiu = null): array
    {
        if (($validated['status'] ?? '') === 'administrativ') {
            $validated['regim_incalzire'] = 'neincalzit';
            $validated['procent_incalzire_override'] = null;
            $validated['locator_id'] = null;
            $validated['configurare_anexa_id'] = null;
            $validated['chirias'] = null;
            $validated['indexare_2026'] = null;

            return $validated;
        }

        if (($validated['status'] ?? '') === 'comun') {
            $validated['regim_incalzire'] = 'neincalzit';
            $validated['procent_incalzire_override'] = null;
            $validated['locator_id'] = null;
            $validated['configurare_anexa_id'] = null;
            $validated['persoane_declarate'] = 0;

            return $validated;
        }

        $becomingLiber = ($validated['status'] ?? '') === 'liber'
            && ($spatiu === null || $spatiu->status !== 'liber');

        if ($becomingLiber) {
            $pretLunar = (float) ($validated['pret_lunar'] ?? $spatiu?->pret_lunar ?? 0);
            $indexare = (float) ($validated['indexare_2026'] ?? $spatiu?->indexare_2026 ?? 0);

            if ($indexare > 0 && $indexare > $pretLunar) {
                $validated['pret_lunar'] = $indexare;
            }

            $validated['indexare_2026'] = null;
        }

        $validated['regim_incalzire'] = $validated['regim_incalzire']
            ?? (in_array($validated['status'] ?? '', ['liber', 'inchiriat'], true) ? 'integral' : 'neincalzit');
        $validated['procent_incalzire_override'] = $validated['regim_incalzire'] === 'partial'
            ? ($validated['procent_incalzire_override'] ?? null)
            : null;

        return $validated;
    }

    private function syncPersoaneForAdministrativ(Spatiu $spatiu): void
    {
        if ($spatiu->status !== 'administrativ') {
            return;
        }

        if ($spatiu->persoane_declarate !== null) {
            $spatiu->update(['persoane_declarate' => null]);
        }
    }

    private function syncPersoaneForComun(Spatiu $spatiu): void
    {
        if ($spatiu->status !== 'comun') {
            return;
        }

        if ((int) $spatiu->persoane_declarate !== 0) {
            $spatiu->update(['persoane_declarate' => 0]);
        }
    }

    private function nextOrdineForImobil(int $imobilId): int
    {
        $maxOrdine = Spatiu::query()->where('imobil_id', $imobilId)->max('ordine');

        return ((int) $maxOrdine) + 1;
    }

    private function isOwner(Request $request): bool
    {
        return true;
    }

    private function showDocumenteForSpatiu(Spatiu $spatiu): bool
    {
        return $spatiu->status === 'inchiriat';
    }

    /**
     * @return array<string, mixed>
     */
    private function linieConfigurareCopie(ConfigurareAnexaLinie $linie): array
    {
        return [
            'ordine' => $linie->ordine,
            'tip_linie' => $linie->tip_linie,
            'denumire' => $linie->denumire,
            'nr_crt' => $linie->nr_crt,
            'index_vechi' => $linie->index_vechi,
            'index_nou' => $linie->index_nou,
            'facturat' => $linie->facturat,
            'coeficient' => $linie->coeficient,
            'um' => $linie->um,
            'pret_unitar' => $linie->pret_unitar,
            'valoare' => $linie->valoare,
            'tva_21' => $linie->tva_21,
            'tip_calcul' => $linie->tip_calcul,
            'apare_cu_zero' => $linie->apare_cu_zero,
            'activ' => $linie->activ,
            'observatii' => $linie->observatii,
        ];
    }

    private function spatiiIndexUrl(int $imobilId): string
    {
        return '/spatii?'.http_build_query(['imobil_id' => $imobilId]);
    }

    private function imobileForSelect()
    {
        return Imobil::query()
            ->orderBy('ordine')
            ->orderBy('id')
            ->get(['id', 'nume', 'localitate'])
            ->map(fn (Imobil $imobil) => [
                'id' => $imobil->id,
                'label' => "{$imobil->nume} ({$imobil->localitate})",
            ]);
    }

    private function campuriSpatiuVizibileForSelect()
    {
        return Imobil::query()
            ->orderBy('ordine')
            ->orderBy('id')
            ->get(['id', 'campuri_spatiu_vizibile'])
            ->mapWithKeys(fn (Imobil $imobil): array => [
                $imobil->id => $imobil->campuriSpatiuVizibilePentruForm(),
            ]);
    }

    private function locatoriForSelect()
    {
        return Locator::query()
            ->orderBy('nume')
            ->get(['id', 'nume'])
            ->map(fn (Locator $locator) => [
                'id' => $locator->id,
                'nume' => $locator->nume,
            ]);
    }

    private function configurariAnexeForSelect()
    {
        return ConfigurareAnexaImobil::query()
            ->where('activ', true)
            ->withCount(['linii', 'spatii'])
            ->orderByDesc('implicit')
            ->orderBy('denumire')
            ->get(['id', 'imobil_id', 'denumire', 'implicit'])
            ->groupBy('imobil_id')
            ->map(fn ($configurari) => $configurari->map(fn (ConfigurareAnexaImobil $configurare) => [
                'id' => $configurare->id,
                'implicit' => $configurare->implicit,
                'denumire' => $this->configurareAnexaLabelForSelect($configurare),
                'linii_count' => $configurare->linii_count,
                'spatii_count' => $configurare->spatii_count,
            ])->values());
    }

    private function configurareAnexaLabelForSelect(ConfigurareAnexaImobil $configurare): string
    {
        $label = trim($configurare->denumire);

        if ($label === '') {
            $label = '(fără nume — completează personalizarea)';
        }

        return $configurare->implicit ? "{$label} (implicită)" : $label;
    }
}
