<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(BackupService $backupService): Response
    {
        $backups = $backupService->listBackups();
        $latest = $backups[0] ?? null;

        return Inertia::render('Backup/Index', [
            'backups' => $backups,
            'retentionDays' => BackupService::RETENTION_DAYS,
            'latestBackupAt' => $latest['created_at'] ?? null,
            'nextScheduledAt' => now()->addDay()->startOfDay()->addHours(3)->toIso8601String(),
        ]);
    }

    public function store(BackupService $backupService): RedirectResponse
    {
        $backupService->runBackup('manual');

        return redirect()
            ->route('backup.index')
            ->with('success', 'Backup-ul a fost creat.');
    }

    public function download(string $date, string $type, BackupService $backupService): BinaryFileResponse
    {
        $path = $backupService->resolveDownloadPath($date, $type);
        $filename = $backupService->downloadFilename($date, $type);

        if (str_ends_with($path, '.sql')) {
            $filename = "imocore-database-{$date}.sql";
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
}
