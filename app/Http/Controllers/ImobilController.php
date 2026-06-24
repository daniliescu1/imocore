<?php

namespace App\Http\Controllers;

use App\Models\ConfigurareAnexaImobil;
use App\Models\Imobil;
use App\Support\StrictSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ImobilController extends Controller
{
    public function index(Request $request): Response
    {
        $localitate = $request->string('localitate')->toString();
        $search = $request->string('search')->toString();

        $query = Imobil::query()
            ->withCount([
                'spatii as spatii_total_live',
                'spatii as spatii_libere_live' => fn ($query) => $query->where('status', 'liber'),
                'spatii as spatii_inchiriate_live' => fn ($query) => $query->where('status', 'inchiriat'),
                'spatii as spatii_comune_live' => fn ($query) => $query->where('status', 'comun'),
            ])
            ->orderBy('ordine')
            ->orderBy('id');

        if ($localitate !== '') {
            $query->where('localitate', $localitate);
        }

        if ($search !== '') {
            StrictSearch::whereTextFieldsMatch($query, $search, [
                'nume',
                'strada',
                'localitate',
                'numar_cf',
                'numere_cf',
            ]);
        }

        $imobile = $query->get()->map(fn (Imobil $imobil) => [
            'id' => $imobil->id,
            'nume' => $imobil->nume,
            'adresa' => trim($imobil->strada.' '.$imobil->numar.', '.$imobil->localitate),
            'numere_cf' => $this->formatNumereCf($imobil),
            'spatii_total' => $imobil->spatii_total_live,
            'spatii_libere' => $imobil->spatii_libere_live,
            'spatii_inchiriate' => $imobil->spatii_inchiriate_live,
            'spatii_comune' => $imobil->spatii_comune_live,
        ]);

        return Inertia::render('Imobile/Index', [
            'imobile' => $imobile,
            'localitati' => Imobil::query()->select('localitate')->distinct()->orderBy('localitate')->pluck('localitate'),
            'filters' => [
                'localitate' => $localitate,
                'search' => $search,
            ],
            'canDeleteImobile' => $this->isOwner($request),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Imobile/Create', [
            'campuriSpatiuConfigurabile' => Imobil::campuriSpatiuConfigurabilePentruForm(),
        ]);
    }

    public function edit(Request $request, Imobil $imobil): Response
    {
        $imobil->load('configurariAnexe.linii');

        return Inertia::render('Imobile/Create', [
            'imobil' => [
                'id' => $imobil->id,
                'nume' => $imobil->nume,
                'strada' => $imobil->strada,
                'numar' => $imobil->numar,
                'localitate' => $imobil->localitate,
                'judet' => $imobil->judet,
                'cod_postal' => $imobil->cod_postal,
                'numere_cf' => $this->numereCfForForm($imobil),
                'observatii' => $imobil->observatii,
                'spatii_total' => $imobil->spatii_total,
                'spatii_libere' => $imobil->spatii_libere,
                'spatii_inchiriate' => $imobil->spatii_inchiriate,
                'spatii_comune' => $imobil->spatii_comune,
                'campuri_spatiu_vizibile' => $imobil->campuriSpatiuVizibilePentruForm(),
                'configurari_anexe' => $imobil->configurariAnexe->map(fn (ConfigurareAnexaImobil $configurare) => [
                    'id' => $configurare->id,
                    'denumire' => $configurare->denumire,
                    'implicit' => $configurare->implicit,
                    'activ' => $configurare->activ,
                    'observatii' => $configurare->observatii,
                    'linii' => $configurare->linii->map(fn ($linie) => [
                        'id' => $linie->id,
                        'denumire' => $linie->denumire,
                        'nr_crt' => $linie->nr_crt,
                        'index_vechi' => $linie->index_vechi,
                        'index_nou' => $linie->index_nou,
                        'facturat' => $linie->facturat,
                        'um' => $linie->um,
                        'pret_unitar' => $linie->pret_unitar,
                        'valoare' => $linie->valoare,
                        'tva_21' => $this->tvaForForm($linie->tva_21),
                        'tip_calcul' => $linie->tip_calcul,
                        'apare_cu_zero' => $linie->apare_cu_zero,
                        'activ' => $linie->activ,
                        'observatii' => $linie->observatii,
                    ])->values(),
                ])->values(),
            ],
            'campuriSpatiuConfigurabile' => Imobil::campuriSpatiuConfigurabilePentruForm(),
            'canDeleteImobile' => $this->isOwner($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $validated['numere_cf'] = $this->syncCfPhotos($request, $validated['numere_cf']);
        $validated['ordine'] = $this->nextOrdineForImobile();

        $imobil = Imobil::create($validated);
        $this->syncConfigurariAnexe($request, $imobil);

        return redirect('/imobile')->with('success', 'Imobilul a fost adăugat.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ordine' => ['required', 'array', 'min:1'],
            'ordine.*' => ['integer', 'distinct', 'exists:imobile,id'],
        ]);

        $ids = collect($validated['ordine'])->map(fn ($id) => (int) $id)->values();

        abort_unless(
            Imobil::query()->whereIn('id', $ids)->count() === $ids->count(),
            422,
            'Ordinea conține imobile invalide.'
        );

        foreach ($ids as $index => $imobilId) {
            Imobil::query()
                ->whereKey($imobilId)
                ->update(['ordine' => $index + 1]);
        }

        return back();
    }

    public function update(Request $request, Imobil $imobil): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $validated['numere_cf'] = $this->syncCfPhotos($request, $validated['numere_cf'], $imobil);
        unset($validated['configurari_anexe']);

        $imobil->update($validated);
        $this->syncConfigurariAnexe($request, $imobil);

        return redirect('/imobile')->with('success', 'Imobilul a fost actualizat.');
    }

    public function updateConfigurariAnexe(Request $request, Imobil $imobil): RedirectResponse
    {
        $request->validate($this->configurariAnexeValidationRules());

        $this->syncConfigurariAnexe($request, $imobil);

        return redirect()
            ->route('imobile.edit', $imobil)
            ->with('success', 'Configurările anexei au fost salvate.');
    }

    public function destroy(Request $request, Imobil $imobil): RedirectResponse
    {
        abort_unless($this->isOwner($request), 403);

        $imobil->delete();

        return redirect('/imobile')->with('success', 'Imobilul a fost șters.');
    }

    public function viewCfFile(Imobil $imobil, int $index)
    {
        $cf = $this->numereCfForForm($imobil)[$index] ?? null;
        abort_unless($cf && $cf['poza_path'] && Storage::disk('public')->exists($cf['poza_path']), 404);

        $path = Storage::disk('public')->path($cf['poza_path']);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$this->safeFileName($cf).'"',
        ]);
    }

    public function downloadCfFile(Imobil $imobil, int $index)
    {
        $cf = $this->numereCfForForm($imobil)[$index] ?? null;
        abort_unless($cf && $cf['poza_path'] && Storage::disk('public')->exists($cf['poza_path']), 404);

        return Storage::disk('public')->download($cf['poza_path'], $this->safeFileName($cf));
    }

    private function isOwner(Request $request): bool
    {
        return true;
    }

    private function safeFileName(array $cf): string
    {
        return str_replace('"', '', $cf['poza_nume'] ?: basename($cf['poza_path']));
    }

    private function tvaForForm(null|string|int|float $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return rtrim(rtrim((string) $value, '0'), '.');
    }

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'nume' => ['required', 'string', 'max:255'],
            'strada' => ['required', 'string', 'max:255'],
            'numar' => ['required', 'string', 'max:50'],
            'localitate' => ['required', 'string', 'max:255'],
            'judet' => ['nullable', 'string', 'max:255'],
            'cod_postal' => ['nullable', 'string', 'max:20'],
            'numere_cf' => ['nullable', 'array'],
            'numere_cf.*.numar' => ['nullable', 'string', 'max:255'],
            'numere_cf.*.observatii' => ['nullable', 'string', 'max:1000'],
            'numere_cf.*.poza_path' => ['nullable', 'string', 'max:1000'],
            'numere_cf.*.poza_nume' => ['nullable', 'string', 'max:1000'],
            'numere_cf.*.sterge_fisier' => ['nullable', 'boolean'],
            'numere_cf.*.poza' => ['nullable', 'file', 'max:10240'],
            'campuri_spatiu_vizibile' => ['nullable', 'array'],
            'campuri_spatiu_vizibile.*' => ['string', 'in:'.implode(',', array_keys(Imobil::CAMPURI_SPATIU_CONFIGURABILE))],
            'observatii' => ['nullable', 'string', 'max:5000'],
            'configurari_anexe' => ['nullable', 'array'],
            'configurari_anexe.*.id' => ['nullable', 'integer'],
            'configurari_anexe.*.denumire' => ['nullable', 'string', 'max:255'],
            'configurari_anexe.*.implicit' => ['nullable', 'boolean'],
            'configurari_anexe.*.activ' => ['nullable', 'boolean'],
            'configurari_anexe.*.observatii' => ['nullable', 'string', 'max:1000'],
            'configurari_anexe.*.linii' => ['nullable', 'array'],
            'configurari_anexe.*.linii.*.denumire' => ['nullable', 'string', 'max:255'],
            'configurari_anexe.*.linii.*.nr_crt' => ['nullable', 'integer', 'min:0'],
            'configurari_anexe.*.linii.*.index_vechi' => ['nullable', 'string', 'max:255'],
            'configurari_anexe.*.linii.*.index_nou' => ['nullable', 'string', 'max:255'],
            'configurari_anexe.*.linii.*.facturat' => ['nullable', 'numeric'],
            'configurari_anexe.*.linii.*.um' => ['nullable', 'string', 'max:50'],
            'configurari_anexe.*.linii.*.pret_unitar' => ['nullable', 'numeric'],
            'configurari_anexe.*.linii.*.valoare' => ['nullable', 'numeric'],
            'configurari_anexe.*.linii.*.tva_21' => ['nullable', 'numeric'],
            'configurari_anexe.*.linii.*.tip_calcul' => ['nullable', 'string', 'max:255'],
            'configurari_anexe.*.linii.*.apare_cu_zero' => ['nullable', 'boolean'],
            'configurari_anexe.*.linii.*.activ' => ['nullable', 'boolean'],
            'configurari_anexe.*.linii.*.observatii' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['numere_cf'] = collect($validated['numere_cf'] ?? [])
            ->map(fn (array $cf): array => [
                'numar' => trim((string) ($cf['numar'] ?? '')),
                'observatii' => trim((string) ($cf['observatii'] ?? '')),
                'poza_path' => $cf['poza_path'] ?? null,
                'poza_nume' => $cf['poza_nume'] ?? (isset($cf['poza_path']) && $cf['poza_path'] ? basename($cf['poza_path']) : null),
                'sterge_fisier' => (bool) ($cf['sterge_fisier'] ?? false),
            ])
            ->filter(fn (array $cf): bool => $cf['numar'] !== '')
            ->values()
            ->all();

        $validated['campuri_spatiu_vizibile'] = Imobil::normalizeazaCampuriSpatiuVizibile($validated['campuri_spatiu_vizibile'] ?? null);

        unset($validated['configurari_anexe']);

        return $validated;
    }

    private function configurariAnexeValidationRules(): array
    {
        return [
            'configurari_anexe' => ['nullable', 'array'],
            'configurari_anexe.*.id' => ['nullable', 'integer'],
            'configurari_anexe.*.denumire' => ['nullable', 'string', 'max:255'],
            'configurari_anexe.*.implicit' => ['nullable', 'boolean'],
            'configurari_anexe.*.activ' => ['nullable', 'boolean'],
            'configurari_anexe.*.observatii' => ['nullable', 'string', 'max:1000'],
            'configurari_anexe.*.linii' => ['nullable', 'array'],
            'configurari_anexe.*.linii.*.denumire' => ['nullable', 'string', 'max:255'],
            'configurari_anexe.*.linii.*.nr_crt' => ['nullable', 'integer', 'min:0'],
            'configurari_anexe.*.linii.*.index_vechi' => ['nullable', 'string', 'max:255'],
            'configurari_anexe.*.linii.*.index_nou' => ['nullable', 'string', 'max:255'],
            'configurari_anexe.*.linii.*.facturat' => ['nullable', 'numeric'],
            'configurari_anexe.*.linii.*.um' => ['nullable', 'string', 'max:50'],
            'configurari_anexe.*.linii.*.pret_unitar' => ['nullable', 'numeric'],
            'configurari_anexe.*.linii.*.valoare' => ['nullable', 'numeric'],
            'configurari_anexe.*.linii.*.tva_21' => ['nullable', 'numeric'],
            'configurari_anexe.*.linii.*.tip_calcul' => ['nullable', 'string', 'max:255'],
            'configurari_anexe.*.linii.*.apare_cu_zero' => ['nullable', 'boolean'],
            'configurari_anexe.*.linii.*.activ' => ['nullable', 'boolean'],
            'configurari_anexe.*.linii.*.observatii' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function syncConfigurariAnexe(Request $request, Imobil $imobil): void
    {
        if (! $request->has('configurari_anexe')) {
            return;
        }

        $configurari = collect($request->input('configurari_anexe', []))
            ->filter(fn (array $configurare): bool => $this->configurareAnexaAreDate($configurare))
            ->values();

        $keepIds = [];

        foreach ($configurari as $configurareData) {
            $configurareValues = [
                'denumire' => $this->denumireConfigurareAnexa($configurareData),
                'implicit' => (bool) ($configurareData['implicit'] ?? false),
                'activ' => (bool) ($configurareData['activ'] ?? true),
                'observatii' => $configurareData['observatii'] ?? null,
            ];

            $configurare = isset($configurareData['id'])
                ? $imobil->configurariAnexe()->whereKey($configurareData['id'])->first()
                : null;

            if ($configurare) {
                $configurare->update($configurareValues);
            } else {
                $configurare = $imobil->configurariAnexe()->create($configurareValues);
            }

            $keepIds[] = $configurare->id;
            $lineKeepIds = [];

            foreach (($configurareData['linii'] ?? []) as $linieData) {
                if (trim((string) ($linieData['denumire'] ?? '')) === '') {
                    continue;
                }

                $linieValues = [
                    'denumire' => trim($linieData['denumire']),
                    'nr_crt' => $linieData['nr_crt'] ?? null,
                    'index_vechi' => $linieData['index_vechi'] ?? null,
                    'index_nou' => $linieData['index_nou'] ?? null,
                    'facturat' => $linieData['facturat'] ?? null,
                    'um' => $linieData['um'] ?? null,
                    'pret_unitar' => $linieData['pret_unitar'] ?? null,
                    'valoare' => $linieData['valoare'] ?? null,
                    'tva_21' => $linieData['tva_21'] ?? null,
                    'tip_calcul' => $linieData['tip_calcul'] ?? 'manual',
                    'apare_cu_zero' => (bool) ($linieData['apare_cu_zero'] ?? true),
                    'activ' => (bool) ($linieData['activ'] ?? true),
                    'observatii' => $linieData['observatii'] ?? null,
                ];

                $linie = isset($linieData['id'])
                    ? $configurare->linii()->whereKey($linieData['id'])->first()
                    : null;

                if ($linie) {
                    $linie->update($linieValues);
                } else {
                    $linie = $configurare->linii()->create($linieValues);
                }

                $lineKeepIds[] = $linie->id;
            }

            $configurare->linii()->whereNotIn('id', $lineKeepIds)->delete();
        }

        $imobil->configurariAnexe()->whereNotIn('id', $keepIds)->delete();

        if ($keepIds !== []) {
            $implicitId = $imobil->configurariAnexe()->where('implicit', true)->value('id') ?: $keepIds[0];
            $imobil->configurariAnexe()->where('id', '!=', $implicitId)->update(['implicit' => false]);
            $imobil->configurariAnexe()->where('id', $implicitId)->update(['implicit' => true]);
        }
    }

    private function configurareAnexaAreDate(array $configurare): bool
    {
        if (trim((string) ($configurare['denumire'] ?? '')) !== '') {
            return true;
        }

        foreach (($configurare['linii'] ?? []) as $linie) {
            if (trim((string) ($linie['denumire'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function denumireConfigurareAnexa(array $configurare): string
    {
        $denumire = trim((string) ($configurare['denumire'] ?? ''));

        return $denumire !== '' ? $denumire : 'Anexă imobil';
    }

    private function syncCfPhotos(Request $request, array $numereCf, ?Imobil $imobil = null): array
    {
        $existing = collect($imobil?->numere_cf ?: []);

        return collect($numereCf)
            ->map(function (array $cf, int $index) use ($request, $existing): array {
                $path = $cf['poza_path'] ?? $existing->get($index)['poza_path'] ?? null;
                $name = $cf['poza_nume'] ?? $existing->get($index)['poza_nume'] ?? null;
                $file = $request->file("numere_cf.{$index}.poza");

                if ($cf['sterge_fisier'] ?? false) {
                    if ($path) {
                        Storage::disk('public')->delete($path);
                    }

                    $path = null;
                    $name = null;
                }

                if ($file) {
                    if ($path) {
                        Storage::disk('public')->delete($path);
                    }

                    $path = $file->store('imobile/cf', 'public');
                    $name = $file->getClientOriginalName();
                }

                return [
                    'numar' => $cf['numar'],
                    'observatii' => $cf['observatii'],
                    'poza_path' => $path,
                    'poza_nume' => $name,
                ];
            })
            ->values()
            ->all();
    }

    private function numereCfForForm(Imobil $imobil): array
    {
        $numereCf = $imobil->numere_cf ?: [];

        if ($numereCf === [] && $imobil->numar_cf) {
            $numereCf[] = [
                'numar' => $imobil->numar_cf,
                'observatii' => '',
                'poza_path' => null,
                'poza_nume' => null,
            ];
        }

        return collect($numereCf)
            ->map(fn (array $cf, int $index): array => [
                'numar' => $cf['numar'] ?? '',
                'observatii' => $cf['observatii'] ?? '',
                'poza_path' => $cf['poza_path'] ?? null,
                'poza_nume' => $cf['poza_nume'] ?? null,
                'sterge_fisier' => false,
                'poza_url' => isset($cf['poza_path']) && $cf['poza_path'] ? Storage::url($cf['poza_path']) : null,
                'preview_url' => isset($cf['poza_path']) && $cf['poza_path'] ? route('imobile.cf.view', ['imobil' => $imobil, 'index' => $index]) : null,
                'download_url' => isset($cf['poza_path']) && $cf['poza_path'] ? route('imobile.cf.download', ['imobil' => $imobil, 'index' => $index]) : null,
            ])
            ->values()
            ->all();
    }

    private function formatNumereCf(Imobil $imobil): string
    {
        $numereCf = collect($this->numereCfForForm($imobil))
            ->pluck('numar')
            ->filter()
            ->implode(', ');

        return $numereCf !== '' ? $numereCf : '—';
    }

    private function nextOrdineForImobile(): int
    {
        $maxOrdine = Imobil::query()->max('ordine');

        return ((int) $maxOrdine) + 1;
    }
}