<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\CitireContor;
use App\Models\Contor;
use App\Models\Anexa;
use App\Models\Factura;
use App\Models\Imobil;
use App\Models\PvPredare;
use App\Models\Rezervare;
use App\Models\Spatiu;
use App\Models\UtilitateLunara;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SimpleCrudController extends Controller
{
    private array $modules = [
        'rezervari' => [
            'title' => 'Rezervări',
            'model' => Rezervare::class,
            'columns' => ['prospect', 'spatiu', 'garantie', 'termen_semnare', 'status'],
            'fields' => ['spatiu_id', 'prospect', 'garantie', 'moneda', 'data_rezervare', 'termen_semnare', 'garantie_incasata', 'status', 'observatii'],
            'rules' => [
                'spatiu_id' => ['required', 'exists:spatii,id'],
                'prospect' => ['required', 'string', 'max:255'],
                'garantie' => ['nullable', 'numeric', 'min:0'],
                'moneda' => ['required', 'string', 'size:3'],
                'data_rezervare' => ['nullable', 'date'],
                'termen_semnare' => ['nullable', 'date'],
                'garantie_incasata' => ['boolean'],
                'status' => ['required', 'string', 'max:255'],
                'observatii' => ['nullable', 'string', 'max:5000'],
            ],
        ],
        'contracte' => [
            'title' => 'Contracte',
            'model' => Contract::class,
            'columns' => ['numar_contract', 'spatiu', 'chirias', 'chirie', 'perioada', 'status'],
            'fields' => ['spatiu_id', 'numar_contract', 'chirias', 'data_start', 'data_end', 'chirie', 'moneda', 'status', 'observatii'],
            'rules' => [
                'spatiu_id' => ['required', 'exists:spatii,id'],
                'numar_contract' => ['required', 'string', 'max:255'],
                'chirias' => ['required', 'string', 'max:255'],
                'data_start' => ['required', 'date'],
                'data_end' => ['nullable', 'date'],
                'chirie' => ['required', 'numeric', 'min:0'],
                'moneda' => ['required', 'string', 'size:3'],
                'status' => ['required', 'string', 'max:255'],
                'observatii' => ['nullable', 'string', 'max:5000'],
            ],
        ],
        'pv-predare' => [
            'title' => 'PV Predare',
            'model' => PvPredare::class,
            'columns' => ['spatiu', 'tip', 'data_pv', 'status', 'observatii'],
            'fields' => ['spatiu_id', 'tip', 'data_pv', 'status', 'observatii'],
            'rules' => [
                'spatiu_id' => ['required', 'exists:spatii,id'],
                'tip' => ['required', 'string', 'max:255'],
                'data_pv' => ['required', 'date'],
                'status' => ['required', 'string', 'max:255'],
                'observatii' => ['nullable', 'string', 'max:5000'],
            ],
        ],
        'contoare' => [
            'title' => 'Contoare',
            'model' => Contor::class,
            'columns' => ['cod_contor', 'imobil', 'spatiu', 'tip_utilitate', 'nivel', 'activ'],
            'fields' => ['imobil_id', 'spatiu_id', 'tip_utilitate', 'cod_contor', 'nivel', 'activ', 'observatii'],
            'rules' => [
                'imobil_id' => ['required', 'exists:imobile,id'],
                'spatiu_id' => ['nullable', 'exists:spatii,id'],
                'tip_utilitate' => ['required', 'string', 'max:255'],
                'cod_contor' => ['required', 'string', 'max:255'],
                'nivel' => ['required', 'string', 'max:255'],
                'activ' => ['boolean'],
                'observatii' => ['nullable', 'string', 'max:5000'],
            ],
        ],
        'citiri-contoare' => [
            'title' => 'Citiri contoare',
            'model' => CitireContor::class,
            'columns' => ['contor', 'luna', 'index_vechi', 'index_nou', 'consum'],
            'fields' => ['contor_id', 'luna', 'index_vechi', 'index_nou', 'observatii'],
            'rules' => [
                'contor_id' => ['required', 'exists:contoare,id'],
                'luna' => ['required', 'string', 'size:7'],
                'index_vechi' => ['required', 'numeric', 'min:0'],
                'index_nou' => ['required', 'numeric', 'min:0'],
                'observatii' => ['nullable', 'string', 'max:5000'],
            ],
        ],
        'utilitati' => [
            'title' => 'Utilități',
            'model' => UtilitateLunara::class,
            'columns' => ['imobil', 'luna', 'tip_utilitate', 'cantitate', 'cost_total', 'aprobat'],
            'fields' => ['imobil_id', 'luna', 'tip_utilitate', 'cantitate', 'cost_total', 'pret_unitar', 'aprobat', 'observatii'],
            'rules' => [
                'imobil_id' => ['required', 'exists:imobile,id'],
                'luna' => ['required', 'string', 'size:7'],
                'tip_utilitate' => ['required', 'string', 'max:255'],
                'cantitate' => ['nullable', 'numeric', 'min:0'],
                'cost_total' => ['required', 'numeric', 'min:0'],
                'pret_unitar' => ['nullable', 'numeric', 'min:0'],
                'aprobat' => ['boolean'],
                'observatii' => ['nullable', 'string', 'max:5000'],
            ],
        ],
        'anexe' => [
            'title' => 'Generare anexe',
            'model' => Anexa::class,
            'columns' => ['contract', 'luna', 'total', 'status'],
            'fields' => ['contract_id', 'luna', 'total', 'status'],
            'rules' => [
                'contract_id' => ['required', 'exists:contracte,id'],
                'luna' => ['required', 'string', 'size:7'],
                'total' => ['nullable', 'numeric', 'min:0'],
                'status' => ['required', 'string', 'max:255'],
            ],
        ],
        'facturare' => [
            'title' => 'Facturare',
            'model' => Factura::class,
            'columns' => ['anexa', 'numar_factura', 'total', 'status', 'email_chirias'],
            'fields' => ['anexa_id', 'numar_factura', 'total', 'status', 'email_chirias'],
            'rules' => [
                'anexa_id' => ['required', 'exists:anexe,id'],
                'numar_factura' => ['nullable', 'string', 'max:255'],
                'total' => ['nullable', 'numeric', 'min:0'],
                'status' => ['required', 'string', 'max:255'],
                'email_chirias' => ['nullable', 'email', 'max:255'],
            ],
        ],
    ];

    public function index(string $module): Response
    {
        $config = $this->config($module);
        $rows = $config['model']::query()->with('spatiu.imobil')->latest()->get()->map(fn (Model $record) => $this->row($module, $record));

        return Inertia::render('Crud/Index', [
            'moduleKey' => $module,
            'title' => $config['title'],
            'rows' => $rows,
            'columns' => $config['columns'],
        ]);
    }

    public function create(string $module): Response
    {
        return Inertia::render('Crud/Form', $this->formProps($module));
    }

    public function store(Request $request, string $module): RedirectResponse
    {
        $config = $this->config($module);
        $validated = $request->validate($config['rules']);
        $validated = $this->normalizeBooleans($validated);
        $validated = $this->normalizeCalculatedFields($module, $validated);
        $record = $config['model']::create($validated);
        $this->syncSpatiuStatus($module, $record);

        return redirect("/{$module}")->with('success', 'Înregistrarea a fost adăugată.');
    }

    public function edit(string $module, int $id): Response
    {
        $config = $this->config($module);
        $record = $config['model']::query()->findOrFail($id);

        return Inertia::render('Crud/Form', [
            ...$this->formProps($module),
            'record' => $record,
        ]);
    }

    public function update(Request $request, string $module, int $id): RedirectResponse
    {
        $config = $this->config($module);
        $record = $config['model']::query()->findOrFail($id);
        $validated = $this->normalizeBooleans($request->validate($config['rules']));
        $record->update($this->normalizeCalculatedFields($module, $validated));
        $this->syncSpatiuStatus($module, $record);

        return redirect("/{$module}")->with('success', 'Înregistrarea a fost actualizată.');
    }

    private function formProps(string $module): array
    {
        $config = $this->config($module);

        return [
            'moduleKey' => $module,
            'title' => $config['title'],
            'fields' => $config['fields'],
            'imobile' => Imobil::query()->orderBy('nume')->get(['id', 'nume', 'localitate'])->map(fn (Imobil $imobil) => [
                'id' => $imobil->id,
                'label' => "{$imobil->nume} ({$imobil->localitate})",
            ]),
            'spatii' => Spatiu::query()->with('imobil')->orderBy('identificator')->get()->map(fn (Spatiu $spatiu) => [
                'id' => $spatiu->id,
                'label' => "{$spatiu->identificator} - {$spatiu->imobil?->nume}",
            ]),
            'contoare' => Contor::query()->with('imobil')->orderBy('cod_contor')->get()->map(fn (Contor $contor) => [
                'id' => $contor->id,
                'label' => "{$contor->cod_contor} - {$contor->imobil?->nume}",
            ]),
            'contracte' => Contract::query()->with('spatiu.imobil')->orderBy('numar_contract')->get()->map(fn (Contract $contract) => [
                'id' => $contract->id,
                'label' => "{$contract->numar_contract} - {$contract->spatiu?->identificator}",
            ]),
            'anexe' => Anexa::query()->with('contract')->orderByDesc('id')->get()->map(fn (Anexa $anexa) => [
                'id' => $anexa->id,
                'label' => "{$anexa->contract?->numar_contract} - {$anexa->luna}",
            ]),
        ];
    }

    private function config(string $module): array
    {
        abort_unless(isset($this->modules[$module]), 404);
        return $this->modules[$module];
    }

    private function row(string $module, Model $record): array
    {
        $spatiu = $record->spatiu?->identificator.' - '.$record->spatiu?->imobil?->nume;

        return [
            'id' => $record->id,
            'data' => match ($module) {
                'rezervari' => [
                    'prospect' => $record->prospect,
                    'spatiu' => $spatiu,
                    'garantie' => $record->garantie ? "{$record->garantie} {$record->moneda}" : '—',
                    'termen_semnare' => optional($record->termen_semnare)->format('d.m.Y') ?: '—',
                    'status' => $record->status,
                ],
                'contracte' => [
                    'numar_contract' => $record->numar_contract,
                    'spatiu' => $spatiu,
                    'chirias' => $record->chirias,
                    'chirie' => "{$record->chirie} {$record->moneda}",
                    'perioada' => optional($record->data_start)->format('d.m.Y').' - '.(optional($record->data_end)->format('d.m.Y') ?: 'nedeterminat'),
                    'status' => $record->status,
                ],
                'contoare' => [
                    'cod_contor' => $record->cod_contor,
                    'imobil' => $record->imobil?->nume ?: '—',
                    'spatiu' => $record->spatiu?->identificator ?: '—',
                    'tip_utilitate' => $record->tip_utilitate,
                    'nivel' => $record->nivel,
                    'activ' => $record->activ ? 'Da' : 'Nu',
                ],
                'citiri-contoare' => [
                    'contor' => $record->contor?->cod_contor ?: '—',
                    'luna' => $record->luna,
                    'index_vechi' => $record->index_vechi,
                    'index_nou' => $record->index_nou,
                    'consum' => $record->consum,
                ],
                'utilitati' => [
                    'imobil' => $record->imobil?->nume ?: '—',
                    'luna' => $record->luna,
                    'tip_utilitate' => $record->tip_utilitate,
                    'cantitate' => $record->cantitate ?: '—',
                    'cost_total' => $record->cost_total,
                    'aprobat' => $record->aprobat ? 'Da' : 'Nu',
                ],
                'anexe' => [
                    'contract' => $record->contract?->numar_contract ?: '—',
                    'luna' => $record->luna,
                    'total' => $record->total,
                    'status' => $record->status,
                ],
                'facturare' => [
                    'anexa' => $record->anexa?->luna ?: '—',
                    'numar_factura' => $record->numar_factura ?: '—',
                    'total' => $record->total,
                    'status' => $record->status,
                    'email_chirias' => $record->email_chirias ?: '—',
                ],
                default => [
                    'spatiu' => $spatiu,
                    'tip' => $record->tip,
                    'data_pv' => optional($record->data_pv)->format('d.m.Y'),
                    'status' => $record->status,
                    'observatii' => $record->observatii ?: '—',
                ],
            },
        ];
    }

    private function normalizeBooleans(array $data): array
    {
        if (array_key_exists('garantie_incasata', $data)) {
            $data['garantie_incasata'] = (bool) $data['garantie_incasata'];
        }

        foreach (['activ', 'aprobat'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (bool) $data[$field];
            }
        }

        return $data;
    }

    private function normalizeCalculatedFields(string $module, array $data): array
    {
        if ($module === 'citiri-contoare') {
            $data['consum'] = max(0, (float) $data['index_nou'] - (float) $data['index_vechi']);
        }

        return $data;
    }

    private function syncSpatiuStatus(string $module, Model $record): void
    {
        if ($module === 'contracte' && $record->status === 'activ') {
            $record->spatiu->update(['status' => 'inchiriat', 'chirias' => $record->chirias]);
            $record->spatiu->imobil->recalculeazaSpatii();
        }

        if ($module === 'rezervari' && $record->status === 'activa' && $record->garantie_incasata) {
            $record->spatiu->update(['status' => 'rezervat']);
            $record->spatiu->imobil->recalculeazaSpatii();
        }
    }
}
