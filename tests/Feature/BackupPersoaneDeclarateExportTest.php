<?php

namespace Tests\Feature;

use App\Models\Imobil;
use App\Models\Spatiu;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BackupPersoaneDeclarateExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_persoane_declarate_csv_export_contains_expected_columns(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada 1',
            'numar' => '1',
            'localitate' => 'Timișoara',
            'ordine' => 1,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HQF2 parter',
            'etaj' => 'Parter',
            'suprafata_contractuala_mp' => 170,
            'status' => 'inchiriat',
            'persoane_declarate' => 1,
            'chirias' => 'CYD JOHAN SCARPE SRL',
            'ordine' => 1,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'Liber 101',
            'status' => 'liber',
            'ordine' => 2,
        ]);

        $targetPath = storage_path('app/temp/test-persoane-declarate.csv');
        app(BackupService::class)->exportPersoaneDeclarateCsv($targetPath, '2026-06-24 12:00:00');

        $content = file_get_contents($targetPath);
        $lines = preg_split('/\r\n|\n|\r/', trim((string) $content)) ?: [];

        $this->assertSame('sep=;', $lines[0]);
        $this->assertStringContainsString('Imobil;Identificat;Etaj;', $lines[1]);
        $this->assertStringContainsString('Pers. calc automat', $lines[1]);
        $this->assertStringContainsString('Pers declarate', $lines[1]);
        $this->assertStringContainsString('Locatar', $lines[1]);
        $this->assertStringContainsString('700 Office', $lines[2]);
        $this->assertStringContainsString('HQF2 parter', $lines[2]);
        $this->assertStringContainsString('Parter', $lines[2]);
        $this->assertStringContainsString('17', $lines[2]);
        $this->assertStringContainsString('1', $lines[2]);
        $this->assertStringContainsString('CYD JOHAN SCARPE SRL', $lines[2]);
        $this->assertCount(3, $lines);

        @unlink($targetPath);
    }

    public function test_on_demand_persoane_declarate_route_returns_csv_download(): void
    {
        $response = $this->get(route('backup.download.persoane-declarate'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString(
            'imocore-persoane-declarate-',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_backup_page_exposes_persoane_declarate_download_url(): void
    {
        $this->get(route('backup.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backup/Index')
                ->where('persoaneDeclarateDownloadUrl', route('backup.download.persoane-declarate')));
    }
}
