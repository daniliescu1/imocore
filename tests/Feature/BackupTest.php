<?php

namespace Tests\Feature;

use App\Models\Imobil;
use App\Models\Contract;
use App\Models\Spatiu;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BackupExportValidator;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanBackupRoot();
    }

    protected function tearDown(): void
    {
        $this->cleanBackupRoot();

        parent::tearDown();
    }

    public function test_backup_page_lists_created_backups(): void
    {
        $this->seedSpatiu();

        $this->post(route('backup.store'))
            ->assertRedirect(route('backup.index'));

        $this->get(route('backup.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backup/Index')
                ->has('backups', 1)
                ->has('backups.0.spatii_files', 1)
                ->where('backups.0.database_format', 'sqlite')
                ->where('backups.0.trigger', 'manual')
                ->where('retentionDays', 7));
    }

    public function test_manual_backup_creates_database_and_csv_files_per_imobil(): void
    {
        $spatiu = $this->seedSpatiu();

        $this->post(route('backup.store'))->assertRedirect(route('backup.index'));

        $date = now()->format('Y-m-d');
        $directory = storage_path('app/backups/manual');
        $imobilCsv = $directory.'/spatii/'.$spatiu->imobil_id.'-imobil-backup.csv';

        $this->assertDirectoryExists($directory);
        $this->assertDirectoryExists($directory.'/spatii');
        $this->assertFileExists($directory.'/database.sqlite');
        $this->assertFileExists($imobilCsv);
        $this->assertFileExists($directory.'/imobile.csv');
        $this->assertFileExists($directory.'/chiriasi.csv');
        $this->assertFileExists($directory.'/manifest.json');
        $this->assertFileExists($directory.'/'.BackupService::ALL_SPATII_CSV_FILENAME);
        $this->assertFileExists($directory.'/'.BackupService::MARCATE_SPATII_CSV_FILENAME);
        $this->assertFileExists($directory.'/'.BackupService::FARA_ANEXA_SPATII_CSV_FILENAME);
        $this->assertFileExists($directory.'/'.BackupService::FARA_CONTRACT_ACTIV_SPATII_CSV_FILENAME);

        $manifest = json_decode(File::get($directory.'/manifest.json'), true);
        $this->assertSame('sqlite', $manifest['database_format']);
        $this->assertSame('database.sqlite', $manifest['database']);
        $this->assertArrayHasKey('imobile', $manifest['database_table_counts']);
        $this->assertArrayHasKey('spatii', $manifest['database_table_counts']);

        $imobileCsv = File::get($directory.'/imobile.csv');
        $spatiiCsv = File::get($imobilCsv);
        $chiriasiCsv = File::get($directory.'/chiriasi.csv');

        $this->assertStringContainsString('sep=;', $imobileCsv);
        $this->assertStringContainsString('Nume imobil', $imobileCsv);
        $this->assertStringContainsString('Imobil backup', $imobileCsv);
        $this->assertStringContainsString('Timisoara', $imobileCsv);
        $this->assertStringNotContainsString('Timișoara', $imobileCsv);

        $this->assertStringContainsString('sep=;', $spatiiCsv);
        $this->assertStringContainsString('Identificat la locator cu numarul', $spatiiCsv);
        $this->assertStringContainsString('Suprafata mp', $spatiiCsv);
        $this->assertStringNotContainsString('Suprafață', $spatiiCsv);
        $this->assertStringContainsString($spatiu->identificator, $spatiiCsv);
        $this->assertStringNotContainsString('Chirie lunara EUR', $spatiiCsv);

        $this->assertStringContainsString('sep=;', $chiriasiCsv);
        $this->assertStringContainsString('SC Test SRL', $chiriasiCsv);
        $this->assertStringContainsString('Chirie lunara EUR', $chiriasiCsv);
    }

    public function test_backup_creates_separate_csv_for_each_imobil(): void
    {
        $this->seedSpatiu();
        $imobilDoi = Imobil::query()->create([
            'nume' => 'Imobil doi',
            'strada' => 'Strada 2',
            'numar' => '2',
            'localitate' => 'Timișoara',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobilDoi->id,
            'identificator' => 'D201',
            'status' => 'liber',
            'moneda' => 'EUR',
        ]);

        $this->post(route('backup.store'));

        $directory = storage_path('app/backups/manual/spatii');

        $this->assertFileExists($directory.'/'.$imobilDoi->id.'-imobil-doi.csv');
        $this->assertSame(2, count(File::files($directory)));
    }

    public function test_backup_download_returns_files(): void
    {
        Carbon::setTestNow('2026-06-17 15:24:00');

        $spatiu = $this->seedSpatiu();
        $this->post(route('backup.store'));

        $filename = $spatiu->imobil_id.'-imobil-backup.csv';
        $dateLabel = '2026-06-17-15-24';

        $this->get(route('backup.download', ['date' => 'manual', 'type' => 'database']))
            ->assertOk()
            ->assertDownload("imocore-database-{$dateLabel}.sqlite");

        $this->get(route('backup.download', ['date' => 'manual', 'type' => 'imobile']))
            ->assertOk()
            ->assertDownload("imocore-imobile-{$dateLabel}.csv");

        $this->get(route('backup.download.spatii', ['date' => 'manual', 'file' => $filename]))
            ->assertOk()
            ->assertDownload("imocore-spatii-{$spatiu->imobil_id}-imobil-backup-{$dateLabel}.csv");

        $this->get(route('backup.download', ['date' => 'manual', 'type' => 'chiriasi']))
            ->assertOk()
            ->assertDownload("imocore-chiriasi-{$dateLabel}.csv");

        $this->get(route('backup.download', ['date' => 'manual', 'type' => 'spatii-toate']))
            ->assertOk()
            ->assertDownload("imocore-spatii-toate-{$dateLabel}.csv");

        $this->get(route('backup.download', ['date' => 'manual', 'type' => 'spatii-marcate']))
            ->assertOk()
            ->assertDownload("imocore-spatii-marcate-{$dateLabel}.csv");

        $this->get(route('backup.download', ['date' => 'manual', 'type' => 'spatii-fara-anexa']))
            ->assertOk()
            ->assertDownload("imocore-spatii-fara-anexa-{$dateLabel}.csv");

        $this->get(route('backup.download', ['date' => 'manual', 'type' => 'spatii-fara-contract-activ']))
            ->assertOk()
            ->assertDownload("imocore-spatii-fara-contract-activ-{$dateLabel}.csv");

        Carbon::setTestNow();
    }

    public function test_on_demand_marcate_fara_anexa_and_fara_contract_activ_downloads_return_csv(): void
    {
        $this->seedSpatiu();

        $this->get(route('backup.download.spatii-marcate'))
            ->assertOk()
            ->assertDownload('imocore-spatii-marcate-'.now()->format('Y-m-d').'.csv');

        $this->get(route('backup.download.spatii-fara-anexa'))
            ->assertOk()
            ->assertDownload('imocore-spatii-fara-anexa-'.now()->format('Y-m-d').'.csv');

        $this->get(route('backup.download.spatii-fara-contract-activ'))
            ->assertOk()
            ->assertDownload('imocore-spatii-fara-contract-activ-'.now()->format('Y-m-d').'.csv');
    }

    public function test_marcate_spatii_csv_exports_only_marked_spaces(): void
    {
        $spatiu = $this->seedSpatiu();
        $spatiu->update([
            'de_lamurit' => true,
            'de_lamurit_detaliu' => 'Text de lamurit',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $spatiu->imobil_id,
            'identificator' => 'B102',
            'status' => 'liber',
            'moneda' => 'EUR',
            'ordine' => 2,
        ]);

        $this->post(route('backup.store'));

        $csv = BackupExportValidator::parseCsvFile(
            storage_path('app/backups/manual/'.BackupService::MARCATE_SPATII_CSV_FILENAME)
        );

        $this->assertSame(
            BackupExportValidator::expectedFilteredAllSpatiiHeaders(BackupExportValidator::allSpatiiEditableFieldsUnion()),
            $csv['headers']
        );
        $this->assertCount(1, $csv['rows']);
        $this->assertSame('De lamurit', $csv['rows'][0][0]);
        $this->assertSame($spatiu->identificator, $csv['rows'][0][4]);
        $this->assertSame('Text de lamurit', $csv['rows'][0][count($csv['headers']) - 2]);
    }

    public function test_fara_anexa_spatii_csv_exports_only_inchiriate_without_annex(): void
    {
        $spatiu = $this->seedSpatiu();

        Spatiu::query()->create([
            'imobil_id' => $spatiu->imobil_id,
            'identificator' => 'B102',
            'status' => 'liber',
            'moneda' => 'EUR',
            'ordine' => 2,
        ]);

        $this->post(route('backup.store'));

        $csv = BackupExportValidator::parseCsvFile(
            storage_path('app/backups/manual/'.BackupService::FARA_ANEXA_SPATII_CSV_FILENAME)
        );

        $this->assertCount(1, $csv['rows']);
        $this->assertSame('Fara anexa', $csv['rows'][0][0]);
        $this->assertSame($spatiu->identificator, $csv['rows'][0][4]);
        $this->assertSame('', $csv['rows'][0][count($csv['headers']) - 2]);
    }

    public function test_fara_contract_activ_spatii_csv_exports_only_spaces_without_current_active_contract(): void
    {
        Carbon::setTestNow('2026-06-17 10:00:00');

        $spatiuFaraContract = $this->seedSpatiu();

        $spatiuCuContractActiv = Spatiu::query()->create([
            'imobil_id' => $spatiuFaraContract->imobil_id,
            'identificator' => 'B102',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'ordine' => 2,
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiuCuContractActiv->id,
            'numar_contract' => 'ACT-1',
            'chirias' => 'Chirias activ',
            'data_start' => '2026-01-01',
            'data_end' => '2026-12-31',
            'chirie' => 100,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $spatiuCuContractExpirat = Spatiu::query()->create([
            'imobil_id' => $spatiuFaraContract->imobil_id,
            'identificator' => 'B103',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'ordine' => 3,
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiuCuContractExpirat->id,
            'numar_contract' => 'EXP-1',
            'chirias' => 'Chirias expirat',
            'data_start' => '2025-01-01',
            'data_end' => '2025-12-31',
            'chirie' => 100,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $this->post(route('backup.store'));

        $csv = BackupExportValidator::parseCsvFile(
            storage_path('app/backups/manual/'.BackupService::FARA_CONTRACT_ACTIV_SPATII_CSV_FILENAME)
        );

        $this->assertCount(2, $csv['rows']);
        $this->assertSame('Fara contract activ', $csv['rows'][0][0]);
        $this->assertSame($spatiuFaraContract->identificator, $csv['rows'][0][4]);
        $this->assertSame($spatiuCuContractExpirat->identificator, $csv['rows'][1][4]);

        Carbon::setTestNow();
    }

    public function test_on_demand_all_spatii_download_returns_csv(): void
    {
        $this->seedSpatiu();

        $this->get(route('backup.download.spatii-toate'))
            ->assertOk()
            ->assertDownload('imocore-spatii-toate-'.now()->format('Y-m-d').'.csv');
    }

    public function test_unified_spatii_csv_contains_all_imobile_in_order(): void
    {
        $spatiuUnu = $this->seedSpatiu();
        $imobilDoi = Imobil::query()->create([
            'nume' => 'Imobil doi',
            'strada' => 'Strada 2',
            'numar' => '2',
            'localitate' => 'Timișoara',
        ]);

        $spatiuDoi = Spatiu::query()->create([
            'imobil_id' => $imobilDoi->id,
            'identificator' => 'D201',
            'status' => 'liber',
            'moneda' => 'EUR',
            'ordine' => 1,
        ]);

        $this->post(route('backup.store'));

        $csv = BackupExportValidator::parseCsvFile(
            storage_path('app/backups/manual/'.BackupService::ALL_SPATII_CSV_FILENAME)
        );

        $this->assertSame(
            BackupExportValidator::expectedAllSpatiiHeaders(BackupExportValidator::allSpatiiEditableFieldsUnion()),
            $csv['headers']
        );
        $this->assertCount(2, $csv['rows']);
        $this->assertSame((string) $spatiuUnu->imobil_id, $csv['rows'][0][0]);
        $this->assertSame('Imobil backup', $csv['rows'][0][1]);
        $this->assertSame($spatiuUnu->identificator, $csv['rows'][0][3]);
        $this->assertSame((string) $imobilDoi->id, $csv['rows'][1][0]);
        $this->assertSame('Imobil doi', $csv['rows'][1][1]);
        $this->assertSame($spatiuDoi->identificator, $csv['rows'][1][3]);
    }

    public function test_unified_spatii_csv_includes_detaliu_de_lamurit_before_data_export(): void
    {
        $spatiu = $this->seedSpatiu();
        $spatiu->update([
            'de_lamurit' => true,
            'de_lamurit_detaliu' => 'Suprafata de confirmat',
        ]);

        $this->post(route('backup.store'));

        $csv = BackupExportValidator::parseCsvFile(
            storage_path('app/backups/manual/'.BackupService::ALL_SPATII_CSV_FILENAME)
        );

        $detaliuIndex = array_search('Detaliu de lamurit', $csv['headers'], true);
        $exportIndex = array_search('Data export', $csv['headers'], true);

        $this->assertNotFalse($detaliuIndex);
        $this->assertNotFalse($exportIndex);
        $this->assertSame($exportIndex - 1, $detaliuIndex);
        $this->assertSame('Suprafata de confirmat', $csv['rows'][0][$detaliuIndex]);
    }

    public function test_daily_command_creates_automatic_backup(): void
    {
        $this->seedSpatiu();

        Artisan::call('backup:run-daily');

        $this->assertEquals(0, Artisan::call('backup:run-daily'));

        $date = now()->format('Y-m-d');
        $manifest = json_decode(File::get(storage_path("app/backups/{$date}/manifest.json")), true);

        $this->assertSame('automatic', $manifest['trigger']);
        $this->assertNotEmpty($manifest['spatii_files']);
    }

    public function test_manual_and_automatic_backups_appear_as_separate_rows(): void
    {
        $this->seedSpatiu();
        $backupService = app(BackupService::class);

        $backupService->runBackup('automatic');
        $backupService->runBackup('manual');

        $this->get(route('backup.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backup/Index')
                ->has('backups', 2)
                ->where('backups.0.date', 'manual')
                ->where('backups.0.trigger', 'manual')
                ->where('backups.1.trigger', 'automatic'));

        $this->assertDirectoryExists(storage_path('app/backups/manual'));
        $this->assertDirectoryExists(storage_path('app/backups/'.now()->format('Y-m-d')));
    }

    public function test_old_backups_are_pruned_after_retention_period(): void
    {
        $backupService = app(BackupService::class);
        $oldDate = now()->subDays(10)->format('Y-m-d');
        $oldDirectory = $backupService->backupRoot().DIRECTORY_SEPARATOR.$oldDate;

        File::makeDirectory($oldDirectory, 0755, true);
        File::put($oldDirectory.DIRECTORY_SEPARATOR.'manifest.json', '{}');

        Carbon::setTestNow(now()->startOfDay());

        $this->seedSpatiu();
        $backupService->runBackup('manual');

        $this->assertDirectoryDoesNotExist($oldDirectory);
        $this->assertDirectoryExists(storage_path('app/backups/manual'));

        Carbon::setTestNow();
    }

    private function seedSpatiu(): Spatiu
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil backup',
            'strada' => 'Strada Test',
            'numar' => '10',
            'localitate' => 'Timișoara',
        ]);

        return Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'B101',
            'status' => 'inchiriat',
            'chirias' => 'SC Test SRL',
            'pret_lunar' => 450,
            'moneda' => 'EUR',
        ]);
    }

    private function cleanBackupRoot(): void
    {
        $root = storage_path('app/backups');

        if (File::isDirectory($root)) {
            File::deleteDirectory($root);
        }
    }
}
