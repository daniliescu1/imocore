<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(BackupService $backupService): Response
    {
        $backupService->ensureAutomaticBackupForToday();

        return Inertia::render('Backup/Index', [
            'backups' => $backupService->listBackups(),
            'manualBackup' => $backupService->manualBackup(),
            'retentionDays' => BackupService::RETENTION_DAYS,
            'latestBackupAt' => $backupService->latestBackupCreatedAt(),
            'nextScheduledAt' => $backupService->nextAutomaticBackupAt(),
            'allSpatiiDownloadUrl' => route('backup.download.spatii-toate'),
            'indexareChiriiDownloadUrl' => route('backup.download.indexare-chirii'),
            'persoaneDeclarateDownloadUrl' => route('backup.download.persoane-declarate'),
        ]);
    }

    public function store(BackupService $backupService): RedirectResponse
    {
        $backupService->runBackup('manual');

        return redirect()
            ->route('backup.index')
            ->with('success', 'Backup-ul manual a fost creat.');
    }

    public function download(string $date, string $type, BackupService $backupService): BinaryFileResponse
    {
        $path = $backupService->resolveDownloadPath($date, $type);
        $filename = $backupService->downloadFilename($date, $type);

        if (str_ends_with($path, '.sql')) {
            $filename = 'imocore-database-'.$backupService->downloadDateLabel($date).'.sql';
        }

        return response()->download($path, $filename, [
            'Content-Type' => File::mimeType($path) ?: 'application/octet-stream',
        ]);
    }

    public function downloadSpatii(string $date, string $file, BackupService $backupService): BinaryFileResponse
    {
        $path = $backupService->resolveSpatiiDownloadPath($date, $file);
        $filename = $backupService->spatiiDownloadFilename($date, $file);

        return response()->download($path, $filename, [
            'Content-Type' => File::mimeType($path) ?: 'text/csv',
        ]);
    }

    public function downloadAllSpatii(BackupService $backupService): BinaryFileResponse
    {
        $tempDirectory = storage_path('app/temp');

        if (! File::isDirectory($tempDirectory)) {
            File::makeDirectory($tempDirectory, 0755, true);
        }

        $tempPath = $tempDirectory.DIRECTORY_SEPARATOR.'spatii-toate-'.Str::uuid().'.xlsx';
        $backupService->exportAllSpatiiXlsx($tempPath, now()->format('Y-m-d H:i:s'));

        return response()->download($tempPath, $backupService->onDemandAllSpatiiDownloadFilename(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function downloadIndexareChirii(BackupService $backupService): BinaryFileResponse
    {
        $tempDirectory = storage_path('app/temp');

        if (! File::isDirectory($tempDirectory)) {
            File::makeDirectory($tempDirectory, 0755, true);
        }

        $tempPath = $tempDirectory.DIRECTORY_SEPARATOR.'indexare-chirii-'.Str::uuid().'.csv';
        $backupService->exportIndexareChiriiCsv($tempPath, now()->format('Y-m-d H:i:s'));

        return response()->download($tempPath, $backupService->onDemandIndexareChiriiDownloadFilename(), [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend();
    }

    public function downloadPersoaneDeclarate(BackupService $backupService): BinaryFileResponse
    {
        $tempDirectory = storage_path('app/temp');

        if (! File::isDirectory($tempDirectory)) {
            File::makeDirectory($tempDirectory, 0755, true);
        }

        $tempPath = $tempDirectory.DIRECTORY_SEPARATOR.'persoane-declarate-'.Str::uuid().'.csv';
        $backupService->exportPersoaneDeclarateCsv($tempPath, now()->format('Y-m-d H:i:s'));

        return response()->download($tempPath, $backupService->onDemandPersoaneDeclarateDownloadFilename(), [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend();
    }

    public function runScheduled(BackupService $backupService): HttpResponse
    {
        $token = (string) config('services.backup.cron_token', '');

        abort_unless($token !== '' && hash_equals($token, (string) request('token')), 403);

        $created = $backupService->ensureAutomaticBackupForToday();

        return response($created ? 'backup-created' : 'backup-current', 200);
    }
}
