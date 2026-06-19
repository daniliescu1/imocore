<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(BackupService $backupService): Response
    {
        return Inertia::render('Backup/Index', [
            'backups' => $backupService->listBackups(),
            'manualBackup' => $backupService->manualBackup(),
            'retentionDays' => BackupService::RETENTION_DAYS,
            'latestBackupAt' => $backupService->latestBackupCreatedAt(),
            'nextScheduledAt' => now()->addDay()->startOfDay()->addHours(3)->toIso8601String(),
            'allSpatiiDownloadUrl' => route('backup.download.spatii-toate'),
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

        $tempPath = $tempDirectory.DIRECTORY_SEPARATOR.'spatii-toate-'.Str::uuid().'.csv';
        $backupService->exportAllSpatiiCsv($tempPath, now()->format('Y-m-d H:i:s'));

        return response()->download($tempPath, $backupService->onDemandAllSpatiiDownloadFilename(), [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend();
    }
}
