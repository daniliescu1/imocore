<?php

namespace Database\Seeders;

use App\Models\ConfigurareAnexaImobil;
use App\Models\ConfigurareAnexaLinie;
use App\Models\Imobil;
use App\Models\Locator;
use App\Models\ServiciuStandardAnexa;
use App\Models\Spatiu;
use Illuminate\Database\Seeder;

class LocalRestoreSeeder extends Seeder
{
    public function run(): void
    {
        ServiciuStandardAnexa::importFromExistingLines();

        $this->seedServiciiStandard();

        $dumbravita = Imobil::query()->where('nume', 'Dumbrăvița Office Conac 60')->firstOrFail();
        $office700 = Imobil::query()->where('nume', '700 Office Gheorghe Lazăr 9')->firstOrFail();

        $locatorAlpha = Locator::query()->firstOrCreate(['nume' => 'Alpha Business SRL']);
        $locatorBeta = Locator::query()->firstOrCreate(['nume' => 'Beta Consulting SRL']);

        $anexaDumbravita = $this->createAnexaConfig($dumbravita, 'Anexă utilități Dumbrăvița');
        $anexa700 = $this->createAnexaConfig($office700, 'Anexă utilități 700 Office');

        $this->seedSpatiiDumbravita($dumbravita, $anexaDumbravita, $locatorAlpha, $locatorBeta);
        $this->seedSpatii700Office($office700, $anexa700, $locatorAlpha);

        $dumbravita->recalculeazaSpatii();
        $office700->recalculeazaSpatii();
    }

    private function seedServiciiStandard(): void
    {
        $servicii = [
            ['tip' => ServiciuStandardAnexa::TIP_DENUMIRE, 'valoare' => 'Energie electrica', 'label' => 'Energie electrica'],
            ['tip' => ServiciuStandardAnexa::TIP_DENUMIRE, 'valoare' => 'Consum Apa', 'label' => 'Consum Apa'],
            ['tip' => ServiciuStandardAnexa::TIP_DENUMIRE, 'valoare' => 'Apa pluviala / meteorica', 'label' => 'Apa pluviala / meteorica'],
            ['tip' => ServiciuStandardAnexa::TIP_DENUMIRE, 'valoare' => 'Curatenie', 'label' => 'Curatenie'],
            ['tip' => ServiciuStandardAnexa::TIP_DENUMIRE, 'valoare' => 'Gunoi menajer', 'label' => 'Gunoi menajer'],
            ['tip' => ServiciuStandardAnexa::TIP_DENUMIRE, 'valoare' => 'Cheltuieli comune', 'label' => 'Cheltuieli comune'],
            ['tip' => ServiciuStandardAnexa::TIP_DENUMIRE, 'valoare' => 'Incaldire', 'label' => 'Incaldire'],
            ['tip' => ServiciuStandardAnexa::TIP_UM, 'valoare' => 'KW', 'label' => 'KW'],
            ['tip' => ServiciuStandardAnexa::TIP_UM, 'valoare' => 'MC', 'label' => 'MC'],
            ['tip' => ServiciuStandardAnexa::TIP_UM, 'valoare' => 'MP', 'label' => 'MP'],
            ['tip' => ServiciuStandardAnexa::TIP_UM, 'valoare' => 'PERS', 'label' => 'PERS'],
            ['tip' => ServiciuStandardAnexa::TIP_TVA, 'valoare' => '21', 'label' => '21%'],
            ['tip' => ServiciuStandardAnexa::TIP_TVA, 'valoare' => '11', 'label' => '11%'],
        ];

        foreach ($servicii as $serviciu) {
            ServiciuStandardAnexa::query()->updateOrCreate(
                ['tip' => $serviciu['tip'], 'valoare' => $serviciu['valoare']],
                ['label' => $serviciu['label'], 'activ' => true]
            );
        }
    }

    private function createAnexaConfig(Imobil $imobil, string $denumire): ConfigurareAnexaImobil
    {
        $configurare = ConfigurareAnexaImobil::query()->firstOrCreate(
            ['imobil_id' => $imobil->id, 'denumire' => $denumire],
            ['implicit' => true, 'activ' => true]
        );

        if ($configurare->linii()->exists()) {
            return $configurare;
        }

        $linii = [
            ['ordine' => 1, 'tip_linie' => 'serviciu', 'nr_crt' => 1, 'denumire' => 'Energie electrica', 'um' => 'KW', 'pret_unitar' => '1.5280', 'tva_21' => '21', 'tip_calcul' => 'contor'],
            ['ordine' => 2, 'tip_linie' => 'serviciu', 'nr_crt' => 2, 'denumire' => 'Consum Apa', 'um' => 'MC', 'pret_unitar' => '9.7400', 'tva_21' => '11', 'tip_calcul' => 'contor'],
            ['ordine' => 3, 'tip_linie' => 'serviciu', 'nr_crt' => 3, 'denumire' => 'Incaldire', 'um' => 'MP', 'pret_unitar' => '4.8500', 'tva_21' => '21', 'tip_calcul' => 'pe_mp'],
            ['ordine' => 4, 'tip_linie' => 'serviciu', 'nr_crt' => 4, 'denumire' => 'Cheltuieli comune', 'um' => 'MP', 'pret_unitar' => '3.1200', 'tva_21' => '21', 'tip_calcul' => 'pe_mp'],
            ['ordine' => 5, 'tip_linie' => 'header', 'denumire' => ''],
            ['ordine' => 6, 'tip_linie' => 'serviciu', 'nr_crt' => 1, 'denumire' => 'Curatenie', 'um' => 'MP', 'pret_unitar' => '2.9700', 'tva_21' => '11', 'tip_calcul' => 'pe_mp'],
            ['ordine' => 7, 'tip_linie' => 'serviciu', 'nr_crt' => 2, 'denumire' => 'Gunoi menajer', 'um' => 'PERS', 'pret_unitar' => '15.0000', 'tva_21' => '11', 'tip_calcul' => 'persoane'],
            ['ordine' => 8, 'tip_linie' => 'serviciu', 'nr_crt' => 3, 'denumire' => 'Apa pluviala / meteorica', 'um' => 'MC', 'pret_unitar' => '9.7400', 'coeficient' => '0.09', 'tva_21' => '11', 'tip_calcul' => 'mp_coeficient'],
        ];

        foreach ($linii as $linie) {
            $configurare->linii()->create([
                ...$linie,
                'apare_cu_zero' => true,
                'activ' => true,
            ]);
        }

        return $configurare;
    }

    private function seedSpatiiDumbravita(Imobil $imobil, ConfigurareAnexaImobil $anexa, Locator $locatorAlpha, Locator $locatorBeta): void
    {
        $spatii = [
            ['identificator' => 'HQD 12', 'suprafata_contractuala_mp' => '120.00', 'status' => 'liber', 'pret_lunar' => '1200.00', 'regim_incalzire' => 'integral'],
            ['identificator' => 'HQD 34', 'suprafata_contractuala_mp' => '85.00', 'status' => 'liber', 'pret_lunar' => '850.00', 'regim_incalzire' => 'integral'],
            ['identificator' => 'HQD 56', 'suprafata_contractuala_mp' => '95.00', 'status' => 'inchiriat', 'pret_lunar' => '1407.50', 'regim_incalzire' => 'partial', 'locator_id' => $locatorAlpha->id, 'chirias' => 'Client Alpha'],
            ['identificator' => 'HQD 78', 'suprafata_contractuala_mp' => '110.00', 'status' => 'inchiriat', 'pret_lunar' => '1540.00', 'regim_incalzire' => 'integral', 'locator_id' => $locatorBeta->id, 'chirias' => 'Client Beta'],
            ['identificator' => 'HQD 89', 'suprafata_contractuala_mp' => '95.00', 'status' => 'rezervat', 'pret_lunar' => '1330.00', 'regim_incalzire' => 'integral'],
            ['identificator' => 'HQD C1', 'suprafata_contractuala_mp' => '45.00', 'status' => 'comun', 'regim_incalzire' => 'neincalzit'],
            ['identificator' => 'HQD C2', 'suprafata_contractuala_mp' => '38.00', 'status' => 'comun', 'regim_incalzire' => 'neincalzit'],
            ['identificator' => 'HQD ADM', 'suprafata_contractuala_mp' => '25.00', 'status' => 'administrativ', 'regim_incalzire' => 'neincalzit'],
        ];

        $this->insertSpatii($imobil, $anexa, $spatii);
    }

    private function seedSpatii700Office(Imobil $imobil, ConfigurareAnexaImobil $anexa, Locator $locatorAlpha): void
    {
        $spatii = [
            ['identificator' => 'TMI 3', 'suprafata_contractuala_mp' => '140.00', 'status' => 'liber', 'pret_lunar' => '1680.00', 'regim_incalzire' => 'integral'],
            ['identificator' => 'TMI 5', 'suprafata_contractuala_mp' => '160.00', 'status' => 'liber', 'pret_lunar' => '1920.00', 'regim_incalzire' => 'integral'],
            ['identificator' => 'TMI 7', 'suprafata_contractuala_mp' => '175.00', 'status' => 'inchiriat', 'pret_lunar' => '2100.00', 'regim_incalzire' => 'integral', 'locator_id' => $locatorAlpha->id, 'chirias' => 'Client Alpha'],
            ['identificator' => 'TMI 9', 'suprafata_contractuala_mp' => '130.00', 'status' => 'inchiriat', 'pret_lunar' => '1560.00', 'regim_incalzire' => 'partial', 'procent_incalzire_override' => '60'],
            ['identificator' => 'TMI 11', 'suprafata_contractuala_mp' => '155.00', 'status' => 'rezervat', 'pret_lunar' => '1860.00', 'regim_incalzire' => 'integral'],
            ['identificator' => 'TMI C1', 'suprafata_contractuala_mp' => '55.00', 'status' => 'comun', 'regim_incalzire' => 'neincalzit'],
            ['identificator' => 'TMI C2', 'suprafata_contractuala_mp' => '48.00', 'status' => 'comun', 'regim_incalzire' => 'neincalzit'],
        ];

        $this->insertSpatii($imobil, $anexa, $spatii);
    }

    private function insertSpatii(Imobil $imobil, ConfigurareAnexaImobil $anexa, array $spatii): void
    {
        foreach ($spatii as $index => $spatiu) {
            Spatiu::query()->firstOrCreate(
                [
                    'imobil_id' => $imobil->id,
                    'identificator' => $spatiu['identificator'],
                ],
                [
                    ...$spatiu,
                    'ordine' => $index + 1,
                    'moneda' => 'EUR',
                    'configurare_anexa_id' => $anexa->id,
                    'activ' => true,
                ]
            );
        }
    }
}
