<?php

namespace App\Services;

use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class BackupService
{
    public const RETENTION_DAYS = 7;

    public const MANUAL_BACKUP_ID = 'manual';

    public const ALL_SPATII_CSV_FILENAME = 'spatii-toate.csv';

    public const MARCATE_SPATII_CSV_FILENAME = 'spatii-marcate.csv';

    public const FARA_ANEXA_SPATII_CSV_FILENAME = 'spatii-fara-anexa.csv';

    private const SPATIU_CAMPURI_DOAR_CITIRE = [
        'persoane_standard',
        'pret_mp_ultima_indexare',
    ];

    public function backupRoot(): string
    {
        return storage_path('app/backups');
    }

    /**
     * @return array{date: string, directory: string, database: string, spatii_files: list<array{imobil_id: int, imobil: string, filename: string, path: string}>, chiriasi_csv: string, imobile_csv: string, spatii_toate: string, created_at: string, trigger: string}
     */
    public function runBackup(string $trigger = 'manual'): array
    {
        $date = $trigger === 'manual'
            ? self::MANUAL_BACKUP_ID
            : now()->format('Y-m-d');
        $directory = $this->backupRoot().DIRECTORY_SEPARATOR.$date;

        if ($trigger === 'manual' && File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $databasePath = $this->backupDatabase($directory);
        $createdAt = now();
        $exportDate = $createdAt->format('Y-m-d H:i:s');
        $csvExport = $this->exportCsvFiles($directory, $exportDate);

        File::put($directory.DIRECTORY_SEPARATOR.'manifest.json', json_encode([
            'date' => $date,
            'created_at' => $createdAt->toIso8601String(),
            'trigger' => $trigger,
            'database' => basename($databasePath),
            'spatii_files' => collect($csvExport['spatii_files'])
                ->map(fn (array $file): array => [
                    'imobil_id' => $file['imobil_id'],
                    'imobil' => $file['imobil'],
                    'filename' => $file['filename'],
                ])
                ->all(),
            'chiriasi_csv' => basename($csvExport['chiriasi']),
            'imobile_csv' => basename($csvExport['imobile']),
            'spatii_toate_csv' => basename($csvExport['spatii_toate']),
            'spatii_marcate_csv' => basename($csvExport['spatii_marcate']),
            'spatii_fara_anexa_csv' => basename($csvExport['spatii_fara_anexa']),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->pruneOldBackups();

        return [
            'date' => $date,
            'directory' => $directory,
            'database' => $databasePath,
            'spatii_files' => $csvExport['spatii_files'],
            'chiriasi_csv' => $csvExport['chiriasi'],
            'imobile_csv' => $csvExport['imobile'],
            'spatii_toate' => $csvExport['spatii_toate'],
            'spatii_marcate' => $csvExport['spatii_marcate'],
            'spatii_fara_anexa' => $csvExport['spatii_fara_anexa'],
            'created_at' => $createdAt->toIso8601String(),
            'trigger' => $trigger,
        ];
    }

    public function onDemandAllSpatiiDownloadFilename(): string
    {
        return 'imocore-spatii-toate-'.now()->format('Y-m-d').'.csv';
    }

    public function onDemandMarcateSpatiiDownloadFilename(): string
    {
        return 'imocore-spatii-marcate-'.now()->format('Y-m-d').'.csv';
    }

    public function onDemandFaraAnexaSpatiiDownloadFilename(): string
    {
        return 'imocore-spatii-fara-anexa-'.now()->format('Y-m-d').'.csv';
    }

    /**
     * @return list<array{date: string, created_at: string|null, trigger: string|null, database_url: string|null, imobile_csv_url: string|null, spatii_files: list<array{imobil_id: int|null, imobil: string, filename: string, url: string, size: int}>, spatii_toate_csv_url: string|null, chiriasi_csv_url: string|null, database_size: int|null, imobile_csv_size: int|null, spatii_toate_csv_size: int|null, chiriasi_csv_size: int|null}>
     */
    public function listBackups(): array
    {
        if (! File::isDirectory($this->backupRoot())) {
            return [];
        }

        $backups = [];

        $manualDirectory = $this->backupRoot().DIRECTORY_SEPARATOR.self::MANUAL_BACKUP_ID;

        if (File::isDirectory($manualDirectory) && $this->findDatabaseFile($manualDirectory)) {
            $manualBackup = $this->buildBackupEntry($manualDirectory, self::MANUAL_BACKUP_ID);

            if ($manualBackup !== null) {
                $backups[] = $manualBackup;
            }
        }

        $dailyBackups = collect(File::directories($this->backupRoot()))
            ->map(function (string $directory): ?array {
                $date = basename($directory);

                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    return null;
                }

                return $this->buildBackupEntry($directory, $date);
            })
            ->filter()
            ->sortByDesc(fn (array $backup): string => $backup['created_at'] ?? ($backup['date'].'T00:00:00'))
            ->values()
            ->all();

        return [...$backups, ...$dailyBackups];
    }

    /**
     * @return array{date: string, created_at: string|null, trigger: string|null, database_url: string|null, imobile_csv_url: string|null, spatii_files: list<array{imobil_id: int|null, imobil: string, filename: string, url: string, size: int}>, spatii_toate_csv_url: string|null, chiriasi_csv_url: string|null, database_size: int|null, imobile_csv_size: int|null, spatii_toate_csv_size: int|null, chiriasi_csv_size: int|null}|null
     */
    private function buildBackupEntry(string $directory, string $dateKey): ?array
    {
        $manifest = $this->readManifest($directory);
        $databaseFile = $this->findDatabaseFile($directory);
        $chiriasiCsvFile = $directory.DIRECTORY_SEPARATOR.'chiriasi.csv';
        $imobileCsvFile = $directory.DIRECTORY_SEPARATOR.'imobile.csv';
        $spatiiToateCsvFile = $directory.DIRECTORY_SEPARATOR.self::ALL_SPATII_CSV_FILENAME;
        $spatiiMarcateCsvFile = $directory.DIRECTORY_SEPARATOR.self::MARCATE_SPATII_CSV_FILENAME;
        $spatiiFaraAnexaCsvFile = $directory.DIRECTORY_SEPARATOR.self::FARA_ANEXA_SPATII_CSV_FILENAME;

        if ($databaseFile === null) {
            return null;
        }

        return [
            'date' => $dateKey,
            'created_at' => $manifest['created_at'] ?? null,
            'trigger' => $manifest['trigger'] ?? null,
            'database_url' => route('backup.download', ['date' => $dateKey, 'type' => 'database']),
            'imobile_csv_url' => File::exists($imobileCsvFile) ? route('backup.download', ['date' => $dateKey, 'type' => 'imobile']) : null,
            'spatii_files' => $this->listSpatiiFilesForBackup($directory, $dateKey, $manifest),
            'spatii_toate_csv_url' => File::exists($spatiiToateCsvFile)
                ? route('backup.download', ['date' => $dateKey, 'type' => 'spatii-toate'])
                : null,
            'spatii_marcate_csv_url' => File::exists($spatiiMarcateCsvFile)
                ? route('backup.download', ['date' => $dateKey, 'type' => 'spatii-marcate'])
                : null,
            'spatii_fara_anexa_csv_url' => File::exists($spatiiFaraAnexaCsvFile)
                ? route('backup.download', ['date' => $dateKey, 'type' => 'spatii-fara-anexa'])
                : null,
            'chiriasi_csv_url' => File::exists($chiriasiCsvFile) ? route('backup.download', ['date' => $dateKey, 'type' => 'chiriasi']) : null,
            'database_size' => File::size($databaseFile),
            'imobile_csv_size' => File::exists($imobileCsvFile) ? File::size($imobileCsvFile) : null,
            'spatii_toate_csv_size' => File::exists($spatiiToateCsvFile) ? File::size($spatiiToateCsvFile) : null,
            'spatii_marcate_csv_size' => File::exists($spatiiMarcateCsvFile) ? File::size($spatiiMarcateCsvFile) : null,
            'spatii_fara_anexa_csv_size' => File::exists($spatiiFaraAnexaCsvFile) ? File::size($spatiiFaraAnexaCsvFile) : null,
            'chiriasi_csv_size' => File::exists($chiriasiCsvFile) ? File::size($chiriasiCsvFile) : null,
        ];
    }

    public function resolveDownloadPath(string $date, string $type): string
    {
        if ($date !== self::MANUAL_BACKUP_ID && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            abort(404);
        }

        $directory = $this->backupRoot().DIRECTORY_SEPARATOR.$date;

        if (! File::isDirectory($directory)) {
            abort(404);
        }

        return match ($type) {
            'database' => $this->findDatabaseFile($directory) ?? abort(404),
            'imobile' => File::exists($directory.DIRECTORY_SEPARATOR.'imobile.csv')
                ? $directory.DIRECTORY_SEPARATOR.'imobile.csv'
                : abort(404),
            'chiriasi' => File::exists($directory.DIRECTORY_SEPARATOR.'chiriasi.csv')
                ? $directory.DIRECTORY_SEPARATOR.'chiriasi.csv'
                : abort(404),
            'spatii-toate' => File::exists($directory.DIRECTORY_SEPARATOR.self::ALL_SPATII_CSV_FILENAME)
                ? $directory.DIRECTORY_SEPARATOR.self::ALL_SPATII_CSV_FILENAME
                : abort(404),
            'spatii-marcate' => File::exists($directory.DIRECTORY_SEPARATOR.self::MARCATE_SPATII_CSV_FILENAME)
                ? $directory.DIRECTORY_SEPARATOR.self::MARCATE_SPATII_CSV_FILENAME
                : abort(404),
            'spatii-fara-anexa' => File::exists($directory.DIRECTORY_SEPARATOR.self::FARA_ANEXA_SPATII_CSV_FILENAME)
                ? $directory.DIRECTORY_SEPARATOR.self::FARA_ANEXA_SPATII_CSV_FILENAME
                : abort(404),
            default => abort(404),
        };
    }

    public function resolveSpatiiDownloadPath(string $date, string $file): string
    {
        if ($date !== self::MANUAL_BACKUP_ID && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            abort(404);
        }

        if (! preg_match('/^[\w\-]+\.csv$/', $file)) {
            abort(404);
        }

        $directory = $this->backupRoot().DIRECTORY_SEPARATOR.$date;
        $path = $directory.DIRECTORY_SEPARATOR.'spatii'.DIRECTORY_SEPARATOR.$file;

        if (! File::exists($path)) {
            $legacyPath = $directory.DIRECTORY_SEPARATOR.'spatii.csv';

            if ($file === 'spatii.csv' && File::exists($legacyPath)) {
                return $legacyPath;
            }

            abort(404);
        }

        return $path;
    }

    public function downloadFilename(string $date, string $type): string
    {
        return match ($type) {
            'database' => "imocore-database-{$date}.sqlite",
            'imobile' => "imocore-imobile-{$date}.csv",
            'chiriasi' => "imocore-chiriasi-{$date}.csv",
            'spatii-toate' => "imocore-spatii-toate-{$date}.csv",
            'spatii-marcate' => "imocore-spatii-marcate-{$date}.csv",
            'spatii-fara-anexa' => "imocore-spatii-fara-anexa-{$date}.csv",
            default => abort(404),
        };
    }

    public function spatiiDownloadFilename(string $date, string $file): string
    {
        $baseName = pathinfo($file, PATHINFO_FILENAME);

        return "imocore-spatii-{$baseName}-{$date}.csv";
    }

    public function pruneOldBackups(int $days = self::RETENTION_DAYS): void
    {
        if (! File::isDirectory($this->backupRoot())) {
            return;
        }

        $cutoff = now()->subDays($days)->startOfDay();

        foreach (File::directories($this->backupRoot()) as $directory) {
            $date = basename($directory);

            if ($date === self::MANUAL_BACKUP_ID || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            if (Carbon::parse($date)->lt($cutoff)) {
                File::deleteDirectory($directory);
            }
        }
    }

    private function backupDatabase(string $directory): string
    {
        $driver = config('database.default');

        return match ($driver) {
            'sqlite' => $this->backupSqliteDatabase($directory),
            'mysql' => $this->backupMysqlDatabase($directory),
            default => throw new RuntimeException("Backup not supported for database driver [{$driver}]."),
        };
    }

    private function backupSqliteDatabase(string $directory): string
    {
        $sourcePath = config('database.connections.sqlite.database');

        if (! is_string($sourcePath) || ! File::exists($sourcePath)) {
            throw new RuntimeException('SQLite database file not found.');
        }

        $targetPath = $directory.DIRECTORY_SEPARATOR.'database.sqlite';

        if (! copy($sourcePath, $targetPath)) {
            throw new RuntimeException('Could not copy SQLite database backup.');
        }

        return $targetPath;
    }

    private function backupMysqlDatabase(string $directory): string
    {
        $connection = config('database.connections.mysql');
        $targetPath = $directory.DIRECTORY_SEPARATOR.'database.sql';

        $result = Process::timeout(120)->env([
            'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
        ])->run([
            'mysqldump',
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? '3306'),
            '--user='.($connection['username'] ?? 'forge'),
            '--single-transaction',
            '--quick',
            $connection['database'] ?? 'forge',
        ]);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: 'MySQL backup failed.'));
        }

        File::put($targetPath, $result->output());

        return $targetPath;
    }

    /**
     * @return array{spatii_files: list<array{imobil_id: int, imobil: string, filename: string, path: string}>, chiriasi: string, imobile: string, spatii_toate: string, spatii_marcate: string, spatii_fara_anexa: string}
     */
    private function exportCsvFiles(string $directory, string $exportDate): array
    {
        $spatiiDirectory = $this->prepareSpatiiExportDirectory($directory);
        $chiriasiPath = $directory.DIRECTORY_SEPARATOR.'chiriasi.csv';
        $imobilePath = $directory.DIRECTORY_SEPARATOR.'imobile.csv';
        $spatiiToatePath = $directory.DIRECTORY_SEPARATOR.self::ALL_SPATII_CSV_FILENAME;
        $spatiiMarcatePath = $directory.DIRECTORY_SEPARATOR.self::MARCATE_SPATII_CSV_FILENAME;
        $spatiiFaraAnexaPath = $directory.DIRECTORY_SEPARATOR.self::FARA_ANEXA_SPATII_CSV_FILENAME;
        $spatiiFiles = [];
        $imobile = $this->imobileWithOrderedSpatii();
        $columnFieldsUnion = $this->editableFieldsUnionFromImobile($imobile);

        $this->exportImobileCsv($imobilePath, $exportDate);

        $chiriasiHandle = $this->openCsvWriter($chiriasiPath);
        $allSpatiiHandle = $this->openCsvWriter($spatiiToatePath);
        $marcateSpatiiHandle = $this->openCsvWriter($spatiiMarcatePath);
        $faraAnexaSpatiiHandle = $this->openCsvWriter($spatiiFaraAnexaPath);

        $this->writeCsvRow($chiriasiHandle, [
            'Imobil',
            'Localitate',
            'Identificator spatiu',
            'Chirias',
            'Locator',
            'Chirie lunara EUR',
            'Chirie curenta EUR',
            'Sursa chirie curenta',
            'Indexare 2026',
            'Pret mp curent',
            'Data export',
        ]);

        $this->writeCsvRow($allSpatiiHandle, $this->allSpatiiCsvHeaders($columnFieldsUnion));
        $this->writeCsvRow($marcateSpatiiHandle, $this->filteredAllSpatiiCsvHeaders($columnFieldsUnion));
        $this->writeCsvRow($faraAnexaSpatiiHandle, $this->filteredAllSpatiiCsvHeaders($columnFieldsUnion));

        $imobile->each(function (Imobil $imobil) use ($spatiiDirectory, $chiriasiHandle, $allSpatiiHandle, $marcateSpatiiHandle, $faraAnexaSpatiiHandle, $exportDate, $columnFieldsUnion, &$spatiiFiles): void {
                if ($imobil->spatii->isEmpty()) {
                    return;
                }

                $editableFields = $this->editableSpatiuFieldsForImobil($imobil);
                $headers = $this->spatiuCsvHeaders($editableFields);
                $filename = $this->spatiiCsvFilename($imobil);
                $path = $spatiiDirectory.DIRECTORY_SEPARATOR.$filename;
                $spatiiHandle = $this->openCsvWriter($path);

                $this->writeCsvRow($spatiiHandle, $headers);

                foreach ($imobil->spatii as $spatiu) {
                    $this->writeCsvRow(
                        $spatiiHandle,
                        $this->buildSpatiuCsvRow($spatiu, $imobil, $editableFields, $editableFields, $exportDate)
                    );
                    $this->writeCsvRow(
                        $allSpatiiHandle,
                        $this->buildSpatiuCsvRow($spatiu, $imobil, $columnFieldsUnion, $editableFields, $exportDate, true)
                    );

                    $marcajLabel = $this->marcajExportLabel($spatiu);

                    if ($marcajLabel !== null) {
                        $this->writeCsvRow(
                            $marcateSpatiiHandle,
                            $this->buildSpatiuCsvRow($spatiu, $imobil, $columnFieldsUnion, $editableFields, $exportDate, true, $marcajLabel)
                        );
                    }

                    if ($this->isSpatiuFaraAnexaInchiriat($spatiu)) {
                        $this->writeCsvRow(
                            $faraAnexaSpatiiHandle,
                            $this->buildSpatiuCsvRow($spatiu, $imobil, $columnFieldsUnion, $editableFields, $exportDate, true, 'Fara anexa')
                        );
                    }

                    $this->writeChiriasRow($chiriasiHandle, $spatiu, $imobil, $exportDate);
                }

                fclose($spatiiHandle);

                $spatiiFiles[] = [
                    'imobil_id' => $imobil->id,
                    'imobil' => $imobil->nume,
                    'filename' => $filename,
                    'path' => $path,
                ];
            });

        fclose($chiriasiHandle);
        fclose($allSpatiiHandle);
        fclose($marcateSpatiiHandle);
        fclose($faraAnexaSpatiiHandle);

        return [
            'spatii_files' => $spatiiFiles,
            'chiriasi' => $chiriasiPath,
            'imobile' => $imobilePath,
            'spatii_toate' => $spatiiToatePath,
            'spatii_marcate' => $spatiiMarcatePath,
            'spatii_fara_anexa' => $spatiiFaraAnexaPath,
        ];
    }

    public function exportAllSpatiiCsv(string $targetPath, string $exportDate): void
    {
        $this->exportFilteredSpatiiCsv($targetPath, $exportDate, fn (): ?string => null, includeAll: true);
    }

    public function exportMarcateSpatiiCsv(string $targetPath, string $exportDate): void
    {
        $this->exportFilteredSpatiiCsv(
            $targetPath,
            $exportDate,
            fn (Spatiu $spatiu): ?string => $this->marcajExportLabel($spatiu),
        );
    }

    public function exportFaraAnexaSpatiiCsv(string $targetPath, string $exportDate): void
    {
        $this->exportFilteredSpatiiCsv(
            $targetPath,
            $exportDate,
            fn (Spatiu $spatiu): ?string => $this->isSpatiuFaraAnexaInchiriat($spatiu) ? 'Fara anexa' : null,
        );
    }

    /**
     * @param  callable(Spatiu): (?string)  $marcajResolver
     */
    private function exportFilteredSpatiiCsv(
        string $targetPath,
        string $exportDate,
        callable $marcajResolver,
        bool $includeAll = false,
    ): void {
        $columnFieldsUnion = $this->allSpatiiEditableFieldsUnion();
        $handle = $this->openCsvWriter($targetPath);

        $this->writeCsvRow(
            $handle,
            $includeAll
                ? $this->allSpatiiCsvHeaders($columnFieldsUnion)
                : $this->filteredAllSpatiiCsvHeaders($columnFieldsUnion)
        );

        $this->imobileWithOrderedSpatii()
            ->each(function (Imobil $imobil) use ($handle, $columnFieldsUnion, $exportDate, $marcajResolver, $includeAll): void {
                if ($imobil->spatii->isEmpty()) {
                    return;
                }

                $editableFields = $this->editableSpatiuFieldsForImobil($imobil);

                foreach ($imobil->spatii as $spatiu) {
                    $marcaj = $includeAll ? null : $marcajResolver($spatiu);

                    if (! $includeAll && $marcaj === null) {
                        continue;
                    }

                    $this->writeCsvRow(
                        $handle,
                        $this->buildSpatiuCsvRow(
                            $spatiu,
                            $imobil,
                            $columnFieldsUnion,
                            $editableFields,
                            $exportDate,
                            true,
                            $marcaj,
                        )
                    );
                }
            });

        fclose($handle);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Imobil>
     */
    private function imobileWithOrderedSpatii()
    {
        return Imobil::query()
            ->orderBy('ordine')
            ->orderBy('id')
            ->with([
                'spatii' => fn ($query) => $query
                    ->with(['locatorEntitate', 'configurareAnexa'])
                    ->orderBy('ordine')
                    ->orderBy('id'),
            ])
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Imobil>  $imobile
     * @return list<string>
     */
    private function editableFieldsUnionFromImobile($imobile): array
    {
        $usedFields = $imobile
            ->flatMap(fn (Imobil $imobil): array => $this->editableSpatiuFieldsForImobil($imobil))
            ->unique()
            ->all();

        return collect(array_keys(Imobil::CAMPURI_SPATIU_CONFIGURABILE))
            ->reject(fn (string $field): bool => in_array($field, self::SPATIU_CAMPURI_DOAR_CITIRE, true))
            ->filter(fn (string $field): bool => in_array($field, $usedFields, true))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function allSpatiiEditableFieldsUnion(): array
    {
        return $this->editableFieldsUnionFromImobile($this->imobileWithOrderedSpatii());
    }

    /**
     * @param  list<string>  $columnFields
     * @return list<string>
     */
    private function allSpatiiCsvHeaders(array $columnFields): array
    {
        return [
            'ID imobil',
            'Imobil',
            'Localitate',
            ...$this->spatiuCsvContentHeaders($columnFields),
            'Detaliu de lamurit',
            'Data export',
        ];
    }

    /**
     * @param  list<string>  $columnFields
     * @return list<string>
     */
    private function filteredAllSpatiiCsvHeaders(array $columnFields): array
    {
        return [
            'Marcaj',
            ...$this->allSpatiiCsvHeaders($columnFields),
        ];
    }

    private function marcajExportLabel(Spatiu $spatiu): ?string
    {
        if ($spatiu->de_lamurit) {
            return 'De lamurit';
        }

        if ($spatiu->marcat_galben) {
            return 'Galben';
        }

        if ($spatiu->marcat_verde) {
            return 'Verde';
        }

        return null;
    }

    private function isSpatiuFaraAnexaInchiriat(Spatiu $spatiu): bool
    {
        return $spatiu->status === 'inchiriat' && $spatiu->configurare_anexa_id === null;
    }

    /**
     * @param  list<string>  $columnFields
     * @param  list<string>  $imobilEditableFields
     * @return list<mixed>
     */
    private function buildSpatiuCsvRow(
        Spatiu $spatiu,
        Imobil $imobil,
        array $columnFields,
        array $imobilEditableFields,
        string $exportDate,
        bool $includeImobilColumns = false,
        ?string $marcaj = null,
    ): array {
        $row = [];

        if ($marcaj !== null) {
            $row[] = $marcaj;
        }

        if ($includeImobilColumns) {
            $row[] = $imobil->id;
            $row[] = $imobil->nume;
            $row[] = $imobil->localitate ?? '';
        }

        $row[] = $spatiu->identificator;
        $row[] = $this->statusLabel($spatiu->status);

        foreach ($columnFields as $field) {
            $row[] = in_array($field, $imobilEditableFields, true)
                ? $this->spatiuFieldExportValue($spatiu, $field)
                : '';
        }

        if ($includeImobilColumns) {
            $row[] = $spatiu->de_lamurit ? ($spatiu->de_lamurit_detaliu ?? '') : '';
        }

        $row[] = $exportDate;

        return $row;
    }

    private function exportImobileCsv(string $targetPath, string $exportDate): void
    {
        $handle = $this->openCsvWriter($targetPath);

        $this->writeCsvRow($handle, [
            ...array_values(Imobil::CAMPURI_FORMULAR),
            'Data export',
        ]);

        Imobil::query()
            ->orderBy('ordine')
            ->orderBy('id')
            ->get()
            ->each(function (Imobil $imobil) use ($handle, $exportDate): void {
                $this->writeCsvRow($handle, [
                    $imobil->nume,
                    $imobil->strada,
                    $imobil->numar,
                    $imobil->localitate,
                    $imobil->judet,
                    $imobil->cod_postal,
                    $this->formatNumereCfForExport($imobil),
                    $this->formatCampuriSpatiuVizibileForExport($imobil),
                    $imobil->observatii,
                    $exportDate,
                ]);
            });

        fclose($handle);
    }

    private function formatNumereCfForExport(Imobil $imobil): string
    {
        return collect($imobil->numere_cf ?? [])
            ->map(function (mixed $cf): string {
                if (! is_array($cf)) {
                    return '';
                }

                $numar = trim((string) ($cf['numar'] ?? ''));
                $observatii = trim((string) ($cf['observatii'] ?? ''));

                if ($numar === '') {
                    return $observatii;
                }

                return $observatii !== '' ? "{$numar} ({$observatii})" : $numar;
            })
            ->filter()
            ->implode(' | ');
    }

    private function formatCampuriSpatiuVizibileForExport(Imobil $imobil): string
    {
        return collect($imobil->campuriSpatiuVizibilePentruForm())
            ->map(fn (string $field): string => Imobil::CAMPURI_SPATIU_CONFIGURABILE[$field] ?? $field)
            ->implode(' | ');
    }

    /**
     * @return list<string>
     */
    private function editableSpatiuFieldsForImobil(Imobil $imobil): array
    {
        return collect($imobil->campuriSpatiuVizibilePentruForm())
            ->reject(fn (string $field): bool => in_array($field, self::SPATIU_CAMPURI_DOAR_CITIRE, true))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $editableFields
     * @return list<string>
     */
    private function spatiuCsvContentHeaders(array $editableFields): array
    {
        $headers = [
            'Identificat la locator cu numarul',
            'Status',
        ];

        foreach ($editableFields as $field) {
            $headers[] = $this->spatiuFieldHeader($field);
        }

        return $headers;
    }

    /**
     * @param  list<string>  $editableFields
     * @return list<string>
     */
    private function spatiuCsvHeaders(array $editableFields): array
    {
        return [
            ...$this->spatiuCsvContentHeaders($editableFields),
            'Data export',
        ];
    }

    private function spatiuFieldHeader(string $field): string
    {
        return Imobil::CAMPURI_SPATIU_CONFIGURABILE[$field] ?? $field;
    }

    private function spatiuFieldExportValue(Spatiu $spatiu, string $field): mixed
    {
        return match ($field) {
            'suprafata_contractuala_mp' => $spatiu->suprafata_contractuala_mp,
            'corp' => $spatiu->corp,
            'etaj' => $spatiu->etaj,
            'pret_lunar' => $spatiu->pret_lunar,
            'indexare_2026' => $spatiu->indexare_2026,
            'regim_incalzire' => $this->regimIncalzireLabel($spatiu->regim_incalzire),
            'procent_incalzire_override' => $spatiu->procent_incalzire_override,
            'locator_id' => $spatiu->locatorEntitate?->nume ?: ($spatiu->getAttribute('locator') ?? ''),
            'configurare_anexa_id' => $spatiu->configurareAnexa?->denumire ?? '',
            'chirias' => $spatiu->chirias,
            'observatii' => $spatiu->observatii,
            default => '',
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'liber' => 'Liber',
            'rezervat' => 'Rezervat',
            'inchiriat' => 'Inchiriat',
            'comun' => 'Spatiu comun',
            'administrativ' => 'Administrativ',
            default => $status ?? '',
        };
    }

    private function regimIncalzireLabel(?string $regim): string
    {
        return match ($regim) {
            'integral' => 'Incalzit integral',
            'partial' => 'Incalzire partiala',
            'neincalzit' => 'Neincalzit',
            'manual' => 'Exceptie',
            default => $regim ?? '',
        };
    }

    /**
     * @param  resource  $handle
     */
    private function writeChiriasRow($handle, Spatiu $spatiu, Imobil $imobil, string $exportDate): void
    {
        if (! filled($spatiu->chirias) && $spatiu->status !== 'inchiriat') {
            return;
        }

        $suprafata = $spatiu->suprafata_contractuala_mp;
        $chirieCurenta = $spatiu->indexare_2026 ?: $spatiu->pret_lunar;
        $sursaChirieCurenta = $spatiu->indexare_2026
            ? 'Indexare 2026'
            : 'Chirie lunara';
        $pretMpCurent = $suprafata && $chirieCurenta
            ? number_format((float) $chirieCurenta / (float) $suprafata, 2, '.', '')
            : '';
        $locator = $spatiu->locatorEntitate?->nume ?: ($spatiu->getAttribute('locator') ?? '');

        $this->writeCsvRow($handle, [
            $imobil->nume,
            $imobil->localitate ?? '',
            $spatiu->identificator,
            $spatiu->chirias ?? '',
            $locator,
            $spatiu->pret_lunar ?? '',
            $chirieCurenta ?? '',
            $sursaChirieCurenta,
            $spatiu->indexare_2026 ?? '',
            $pretMpCurent,
            $exportDate,
        ]);
    }

    private function prepareSpatiiExportDirectory(string $directory): string
    {
        $spatiiDirectory = $directory.DIRECTORY_SEPARATOR.'spatii';

        if (File::isDirectory($spatiiDirectory)) {
            File::deleteDirectory($spatiiDirectory);
        }

        File::makeDirectory($spatiiDirectory, 0755, true);

        $legacyPath = $directory.DIRECTORY_SEPARATOR.'spatii.csv';

        if (File::exists($legacyPath)) {
            File::delete($legacyPath);
        }

        return $spatiiDirectory;
    }

    private function spatiiCsvFilename(Imobil $imobil): string
    {
        $slug = Str::slug($imobil->nume ?: ('imobil-'.$imobil->id));

        return $imobil->id.'-'.($slug !== '' ? $slug : 'imobil').'.csv';
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<array{imobil_id: int|null, imobil: string, filename: string, url: string, size: int}>
     */
    private function listSpatiiFilesForBackup(string $directory, string $date, array $manifest): array
    {
        $spatiiDirectory = $directory.DIRECTORY_SEPARATOR.'spatii';

        if (File::isDirectory($spatiiDirectory)) {
            $manifestFiles = collect($manifest['spatii_files'] ?? [])
                ->keyBy('filename');

            return collect(File::files($spatiiDirectory))
                ->filter(fn (\SplFileInfo $file): bool => str_ends_with(strtolower($file->getFilename()), '.csv'))
                ->map(function (\SplFileInfo $file) use ($date, $manifestFiles): array {
                    $filename = $file->getFilename();
                    $meta = $manifestFiles->get($filename, []);

                    return [
                        'imobil_id' => $meta['imobil_id'] ?? null,
                        'imobil' => $meta['imobil'] ?? pathinfo($filename, PATHINFO_FILENAME),
                        'filename' => $filename,
                        'url' => route('backup.download.spatii', ['date' => $date, 'file' => $filename]),
                        'size' => $file->getSize(),
                    ];
                })
                ->sortBy('imobil')
                ->values()
                ->all();
        }

        $legacyPath = $directory.DIRECTORY_SEPARATOR.'spatii.csv';

        if (! File::exists($legacyPath)) {
            return [];
        }

        return [[
            'imobil_id' => null,
            'imobil' => 'Toate imobilele',
            'filename' => 'spatii.csv',
            'url' => route('backup.download.spatii', ['date' => $date, 'file' => 'spatii.csv']),
            'size' => File::size($legacyPath),
        ]];
    }

    /**
     * @return resource
     */
    private function openCsvWriter(string $targetPath)
    {
        $handle = fopen($targetPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Could not create CSV backup file.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fwrite($handle, "sep=;\r\n");

        return $handle;
    }

    /**
     * @param  resource  $handle
     * @param  list<mixed>  $row
     */
    private function writeCsvRow($handle, array $row): void
    {
        fputcsv(
            $handle,
            array_map(fn (mixed $value): string => $this->formatCsvCell($value), $row),
            ';',
            '"',
            '\\'
        );
    }

    private function formatCsvCell(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $string = trim((string) $value);

        if ($string === '') {
            return '';
        }

        return $this->removeDiacritics($string);
    }

    private function removeDiacritics(string $value): string
    {
        return Str::ascii($value);
    }

    private function readManifest(string $directory): array
    {
        $manifestPath = $directory.DIRECTORY_SEPARATOR.'manifest.json';

        if (! File::exists($manifestPath)) {
            return [];
        }

        $decoded = json_decode(File::get($manifestPath), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function findDatabaseFile(string $directory): ?string
    {
        foreach (['database.sqlite', 'database.sql'] as $filename) {
            $path = $directory.DIRECTORY_SEPARATOR.$filename;

            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
