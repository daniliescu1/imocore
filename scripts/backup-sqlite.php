<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__);
$sourcePath = $rootPath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite';
$backupDirectory = $rootPath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'backups';

if (! is_file($sourcePath)) {
    fwrite(STDERR, "SQLite database not found: {$sourcePath}" . PHP_EOL);
    exit(1);
}

if (! is_dir($backupDirectory) && ! mkdir($backupDirectory, 0755, true) && ! is_dir($backupDirectory)) {
    fwrite(STDERR, "Could not create backup directory: {$backupDirectory}" . PHP_EOL);
    exit(1);
}

$backupPath = $backupDirectory . DIRECTORY_SEPARATOR . 'database-' . date('Ymd-His') . '.sqlite';

if (! copy($sourcePath, $backupPath)) {
    fwrite(STDERR, "Could not copy database backup to: {$backupPath}" . PHP_EOL);
    exit(1);
}

echo "SQLite database backup created: {$backupPath}" . PHP_EOL;
