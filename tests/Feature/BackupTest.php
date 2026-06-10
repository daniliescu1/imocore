<?php

namespace Tests\Feature;

use App\Models\Imobil;
use App\Models\Spatiu;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
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
                ->where('backups.0.trigger', 'manual')
                ->where('retentionDays', 7));
    }

    public function test_manual_backup_creates_database_and_csv_files_per_imobil(): void
    {
        $spatiu = $this->seedSpatiu();

        $this->post(route('backup.store'))->assertRedirect(route('backup.index'));

        $date = now()->format('Y-m-d');
        $directory = storage_path('app/backups/'.$date);
        $imobilCsv = $directory.'/spatii/'.$spatiu->imobil_id.'-imobil-backup.csv';

        $this->assertDirectoryExists($directory);
        $this->assertDirectoryExists($directory.'/spatii');
        $this->assertFileExists($directory.'/database.sqlite');
        $this->assertFileExists($imobilCsv);
        $this->assertFileExists($directory.'/imobile.csv');
        $this->assertFileExists($directory.'/chiriasi.csv');
        $this->assertFileExists($directory.'/manifest.json');

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

        $date = now()->format('Y-m-d');
        $directory = storage_path('app/backups/'.$date.'/spatii');

        $this->assertFileExists($directory.'/'.$imobilDoi->id.'-imobil-doi.csv');
        $this->assertSame(2, count(File::files($directory)));
    }

    public function test_backup_download_returns_files(): void
    {
        $spatiu = $this->seedSpatiu();
        $this->post(route('backup.store'));

        $date = now()->format('Y-m-d');
        $filename = $spatiu->imobil_id.'-imobil-backup.csv';

        $this->get(route('backup.download', ['date' => $date, 'type' => 'database']))
            ->assertOk()
            ->assertDownload("imocore-database-{$date}.sqlite");

        $this->get(route('backup.download', ['date' => $date, 'type' => 'imobile']))
            ->assertOk()
            ->assertDownload("imocore-imobile-{$date}.csv");

        $this->get(route('backup.download.spatii', ['date' => $date, 'file' => $filename]))
            ->assertOk()
            ->assertDownload("imocore-spatii-{$spatiu->imobil_id}-imobil-backup-{$date}.csv");

        $this->get(route('backup.download', ['date' => $date, 'type' => 'chiriasi']))
            ->assertOk()
            ->assertDownload("imocore-chiriasi-{$date}.csv");
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
