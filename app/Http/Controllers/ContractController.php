<?php

namespace App\Http\Controllers;

use App\Models\ConfigurareAnexaImobil;
use App\Models\Contract;
use App\Models\Imobil;
use App\Models\Locator;
use App\Models\Spatiu;
use App\Support\ContractChiriasData;
use App\Support\ContractCompleteness;
use App\Support\ContractIncompleteStorage;
use App\Support\InternalReturnUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        if (! in_array($status, ['', 'activ', 'incomplet'], true)) {
            $status = '';
        }

        $configurareAnexaId = $request->integer('configurare_anexa_id') ?: null;

        if ($configurareAnexaId && ! ConfigurareAnexaImobil::query()->whereKey($configurareAnexaId)->exists()) {
            $configurareAnexaId = null;
        }

        $query = Contract::query()
            ->with('spatiu.imobil')
            ->latest();

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($configurareAnexaId) {
            $query->whereHas('spatiu', fn ($spatiuQuery) => $spatiuQuery->where('configurare_anexa_id', $configurareAnexaId));
        }

        $contracte = $query
            ->get()
            ->map(fn (Contract $contract): array => [
                'id' => $contract->id,
                'numar_contract' => $contract->numar_contract ?: '—',
                'imobil' => $contract->spatiu?->imobil?->nume ?: '—',
                'spatiu' => $contract->spatiu?->identificator ?: '—',
                'chirias' => $contract->chirias ?: '—',
                'chirie' => $contract->chirie,
                'moneda' => $contract->moneda,
                'perioada' => optional($contract->data_start)->format('d.m.Y').' - '.(optional($contract->data_end)->format('d.m.Y') ?: 'nedeterminat'),
                'status' => $this->statusLabel($contract->status),
            ]);

        return Inertia::render('Contracte/Index', [
            'contracte' => $contracte,
            'configurariAnexe' => ConfigurareAnexaImobil::query()
                ->with('imobil:id,nume')
                ->orderBy('denumire')
                ->get(['id', 'denumire', 'imobil_id'])
                ->map(fn (ConfigurareAnexaImobil $configurare): array => [
                    'id' => $configurare->id,
                    'label' => trim($configurare->denumire.' · '.($configurare->imobil?->nume ?: '—')),
                ]),
            'filters' => [
                'status' => $status,
                'configurare_anexa_id' => $configurareAnexaId ? (string) $configurareAnexaId : '',
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $returnUrl = InternalReturnUrl::normalize($request->string('return_url')->toString());

        return Inertia::render('Contracte/Form', [
            ...$this->formProps(),
            'initialImobilId' => $request->integer('imobil_id') ?: null,
            'initialSpatiuId' => $request->integer('spatiu_id') ?: null,
            'returnUrl' => $returnUrl,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$validated, $status, $persoaneDeclarate] = $this->prepareContractPayload($request);

        $contract = Contract::query()->create([
            ...$validated,
            'status' => $status,
        ]);

        $this->syncSpatiuAfterSave($contract, $request, $status, $persoaneDeclarate);

        $returnUrl = InternalReturnUrl::normalize($request->input('return_url'));

        if ($status === 'incomplet') {
            return redirect()
                ->route('contracte.edit', [
                    'contract' => $contract,
                    'return_url' => $returnUrl,
                ])
                ->with('warning', 'Contract incomplet — completează câmpurile marcate pentru activare.');
        }

        return redirect($returnUrl ?: '/contracte')->with('success', 'Contract activ salvat.');
    }

    public function edit(Request $request, Contract $contract): Response
    {
        $contract->load('spatiu.locatorEntitate');
        $returnUrl = InternalReturnUrl::normalize($request->string('return_url')->toString());

        return Inertia::render('Contracte/Form', [
            ...$this->formProps(),
            'returnUrl' => $returnUrl,
            'contract' => $this->contractForForm($contract, $returnUrl),
        ]);
    }

    public function update(Request $request, Contract $contract): RedirectResponse
    {
        [$validated, $status, $persoaneDeclarate] = $this->prepareContractPayload($request);

        $contract->update([
            ...$validated,
            'status' => $status,
        ]);

        $this->syncSpatiuAfterSave($contract, $request, $status, $persoaneDeclarate);

        $returnUrl = InternalReturnUrl::normalize($request->input('return_url'));

        if ($status === 'incomplet') {
            return redirect()
                ->route('contracte.edit', [
                    'contract' => $contract,
                    'return_url' => $returnUrl,
                ])
                ->with('warning', 'Contract incomplet — completează câmpurile marcate pentru activare.');
        }

        return redirect($returnUrl ?: '/contracte')->with('success', 'Contract activ salvat.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: string, 2: ?int}
     */
    private function prepareContractPayload(Request $request): array
    {
        $request->merge([
            'data_start' => ContractIncompleteStorage::normalizeDateValue($request->input('data_start')),
            'data_end' => ContractIncompleteStorage::normalizeDateValue($request->input('data_end')),
            'data_crestere_chirie' => ContractIncompleteStorage::normalizeDateValue($request->input('data_crestere_chirie')),
            'chirias_pf' => ContractChiriasData::normalizeCnpInRequestGroup($request->input('chirias_pf', [])),
            'chirias_pj' => ContractChiriasData::normalizeCnpInRequestGroup($request->input('chirias_pj', [])),
        ]);

        $baseValidated = $request->validate([
            'spatiu_id' => ['required', 'exists:spatii,id'],
            'locator_id' => ['nullable', 'exists:locatori,id'],
            'numar_contract' => ['nullable', 'string', 'max:255'],
            'chirias_tip' => ['nullable', 'in:pf,pj'],
            'chirias_pf' => ['nullable', 'array'],
            'chirias_pj' => ['nullable', 'array'],
            'persoane_declarate' => ['nullable', 'integer', 'min:0'],
            'data_start' => ['nullable', 'date'],
            'data_end' => ['nullable', 'date'],
            'chirie' => ['nullable', 'numeric', 'min:0'],
            'crestere_chirie_la' => ['nullable', 'numeric', 'min:0'],
            'data_crestere_chirie' => ['nullable', 'date'],
            'moneda' => ['nullable', 'string', 'size:3'],
            'observatii' => ['nullable', 'string', 'max:5000'],
            'configurare_anexa_id' => ['nullable', 'exists:configurari_anexe_imobil,id'],
        ]);

        $spatiu = Spatiu::query()->findOrFail($baseValidated['spatiu_id']);
        $isComplete = ContractCompleteness::isComplete($request->all());
        $status = $isComplete ? 'activ' : 'incomplet';

        $chirias = $isComplete
            ? ContractChiriasData::validateAndNormalize($request)
            : ContractChiriasData::normalizeBestEffort($request);

        $persoaneDeclarate = isset($baseValidated['persoane_declarate'])
            ? (int) $baseValidated['persoane_declarate']
            : null;

        $payload = [
            'spatiu_id' => (int) $baseValidated['spatiu_id'],
            'numar_contract' => filled($baseValidated['numar_contract'] ?? null) ? $baseValidated['numar_contract'] : '',
            'data_start' => $baseValidated['data_start'] ?? null,
            'data_end' => $baseValidated['data_end'] ?? null,
            'chirie' => $baseValidated['chirie'] ?? 0,
            'crestere_chirie_la' => $baseValidated['crestere_chirie_la'] ?? null,
            'data_crestere_chirie' => $baseValidated['data_crestere_chirie'] ?? null,
            'moneda' => Spatiu::normalizeMoneda($spatiu->etaj, $baseValidated['moneda'] ?? $spatiu->moneda),
            'observatii' => $baseValidated['observatii'] ?? null,
            ...$chirias,
        ];

        $payload['chirias'] = filled($payload['chirias'] ?? null) ? $payload['chirias'] : '';

        $payload = ContractIncompleteStorage::normalizeForStorage($payload, $status);

        return [$payload, $status, $persoaneDeclarate];
    }

    private function syncSpatiuAfterSave(Contract $contract, Request $request, string $status, ?int $persoaneDeclarate): void
    {
        $this->updateSpatiuLocator($contract->spatiu, $request->integer('locator_id') ?: null);

        $spatiuUpdates = [
            'configurare_anexa_id' => $this->resolveConfigurareAnexaIdForSpatiu($request, $contract->spatiu),
        ];

        if ($status === 'activ') {
            $esteAdministrativ = $contract->spatiu->status === 'administrativ';
            $spatiuUpdates['status'] = $esteAdministrativ ? 'administrativ' : 'inchiriat';
            $spatiuUpdates['chirias'] = $contract->chirias;
            $spatiuUpdates['persoane_declarate'] = $esteAdministrativ ? null : $persoaneDeclarate;
        }

        $contract->spatiu->update($spatiuUpdates);
        $contract->spatiu->imobil->recalculeazaSpatii();
    }

    private function resolveConfigurareAnexaIdForSpatiu(Request $request, Spatiu $spatiu): ?int
    {
        if (! $request->has('configurare_anexa_id')) {
            return $spatiu->configurare_anexa_id;
        }

        if (blank($request->input('configurare_anexa_id'))) {
            return $spatiu->configurare_anexa_id;
        }

        return $this->validatedConfigurareAnexaId($request, $spatiu);
    }

    private function validatedConfigurareAnexaId(Request $request, Spatiu $spatiu): ?int
    {
        $configurareAnexaId = $request->input('configurare_anexa_id');

        if (blank($configurareAnexaId)) {
            return null;
        }

        $belongsToImobil = ConfigurareAnexaImobil::query()
            ->whereKey($configurareAnexaId)
            ->where('imobil_id', $spatiu->imobil_id)
            ->exists();

        abort_unless($belongsToImobil, 422, 'Configurarea de anexă nu aparține imobilului spațiului ales.');

        return (int) $configurareAnexaId;
    }

    /**
     * @return array<string, mixed>
     */
    private function contractForForm(Contract $contract, ?string $returnUrl = null): array
    {
        $chiriasForm = ContractChiriasData::forForm(
            $contract->chirias_tip,
            $contract->chirias_date,
            ContractIncompleteStorage::displayChirias($contract->chirias),
        );

        $formInput = ContractIncompleteStorage::normalizeInputForCompleteness([
            'spatiu_id' => $contract->spatiu_id,
            'locator_id' => $contract->spatiu?->locator_id,
            'numar_contract' => ContractIncompleteStorage::displayNumarContract($contract->numar_contract),
            'data_start' => ContractIncompleteStorage::displayDate($contract->data_start),
            'data_end' => optional($contract->data_end)?->format('Y-m-d'),
            'chirie' => $contract->chirie,
            'crestere_chirie_la' => $contract->crestere_chirie_la,
            'data_crestere_chirie' => optional($contract->data_crestere_chirie)?->format('Y-m-d'),
            'chirias_tip' => $contract->chirias_tip ?? 'pj',
            'chirias_pf' => $chiriasForm['chirias_pf'] ?? [],
            'chirias_pj' => $chiriasForm['chirias_pj'] ?? [],
        ]);

        return [
            'id' => $contract->id,
            'imobil_id' => $contract->spatiu?->imobil_id,
            'spatiu_id' => $contract->spatiu_id,
            'locator_id' => $contract->spatiu?->locator_id,
            'numar_contract' => ContractIncompleteStorage::displayNumarContract($contract->numar_contract),
            'persoane_declarate' => $contract->spatiu?->persoane_declarate,
            'data_start' => ContractIncompleteStorage::displayDate($contract->data_start),
            'data_end' => optional($contract->data_end)->format('Y-m-d'),
            'chirie' => $contract->chirie,
            'crestere_chirie_la' => $contract->crestere_chirie_la,
            'data_crestere_chirie' => optional($contract->data_crestere_chirie)->format('Y-m-d'),
            'moneda' => $contract->moneda,
            'status' => $contract->status,
            'observatii' => $contract->observatii,
            'configurare_anexa_id' => $contract->spatiu?->configurare_anexa_id,
            'missing_field_keys' => $contract->status === 'incomplet'
                ? array_values(ContractCompleteness::missingFieldKeys($formInput))
                : [],
            'missing_field_labels' => $contract->status === 'incomplet'
                ? ContractCompleteness::missingFieldLabels($formInput)
                : [],
            ...$chiriasForm,
        ];
    }

    private function updateSpatiuLocator(Spatiu $spatiu, ?int $locatorId): void
    {
        if (! $locatorId) {
            return;
        }

        $locator = Locator::query()->findOrFail($locatorId);

        $spatiu->update([
            'locator_id' => $locator->id,
            'locator' => $locator->nume,
        ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'activ' => 'Activ',
            'incomplet' => 'Incomplet',
            'inactiv' => 'Inactiv',
            'incetat' => 'Încetat',
            default => $status,
        };
    }

    private function formProps(): array
    {
        return [
            'locatori' => Locator::query()
                ->orderBy('nume')
                ->get(['id', 'nume'])
                ->map(fn (Locator $locator): array => [
                    'id' => $locator->id,
                    'nume' => $locator->nume,
                ]),
            'imobile' => Imobil::query()
                ->orderBy('nume')
                ->get(['id', 'nume', 'localitate'])
                ->map(fn (Imobil $imobil): array => [
                    'id' => $imobil->id,
                    'label' => "{$imobil->nume} ({$imobil->localitate})",
                ]),
            'spatii' => Spatiu::query()
                ->with(['imobil', 'configurareAnexa', 'locatorEntitate'])
                ->orderBy('identificator')
                ->get()
                ->map(function (Spatiu $spatiu): array {
                    $suprafata = $spatiu->suprafata_contractuala_mp;
                    $chirieCurenta = $spatiu->indexare_2026 ?: $spatiu->pret_lunar;

                    return [
                        'id' => $spatiu->id,
                        'imobil_id' => $spatiu->imobil_id,
                        'label' => "{$spatiu->identificator} - {$spatiu->imobil?->nume}",
                        'identificator' => $spatiu->identificator,
                        'status' => $spatiu->status,
                        'locator_id' => $spatiu->locator_id,
                        'locator_nume' => $spatiu->locatorEntitate?->nume ?: $spatiu->getAttribute('locator'),
                        'chirias' => $spatiu->chirias,
                        'persoane_declarate' => $spatiu->persoane_declarate,
                        'suprafata_contractuala_mp' => $suprafata,
                        'pret_lunar' => $spatiu->pret_lunar,
                        'indexare_2026' => $spatiu->indexare_2026,
                        'chirie_curenta' => $chirieCurenta,
                        'moneda' => $spatiu->moneda ?: 'EUR',
                        'configurare_anexa' => $spatiu->configurareAnexa?->denumire,
                        'configurare_anexa_id' => $spatiu->configurare_anexa_id,
                    ];
                }),
            'configurariAnexe' => $this->configurariAnexeForSelect(),
        ];
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
                'linii_count' => $configurare->linii()->count(),
                'spatii_count' => Spatiu::query()->where('configurare_anexa_id', $configurare->id)->count(),
            ])->values());
    }
}
