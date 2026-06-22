<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Imobil;
use App\Models\Locator;
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

    public function test_backup_page_creates_missing_automatic_backup_for_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 23:30:00', 'Europe/Bucharest'));

        $this->seedSpatiu();

        $this->get(route('backup.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backup/Index')
                ->has('backups', 1)
                ->where('backups.0.date', '2026-06-19'));

        $this->assertDirectoryExists(storage_path('app/backups/2026-06-19'));

        Carbon::setTestNow();
    }

    public function test_backup_page_waits_until_three_am_when_automatic_history_already_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 10:00:00', 'Europe/Bucharest'));
        $this->seedSpatiu();
        app(BackupService::class)->runBackup('automatic');

        Carbon::setTestNow(Carbon::parse('2026-06-20 02:00:00', 'Europe/Bucharest'));

        $this->get(route('backup.index'))->assertOk();

        $this->assertDirectoryDoesNotExist(storage_path('app/backups/2026-06-20'));

        Carbon::setTestNow();
    }

    public function test_backup_cron_route_runs_with_valid_token(): void
    {
        config(['services.backup.cron_token' => 'secret-token']);

        Carbon::setTestNow(Carbon::parse('2026-06-19 12:00:00', 'Europe/Bucharest'));
        $this->seedSpatiu();

        $this->get(route('backup.cron', ['token' => 'secret-token']))
            ->assertOk()
            ->assertSee('backup-created');

        $this->assertDirectoryExists(storage_path('app/backups/2026-06-19'));

        Carbon::setTestNow();
    }

    public function test_backup_page_lists_automatic_backups(): void
    {
        $this->seedSpatiu();
        app(BackupService::class)->runBackup('automatic');

        $this->get(route('backup.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backup/Index')
                ->has('backups', 1)
                ->where('backups.0.trigger', 'automatic')
                ->where('retentionDays', 7));
    }

    public function test_manual_backup_appears_on_page_after_backup_acum(): void
    {
        $this->seedSpatiu();

        $this->post(route('backup.store'))->assertRedirect(route('backup.index'));

        $this->get(route('backup.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backup/Index')
                ->where('manualBackup.trigger', 'manual')
                ->where('manualBackup.database_format', 'sqlite')
                ->has('manualBackup.database_url')
                ->has('manualBackup.contracte_csv_url')
                ->has('manualBackup.locatori_csv_url'));
    }

    public function test_manual_backup_creates_database_and_csv_files_per_imobil(): void
    {
        $spatiu = $this->seedSpatiu();

        $this->post(route('backup.store'))->assertRedirect(route('backup.index'));

        $directory = storage_path('app/backups/manual');
        $imobilCsv = $directory.'/spatii/'.$spatiu->imobil_id.'-imobil-backup.csv';

        $this->assertDirectoryExists($directory);
        $this->assertDirectoryExists($directory.'/spatii');
        $this->assertFileExists($directory.'/database.sqlite');
        $this->assertFileExists($imobilCsv);
        $this->assertFileExists($directory.'/imobile.csv');
        $this->assertFileExists($directory.'/chiriasi.csv');
        $this->assertFileExists($directory.'/'.BackupService::CONTRACTE_CSV_FILENAME);
        $this->assertFileExists($directory.'/'.BackupService::LOCATORI_CSV_FILENAME);
        $this->assertFileExists($directory.'/manifest.json');
        $this->assertFileExists($directory.'/'.BackupService::ALL_SPATII_CSV_FILENAME);
        $this->assertFileDoesNotExist($directory.'/'.BackupService::MARCATE_SPATII_CSV_FILENAME);
        $this->assertFileDoesNotExist($directory.'/'.BackupService::FARA_ANEXA_SPATII_CSV_FILENAME);
        $this->assertFileDoesNotExist($directory.'/'.BackupService::FARA_CONTRACT_ACTIV_SPATII_CSV_FILENAME);

        $manifest = json_decode(File::get($directory.'/manifest.json'), true);
        $this->assertSame('sqlite', $manifest['database_format']);
        $this->assertSame('database.sqlite', $manifest['database']);
        $this->assertArrayHasKey('contracte_csv', $manifest);
        $this->assertArrayHasKey('locatori_csv', $manifest);
        $this->assertArrayNotHasKey('spatii_marcate_csv', $manifest);
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

        $this->get(route('backup.download', ['date' => 'manual', 'type' => 'contracte']))
            ->assertOk()
            ->assertDownload("imocore-contracte-{$dateLabel}.csv");

        $this->get(route('backup.download', ['date' => 'manual', 'type' => 'locatori']))
            ->assertOk()
            ->assertDownload("imocore-locatori-{$dateLabel}.csv");

        Carbon::setTestNow();
    }

    public function test_on_demand_all_spatii_download_returns_xlsx(): void
    {
        $this->seedSpatiu();

        $response = $this->get(route('backup.download.spatii-toate'));

        $response
            ->assertOk()
            ->assertDownload('imocore-spatii-toate-'.now()->format('Y-m-d').'.xlsx');
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

    public function test_contracte_and_locatori_csv_are_exported(): void
    {
        $spatiu = $this->seedSpatiu();
        $locator = Locator::query()->create([
            'imobil_id' => $spatiu->imobil_id,
            'nume' => 'Locator Test SRL',
            'cui' => '12345678',
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-101',
            'chirias' => 'SC Test SRL',
            'data_start' => '2026-01-01',
            'chirie' => 500,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $spatiu->update(['locator_id' => $locator->id]);

        $this->post(route('backup.store'));

        $contracteCsv = BackupExportValidator::parseCsvFile(
            storage_path('app/backups/manual/'.BackupService::CONTRACTE_CSV_FILENAME)
        );
        $locatoriCsv = BackupExportValidator::parseCsvFile(
            storage_path('app/backups/manual/'.BackupService::LOCATORI_CSV_FILENAME)
        );

        $this->assertCount(1, $contracteCsv['rows']);
        $this->assertSame('C-101', $contracteCsv['rows'][0][3]);
        $this->assertCount(1, $locatoriCsv['rows']);
        $this->assertSame('Locator Test SRL', $locatoriCsv['rows'][0][1]);
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
        $this->assertArrayHasKey('contracte_csv', $manifest);
        $this->assertArrayHasKey('locatori_csv', $manifest);
    }

    public function test_automatic_backups_appear_in_history_and_manual_is_separate(): void
    {
        $this->seedSpatiu();
        $backupService = app(BackupService::class);

        $backupService->runBackup('automatic');
        $backupService->runBackup('manual');

        $this->get(route('backup.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backup/Index')
                ->has('backups', 1)
                ->where('backups.0.trigger', 'automatic'));

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
        $backupService->runBackup('automatic');

        $this->assertDirectoryDoesNotExist($oldDirectory);
        $this->assertDirectoryExists(storage_path('app/backups/'.now()->format('Y-m-d')));

        Carbon::setTestNow();
    }

    public function test_backup_history_keeps_only_last_seven_days(): void
    {
        $backupService = app(BackupService::class);
        $this->seedSpatiu();

        Carbon::setTestNow('2026-06-20 10:00:00');

        for ($daysAgo = 0; $daysAgo <= 7; $daysAgo++) {
            $date = now()->copy()->subDays($daysAgo)->format('Y-m-d');
            $directory = $backupService->backupRoot().DIRECTORY_SEPARATOR.$date;
            File::makeDirectory($directory, 0755, true);
            File::put($directory.'/database.sqlite', 'sqlite');
            File::put($directory.'/manifest.json', json_encode([
                'created_at' => now()->copy()->subDays($daysAgo)->toIso8601String(),
                'trigger' => 'automatic',
                'database_format' => 'sqlite',
            ]));
        }

        $backups = $backupService->listBackups();

        $this->assertCount(7, $backups);
        $this->assertSame('2026-06-20', $backups[0]['date']);
        $this->assertSame('2026-06-14', $backups[6]['date']);

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
