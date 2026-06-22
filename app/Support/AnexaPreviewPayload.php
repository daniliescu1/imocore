<?php

namespace App\Support;

use App\Models\ConfigurareAnexaImobil;
use App\Models\Contract;
use App\Models\Spatiu;
use Carbon\Carbon;

class AnexaPreviewPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function forSpatiu(
        Spatiu $spatiu,
        ConfigurareAnexaImobil $configurare,
        ?Contract $contract = null,
        ?string $lunaUtilitati = null,
    ): array {
        $spatiu->loadMissing(['imobil', 'locatorEntitate']);
        $configurare->loadMissing('linii');
        $imobil = $spatiu->imobil;
        $lunaUtilitati = $lunaUtilitati ?: Carbon::now()->subMonth()->format('Y-m');
        $lunaFacturare = Carbon::createFromFormat('Y-m', $lunaUtilitati)->addMonth()->format('Y-m');

        $linii = [];
        $subtotal = 0.0;
        $totalTva = 0.0;

        foreach ($configurare->linii
            ->where('activ', true)
            ->sortBy(fn ($linie): array => [$linie->ordine, $linie->id]) as $linieConfigurata) {
            if (($linieConfigurata->tip_linie ?: 'serviciu') === 'header') {
                $linii[] = [
                    'tip_linie' => 'header',
                    'nr_crt' => null,
                    'denumire' => '',
                    'tip_calcul' => null,
                    'coeficient' => null,
                    'index_vechi' => null,
                    'index_nou' => null,
                    'cantitate' => null,
                    'um' => null,
                    'pret_unitar' => null,
                    'valoare' => null,
                    'tva_21' => null,
                ];

                continue;
            }

            $linie = GenerareAnexaLinieCalculator::calculate(
                $spatiu,
                $linieConfigurata,
                $lunaUtilitati,
                $lunaFacturare,
            );

            $subtotal += (float) $linie['valoare'];
            $totalTva += (float) ($linie['tva_21'] ?? 0);

            $linii[] = [
                'tip_linie' => 'serviciu',
                'nr_crt' => $linie['nr_crt'],
                'denumire' => DocumentFormatter::denumireServiciuCuLuna($linie['denumire'], $lunaUtilitati),
                'tip_calcul' => $linie['tip_calcul'],
                'coeficient' => $linie['coeficient'],
                'index_vechi' => $linie['index_vechi'],
                'index_nou' => $linie['index_nou'],
                'cantitate' => $linie['cantitate'],
                'um' => $linie['um'],
                'pret_unitar' => $linie['pret_unitar'],
                'valoare' => $linie['valoare'],
                'tva_21' => $linie['tva_21'],
            ];
        }

        return [
            'id' => null,
            'numar' => '01',
            'luna' => $lunaUtilitati,
            'luna_utilitati' => self::lunaUtilitati($lunaUtilitati),
            'total' => round($subtotal + $totalTva, 2),
            'subtotal' => $subtotal,
            'total_tva' => $totalTva,
            'status' => 'preview',
            'perioada_citire' => DocumentFormatter::perioadaCitireDefault($lunaUtilitati),
            'configurare' => [
                'denumire' => $configurare->denumire,
            ],
            'contract' => [
                'numar' => $contract?->numar_contract,
                'chirias' => $contract?->chirias ?: $spatiu->chirias,
                'email_facturare' => self::emailFacturareDinContract($contract),
            ],
            'spatiu' => [
                'identificator' => $spatiu->identificator,
                'locator' => $spatiu->locatorEntitate?->nume ?: $spatiu->getAttribute('locator'),
                'chirias' => $spatiu->chirias ?: $contract?->chirias,
            ],
            'imobil' => [
                'id' => $imobil?->id,
                'nume' => $imobil?->nume,
                'adresa' => trim(implode(' ', array_filter([$imobil?->strada, $imobil?->numar]))),
                'localitate' => $imobil?->localitate,
            ],
            'linii' => $linii,
        ];
    }

    private static function emailFacturareDinContract(?Contract $contract): ?string
    {
        if ($contract === null) {
            return null;
        }

        $date = is_array($contract->chirias_date) ? $contract->chirias_date : [];
        $email = trim((string) ($date['email_2'] ?? ''));

        return $email !== '' ? $email : null;
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
