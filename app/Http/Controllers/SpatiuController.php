<?php

namespace App\Http\Controllers;

use App\Models\ConfigurareAnexaImobil;
use App\Models\Imobil;
use App\Models\Locator;
use App\Models\Spatiu;
use App\Support\DecimalInput;
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
        $regimIncalzire = $this->normalizeRegimIncalzireFilter($request->string('regim_incalzire')->toString());
        $imobilId = $request->integer('imobil_id') ?: null;

        if ($imobilId) {
            return $this->indexSpatiiForImobil($imobilId, $search, $status, $regimIncalzire);
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
            ->orderBy('nume');

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
                'regim_incalzire' => '',
                'imobil_id' => null,
            ],
        ]);
    }

    private function indexSpatiiForImobil(int $imobilId, string $search, string $status, string $regimIncalzire): Response
    {
        $imobil = Imobil::query()->findOrFail($imobilId);

        $query = Spatiu::query()
            ->with(['imobil', 'locatorEntitate'])
            ->where('imobil_id', $imobil->id)
            ->orderBy('ordine')
            ->orderBy('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($regimIncalzire !== '') {
            $query->where('regim_incalzire', $regimIncalzire);
        }

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
                'regim_incalzire' => $regimIncalzire,
                'imobil_id' => $imobil->id,
            ],
        ]);
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
        $chirieCurenta = $spatiu->indexare_2026 ?: ($spatiu->indexare_2025 ?: $spatiu->pret_lunar);
        $sursaChirieCurenta = $spatiu->indexare_2026
            ? 'Indexare 2026'
            : ($spatiu->indexare_2025 ? 'Indexare 2025' : null);
        $pretMpCurent = $suprafata && $chirieCurenta
            ? number_format((float) $chirieCurenta / (float) $suprafata, 2, '.', '')
            : null;

        return [
            'id' => $spatiu->id,
            'imobil_id' => $spatiu->imobil_id,
            'identificator' => $spatiu->identificator,
            'imobil' => $spatiu->imobil?->nume ?: '—',
            'localitate' => $spatiu->imobil?->localitate ?: '—',
            'suprafata_contractuala_mp' => $suprafata,
            'status' => $spatiu->status,
            'pret_lunar' => $spatiu->pret_lunar,
            'indexare_2025' => $spatiu->indexare_2025,
            'indexare_2026' => $spatiu->indexare_2026,
            'chirie_lunara_curenta' => $chirieCurenta,
            'sursa_chirie_curenta' => $sursaChirieCurenta,
            'pret_mp_curent' => $pretMpCurent,
            'moneda' => $spatiu->moneda,
            'locator' => $spatiu->locatorEntitate?->nume ?: ($spatiu->getAttribute('locator') ?: '—'),
            'chirias' => $spatiu->chirias ?: '—',
            'de_lamurit' => (bool) $spatiu->de_lamurit,
            'marcat_galben' => (bool) $spatiu->marcat_galben,
            'marcat_verde' => (bool) $spatiu->marcat_verde,
            'are_anexa_alocata' => $spatiu->configurare_anexa_id !== null,
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
        ]);
    }

    public function edit(Spatiu $spatiu): Response
    {
        return Inertia::render('Spatii/Create', [
            'imobile' => $this->imobileForSelect(),
            'locatori' => $this->locatoriForSelect(),
            'configurariAnexe' => $this->configurariAnexeForSelect(),
            'campuriSpatiuVizibile' => $this->campuriSpatiuVizibileForSelect(),
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
                'indexare_2025' => $spatiu->indexare_2025,
                'indexare_2026' => $spatiu->indexare_2026,
                'moneda' => $spatiu->moneda,
                'locator_id' => $spatiu->locator_id,
                'configurare_anexa_id' => $spatiu->configurare_anexa_id,
                'chirias' => $spatiu->chirias,
                'observatii' => $spatiu->observatii,
                'de_lamurit' => (bool) $spatiu->de_lamurit,
                'marcat_galben' => (bool) $spatiu->marcat_galben,
                'marcat_verde' => (bool) $spatiu->marcat_verde,
            ],
        ]);
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
        $spatiu->imobil->recalculeazaSpatii();

        return redirect($this->spatiiIndexUrl($spatiu->imobil_id))->with('success', 'Spațiul a fost adăugat.');
    }

    public function update(Request $request, Spatiu $spatiu): RedirectResponse
    {
        $oldImobil = $spatiu->imobil;
        $spatiu->update($this->validatedData($request, $spatiu));
        $this->syncPersoaneForAdministrativ($spatiu->fresh());
        $this->syncPersoaneForComun($spatiu->fresh());

        $spatiu->refresh()->imobil->recalculeazaSpatii();

        if ($oldImobil->isNot($spatiu->imobil)) {
            $oldImobil->recalculeazaSpatii();
        }

        return redirect($this->spatiiIndexUrl($spatiu->imobil_id))->with('success', 'Spațiul a fost actualizat.');
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
            'etaj' => ['nullable', 'string', 'in:-1,Parter,1,2,3,4,5,Acoperiș,Fațadă'],
            'regim_incalzire' => ['nullable', 'in:integral,partial,neincalzit'],
            'procent_incalzire_override' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'retim_direct' => ['boolean'],
            'status' => ['required', 'in:liber,rezervat,inchiriat,comun,administrativ'],
            'pret_lunar' => ['nullable', 'numeric', 'min:0'],
            'indexare_2025' => ['nullable', 'numeric', 'min:0'],
            'indexare_2026' => ['nullable', 'numeric', 'min:0'],
            'moneda' => ['nullable', 'string', 'size:3'],
            'locator_id' => ['nullable', 'exists:locatori,id'],
            'configurare_anexa_id' => ['nullable', 'exists:configurari_anexe_imobil,id'],
            'chirias' => ['nullable', 'string', 'max:255'],
            'observatii' => ['nullable', 'string', 'max:5000'],
            'de_lamurit' => ['boolean'],
            'marcat_galben' => ['boolean'],
            'marcat_verde' => ['boolean'],
        ]);

        $validated['retim_direct'] = (bool) ($validated['retim_direct'] ?? false);
        $validated['de_lamurit'] = (bool) ($validated['de_lamurit'] ?? false);
        $validated['marcat_galben'] = (bool) ($validated['marcat_galben'] ?? false);
        $validated['marcat_verde'] = (bool) ($validated['marcat_verde'] ?? false);
        $validated = $this->normalizeMarcaje($validated);
        $validated = $this->normalizeSpatiuByStatus($validated);
        $validated['moneda'] = 'EUR';

        if (blank($validated['etaj'] ?? null)) {
            $validated['etaj'] = 'Parter';
        }

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
            $validated['configurare_anexa_id'] = null;
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
            'indexare_2025',
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

    private function normalizeSpatiuByStatus(array $validated): array
    {
        if (($validated['status'] ?? '') === 'administrativ') {
            $validated['regim_incalzire'] = 'neincalzit';
            $validated['procent_incalzire_override'] = null;
            $validated['locator_id'] = null;
            $validated['configurare_anexa_id'] = null;
            $validated['chirias'] = null;
            $validated['indexare_2025'] = null;
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

    private function spatiiIndexUrl(int $imobilId): string
    {
        return '/spatii?'.http_build_query(['imobil_id' => $imobilId]);
    }

    private function imobileForSelect()
    {
        return Imobil::query()
            ->orderBy('nume')
            ->get(['id', 'nume', 'localitate'])
            ->map(fn (Imobil $imobil) => [
                'id' => $imobil->id,
                'label' => "{$imobil->nume} ({$imobil->localitate})",
            ]);
    }

    private function campuriSpatiuVizibileForSelect()
    {
        return Imobil::query()
            ->orderBy('nume')
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
            ->orderByDesc('implicit')
            ->orderBy('denumire')
            ->get(['id', 'imobil_id', 'denumire', 'implicit'])
            ->groupBy('imobil_id')
            ->map(fn ($configurari) => $configurari->map(fn (ConfigurareAnexaImobil $configurare) => [
                'id' => $configurare->id,
                'implicit' => $configurare->implicit,
                'denumire' => $configurare->implicit ? "{$configurare->denumire} (implicită)" : $configurare->denumire,
            ])->values());
    }
}
