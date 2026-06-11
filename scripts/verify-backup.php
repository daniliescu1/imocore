<?php

use App\Services\BackupService;
use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Support\Facades\File;
use Tests\Support\BackupExportValidator;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$date = is_dir(storage_path('app/backups/manual'))
    ? 'manual'
    : now()->format('Y-m-d');
$dir = storage_path("app/backups/{$date}");

if (! is_dir($dir)) {
    fwrite(STDERR, "Backup directory missing: {$dir}\n");
    exit(1);
}

echo "Verificare backup {$date}\n\n";

$imobileDb = Imobil::count();
$spatiiDb = Spatiu::count();
$chiriasiDb = Spatiu::query()
    ->where(fn ($q) => $q->where('status', 'inchiriat')->orWhereNotNull('chirias')->where('chirias', '!=', ''))
    ->count();

$imobileCsv = BackupExportValidator::parseCsvFile($dir.'/imobile.csv');
$chiriasiCsv = BackupExportValidator::parseCsvFile($dir.'/chiriasi.csv');
$spatiiFiles = collect(File::files($dir.'/spatii'))->filter(fn ($f) => str_ends_with($f->getFilename(), '.csv'));
$spatiiRows = $spatiiFiles->sum(fn ($f) => count(BackupExportValidator::parseCsvFile($f->getPathname())['rows']));

$spatiiToatePath = $dir.'/'.BackupService::ALL_SPATII_CSV_FILENAME;
$spatiiToateRows = is_file($spatiiToatePath)
    ? count(BackupExportValidator::parseCsvFile($spatiiToatePath)['rows'])
    : 0;

echo "Imobile DB: {$imobileDb} | CSV: ".count($imobileCsv['rows'])."\n";
echo "Spatii DB: {$spatiiDb} | CSV total: {$spatiiRows} (".count($spatiiFiles)." fisiere) | Toate: {$spatiiToateRows}\n";
echo "Chiriasi DB: {$chiriasiDb} | CSV: ".count($chiriasiCsv['rows'])."\n";

$source = config('database.connections.sqlite.database');
$mismatches = BackupExportValidator::compareDatabaseCounts($source, $dir.'/database.sqlite', [
    'imobile', 'spatii', 'locatori', 'configurari_anexe_imobil',
]);

echo 'SQLite tabele OK: '.(empty($mismatches) ? 'da' : 'nu')."\n";

if ($mismatches) {
    print_r($mismatches);
    exit(1);
}

foreach ([$dir.'/imobile.csv', $dir.'/chiriasi.csv', $spatiiToatePath, ...$spatiiFiles->map->getPathname()->all()] as $file) {
    if (! is_file($file)) {
        continue;
    }
    $ok = BackupExportValidator::assertNoDiacritics(File::get($file));
    echo basename($file).': diacritice '.($ok ? 'OK' : 'GASITE')."\n";
}

echo "\nVerificare completa.\n";
