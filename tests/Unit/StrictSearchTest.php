<?php

namespace Tests\Unit;

use App\Models\Imobil;
use App\Models\Spatiu;
use App\Support\StrictSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrictSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_collapseaza_spatiile_albe(): void
    {
        $this->assertSame('c318', StrictSearch::normalize('C 318'));
        $this->assertSame('c318', StrictSearch::normalize('  C   318  '));
    }

    public function test_contains_este_case_insensitive_si_fara_spatii(): void
    {
        $this->assertTrue(StrictSearch::contains('C 318', 'c318'));
        $this->assertTrue(StrictSearch::contains('E306', '306'));
        $this->assertFalse(StrictSearch::contains('C 309', '318'));
    }

    public function test_where_spatiu_identificator_nu_potriveste_doar_chiriasul(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Test',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Oradea',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'C 309',
            'chirias' => 'Firma 318 SRL',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
        ]);

        $this->assertFalse(
            Spatiu::query()
                ->tap(fn ($query) => StrictSearch::whereSpatiuIdentificator($query, '318'))
                ->exists()
        );

        $this->assertTrue(
            Spatiu::query()
                ->tap(fn ($query) => StrictSearch::whereSpatiuIdentificator($query, 'C 309'))
                ->exists()
        );
    }

    public function test_where_spatiu_list_match_potriveste_chiriasul(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Test',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Oradea',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'E101',
            'chirias' => 'Yusen Logistic',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
        ]);

        $this->assertTrue(
            Spatiu::query()
                ->tap(fn ($query) => StrictSearch::whereSpatiuListMatch($query, 'Yusen'))
                ->exists()
        );
    }
}
