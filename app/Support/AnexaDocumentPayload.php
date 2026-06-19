<?php

namespace App\Support;

use App\Models\Anexa;
use App\Models\AnexaLinie;

class AnexaDocumentPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(Anexa $anexa): array
    {
        $anexa->loadMissing(['linii', 'contract.spatiu.imobil', 'contract.spatiu.locatorEntitate']);
        $contract = $anexa->contract;
        $spatiu = $contract?->spatiu;
        $imobil = $spatiu?->imobil;

        $liniiServiciu = $anexa->linii->filter(
            fn (AnexaLinie $linie): bool => ($linie->tip_linie ?: 'serviciu') !== 'header'
        );
        $subtotal = $liniiServiciu->sum(fn (AnexaLinie $linie): float => (float) $linie->valoare);
        $totalTva = $liniiServiciu->sum(fn (AnexaLinie $linie): float => (float) ($linie->tva_21 ?? 0));

        return [
            'id' => $anexa->id,
            'numar' => '01',
            'luna' => $anexa->luna,
            'luna_utilitati' => self::lunaUtilitati($anexa->luna),
            'total' => $anexa->total,
            'subtotal' => $subtotal,
            'total_tva' => $totalTva,
            'status' => $anexa->status,
            'perioada_citire' => DocumentFormatter::perioadaCitireDefault($anexa->luna),
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
            ])->all(),
        ];
    }

    private static function lunaUtilitati(?string $luna): string
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
}
