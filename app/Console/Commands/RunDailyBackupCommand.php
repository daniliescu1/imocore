<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class RunDailyBackupCommand extends Command
{
    protected $signature = 'backup:run-daily';

    protected $description = 'Create daily database and CSV backups, keeping the last 7 days.';

    public function handle(BackupService $backupService): int
    {
        $result = $backupService->runBackup('automatic');

        $this->info("Backup created for {$result['date']}.");
        $this->line('Database: '.$result['database']);
        $this->line('Spatii CSV files: '.count($result['spatii_files']));
        $this->line('Imobile CSV: '.$result['imobile_csv']);
        $this->line('Chiriasi CSV: '.$result['chiriasi_csv']);
        $this->line('Contracte CSV: '.$result['contracte_csv']);
        $this->line('Locatori CSV: '.$result['locatori_csv']);

        return self::SUCCESS;
    }
}
