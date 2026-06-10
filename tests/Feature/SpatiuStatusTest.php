<?php

namespace Tests\Feature;

use App\Models\ConfigurareAnexaImobil;
use App\Models\Imobil;
use App\Models\Locator;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpatiuStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_spatiul_liber_are_regim_incalzire_integral_implicit(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil regim implicit',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $this->post(route('spatii.store'), [
            'imobil_id' => $imobil->id,
            'identificator' => 'S-LIBER',
            'status' => 'liber',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $this->assertSame('integral', Spatiu::query()->where('identificator', 'S-LIBER')->value('regim_incalzire'));
    }

    public function test_spatiul_administrativ_curata_campurile_comerciale(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil administrativ',
            'strada' => 'Strada Test',
            'numar' => '2',
            'localitate' => 'Timișoara',
        ]);

        $locator = Locator::query()->create(['nume' => 'Locator Test']);
        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa test',
            'implicit' => true,
            'activ' => true,
        ]);

        $this->post(route('spatii.store'), [
            'imobil_id' => $imobil->id,
            'identificator' => 'S-ADM',
            'status' => 'administrativ',
            'regim_incalzire' => 'integral',
            'locator_id' => $locator->id,
            'configurare_anexa_id' => $configurare->id,
            'chirias' => 'Nu ar trebui salvat',
            'indexare_2025' => '100',
            'indexare_2026' => '120',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $spatiu = Spatiu::query()->where('identificator', 'S-ADM')->firstOrFail();

        $this->assertSame('administrativ', $spatiu->status);
        $this->assertSame('neincalzit', $spatiu->regim_incalzire);
        $this->assertNull($spatiu->locator_id);
        $this->assertNull($spatiu->configurare_anexa_id);
        $this->assertNull($spatiu->chirias);
        $this->assertNull($spatiu->indexare_2025);
        $this->assertNull($spatiu->indexare_2026);
    }
}
