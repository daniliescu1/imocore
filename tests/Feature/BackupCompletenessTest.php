<?php

namespace Tests\Feature;

use App\Models\ConfigurareAnexaImobil;
use App\Models\Imobil;
use App\Models\Locator;
use App\Models\Spatiu;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\BackupExportValidator;
use Tests\TestCase;

class BackupCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDirectory;

    private string $exportDate;

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

    public function test_backup_csv_si_sqlite_contin_toate_campurile_complete(): void
    {
        $dataset = $this->seedCompleteDataset();

        app(BackupService::class)->runBackup('manual');

        $this->backupDirectory = storage_path('app/backups/manual');
        $manifest = json_decode(File::get($this->backupDirectory.'/manifest.json'), true);
        $this->exportDate = isset($manifest['created_at'])
            ? \Illuminate\Support\Carbon::parse($manifest['created_at'])->format('Y-m-d H:i:s')
            : now()->format('Y-m-d H:i:s');

        $this->assertImobileCsvComplete($dataset['imobil']);
        $this->assertSpatiiCsvComplete($dataset['imobil'], $dataset['spatiu']);
        $this->assertAllSpatiiCsvComplete($dataset['imobil'], $dataset['spatiu']);
        $this->assertContracteAndLocatoriCsvExist();
        $this->assertChiriasiCsvComplete($dataset['imobil'], $dataset['spatiu']);
        $this->assertDatabaseBackupComplete();
        $this->assertAllCsvFilesHaveNoDiacritics();
    }

    private function assertImobileCsvComplete(Imobil $imobil): void
    {
        $csv = BackupExportValidator::parseCsvFile($this->backupDirectory.'/imobile.csv');

        $this->assertSame(BackupExportValidator::expectedImobileHeaders(), $csv['headers']);
        $this->assertCount(1, $csv['rows']);

        $expected = BackupExportValidator::expectedImobilRow($imobil->fresh(), $this->exportDate);
        $row = BackupExportValidator::findMatchingRow($csv, ['Nume imobil' => $expected['Nume imobil']]);

        $this->assertNotNull($row, 'Imobilul nu apare in imobile.csv');

        foreach ($expected as $header => $value) {
            $this->assertSame($value, $row[$header], "Coloana imobile.csv [{$header}] nu corespunde.");
        }
    }

    private function assertSpatiiCsvComplete(Imobil $imobil, Spatiu $spatiu): void
    {
        $imobil->load(['spatii.locatorEntitate', 'spatii.configurareAnexa']);
        $editableFields = BackupExportValidator::editableSpatiuFieldsForImobil($imobil);
        $path = $this->backupDirectory.'/spatii/'.BackupExportValidator::spatiiCsvFilename($imobil);

        $this->assertFileExists($path);

        $csv = BackupExportValidator::parseCsvFile($path);

        $this->assertSame(BackupExportValidator::expectedSpatiiHeaders($editableFields), $csv['headers']);
        $this->assertCount($imobil->spatii->count(), $csv['rows']);

        $spatiu->refresh()->load(['locatorEntitate', 'configurareAnexa']);
        $expected = BackupExportValidator::expectedSpatiuRow($spatiu, $editableFields, $this->exportDate);
        $row = BackupExportValidator::findMatchingRow($csv, [
            'Identificat la locator cu numarul' => $expected['Identificat la locator cu numarul'],
        ]);

        $this->assertNotNull($row, 'Spatiul nu apare in CSV-ul imobilului.');

        foreach ($expected as $header => $value) {
            $this->assertSame($value, $row[$header], "Coloana spatii.csv [{$header}] nu corespunde.");
        }
    }

    private function assertAllSpatiiCsvComplete(Imobil $imobil, Spatiu $spatiu): void
    {
        $imobil->load(['spatii.locatorEntitate', 'spatii.configurareAnexa']);
        $columnFields = BackupExportValidator::allSpatiiEditableFieldsUnion();
        $editableFields = BackupExportValidator::editableSpatiuFieldsForImobil($imobil);
        $path = $this->backupDirectory.'/'.BackupService::ALL_SPATII_CSV_FILENAME;

        $this->assertFileExists($path);

        $csv = BackupExportValidator::parseCsvFile($path);

        $this->assertSame(BackupExportValidator::expectedAllSpatiiHeaders($columnFields), $csv['headers']);
        $this->assertCount($imobil->spatii->count(), $csv['rows']);

        $spatiu->refresh()->load(['locatorEntitate', 'configurareAnexa']);
        $expected = BackupExportValidator::expectedAllSpatiuRow(
            $spatiu,
            $imobil->fresh(),
            $columnFields,
            $editableFields,
            $this->exportDate,
        );
        $row = BackupExportValidator::findMatchingRow($csv, [
            'Identificat la locator cu numarul' => $expected['Identificat la locator cu numarul'],
            'ID imobil' => $expected['ID imobil'],
        ]);

        $this->assertNotNull($row, 'Spatiul nu apare in spatii-toate.csv');

        foreach ($expected as $header => $value) {
            $this->assertSame($value, $row[$header], "Coloana spatii-toate.csv [{$header}] nu corespunde.");
        }
    }

    private function assertChiriasiCsvComplete(Imobil $imobil, Spatiu $spatiu): void
    {
        $csv = BackupExportValidator::parseCsvFile($this->backupDirectory.'/chiriasi.csv');
        $spatiu->refresh()->load(['locatorEntitate', 'imobil']);

        $this->assertCount(1, $csv['rows']);

        $expected = BackupExportValidator::expectedChiriasRow($spatiu, $imobil->fresh(), $this->exportDate);
        $row = BackupExportValidator::findMatchingRow($csv, [
            'Identificator spatiu' => $expected['Identificator spatiu'],
        ]);

        $this->assertNotNull($row, 'Chiriasul nu apare in chiriasi.csv');

        foreach ($expected as $header => $value) {
            $this->assertSame($value, $row[$header], "Coloana chiriasi.csv [{$header}] nu corespunde.");
        }
    }

    private function assertContracteAndLocatoriCsvExist(): void
    {
        $this->assertFileExists($this->backupDirectory.'/'.BackupService::CONTRACTE_CSV_FILENAME);
        $this->assertFileExists($this->backupDirectory.'/'.BackupService::LOCATORI_CSV_FILENAME);

        $contracteCsv = BackupExportValidator::parseCsvFile($this->backupDirectory.'/'.BackupService::CONTRACTE_CSV_FILENAME);
        $locatoriCsv = BackupExportValidator::parseCsvFile($this->backupDirectory.'/'.BackupService::LOCATORI_CSV_FILENAME);

        $this->assertSame('Numar contract', $contracteCsv['headers'][3]);
        $this->assertSame('Nume locator', $locatoriCsv['headers'][1]);
    }

    private function assertDatabaseBackupComplete(): void
    {
        $backupPath = $this->backupDirectory.'/database.sqlite';
        $sourcePath = config('database.connections.sqlite.database');

        $this->assertFileExists($backupPath);
        $this->assertGreaterThan(1000, filesize($backupPath));

        $mismatches = BackupExportValidator::compareDatabaseCounts($sourcePath, $backupPath, [
            'imobile',
            'spatii',
            'locatori',
            'configurari_anexe_imobil',
            'configurare_anexa_linii',
        ]);

        $this->assertSame([], $mismatches, 'Backup SQLite nu contine aceleasi inregistrari ca baza sursa.');
    }

    private function assertAllCsvFilesHaveNoDiacritics(): void
    {
        $files = array_merge(
            [
                $this->backupDirectory.'/imobile.csv',
                $this->backupDirectory.'/chiriasi.csv',
                $this->backupDirectory.'/'.BackupService::ALL_SPATII_CSV_FILENAME,
                $this->backupDirectory.'/'.BackupService::CONTRACTE_CSV_FILENAME,
                $this->backupDirectory.'/'.BackupService::LOCATORI_CSV_FILENAME,
            ],
            collect(File::files($this->backupDirectory.'/spatii'))
                ->map(fn (\SplFileInfo $file): string => $file->getPathname())
                ->all()
        );

        foreach ($files as $file) {
            $this->assertTrue(
                BackupExportValidator::assertNoDiacritics(File::get($file)),
                'Fisierul '.basename($file).' contine diacritice.'
            );
        }
    }

    /**
     * @return array{imobil: Imobil, spatiu: Spatiu, locator: Locator}
     */
    private function seedCompleteDataset(): array
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil Complet Test',
            'strada' => 'Strada Principala',
            'numar' => '42',
            'localitate' => 'Timișoara',
            'judet' => 'Timiș',
            'cod_postal' => '300001',
            'numere_cf' => [
                ['numar' => '123456', 'observatii' => 'CF principal'],
                ['numar' => '789012', 'observatii' => 'CF secundar'],
            ],
            'campuri_spatiu_vizibile' => array_keys(Imobil::CAMPURI_SPATIU_CONFIGURABILE),
            'observatii' => 'Observatii imobil test',
        ]);

        $locator = Locator::query()->create(['nume' => 'Locator Test SRL']);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa test utilitati',
            'implicit' => true,
            'activ' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'SPC 101',
            'suprafata_contractuala_mp' => 125.50,
            'corp' => 'A',
            'etaj' => 'Parter',
            'status' => 'inchiriat',
            'pret_lunar' => 1500.00,
            'indexare_2026' => 1750.75,
            'regim_incalzire' => 'partial',
            'procent_incalzire_override' => 60,
            'locator_id' => $locator->id,
            'configurare_anexa_id' => $configurare->id,
            'chirias' => 'SC Chiriaș Test SRL',
            'observatii' => 'Observatii spatiu test',
            'moneda' => 'EUR',
            'ordine' => 1,
        ]);

        return [
            'imobil' => $imobil,
            'spatiu' => $spatiu,
            'locator' => $locator,
        ];
    }

    private function cleanBackupRoot(): void
    {
        $root = storage_path('app/backups');

        if (File::isDirectory($root)) {
            File::deleteDirectory($root);
        }
    }
}
