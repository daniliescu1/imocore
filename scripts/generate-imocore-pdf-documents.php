<?php

declare(strict_types=1);

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function countLinesInDir(string $dir, array $extensions): array
{
    if (! is_dir($dir)) {
        return ['files' => 0, 'lines' => 0];
    }

    $files = 0;
    $lines = 0;

    foreach ($extensions as $ext) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== $ext) {
                continue;
            }
            $files++;
            $content = file($file->getPathname());
            $lines += $content ? count($content) : 0;
        }
    }

    return ['files' => $files, 'lines' => $lines];
}

function gitFirstCommitDate(): string
{
    $out = shell_exec('git log --reverse --format=%ai -1 2>nul');

    return trim($out ?: now()->format('Y-m-d'));
}

function gitLastCommitDate(): string
{
    $out = shell_exec('git log -1 --format=%ai 2>nul');

    return trim($out ?: now()->format('Y-m-d'));
}

function gitCommitCount(): int
{
    $out = shell_exec('git rev-list --count HEAD 2>nul');

    return (int) trim($out ?: '0');
}

$appStats = countLinesInDir(base_path('app'), ['php']);
$jsStats = ['files' => 0, 'lines' => 0];
foreach (['js', 'jsx'] as $ext) {
    $part = countLinesInDir(base_path('resources/js'), [$ext]);
    $jsStats['files'] += $part['files'];
    $jsStats['lines'] += $part['lines'];
}
$cssStats = countLinesInDir(base_path('resources/css'), ['css']);
$viewsStats = countLinesInDir(base_path('resources/views'), ['php']);
$routesStats = countLinesInDir(base_path('routes'), ['php']);
$testsStats = countLinesInDir(base_path('tests'), ['php']);
$migrationsPath = base_path('database/migrations');
$migrationsCount = is_dir($migrationsPath) ? count(glob($migrationsPath.'/*.php')) : 0;
$migrationsStats = countLinesInDir($migrationsPath, ['php']);

$appTotal = $appStats['lines'] + $jsStats['lines'] + $cssStats['lines'] + $viewsStats['lines'] + $routesStats['lines'];
$grandTotal = $appTotal + $testsStats['lines'] + $migrationsStats['lines'];

$models = collect(File::files(app_path('Models')))
    ->map(fn ($f) => pathinfo($f->getFilename(), PATHINFO_FILENAME))
    ->sort()->values()->all();

$controllers = collect(File::files(app_path('Http/Controllers')))
    ->map(fn ($f) => pathinfo($f->getFilename(), PATHINFO_FILENAME))
    ->sort()->values()->all();

$supportClasses = collect(File::files(app_path('Support')))
    ->map(fn ($f) => pathinfo($f->getFilename(), PATHINFO_FILENAME))
    ->sort()->values()->all();

$jsxPages = count(glob(base_path('resources/js/Pages/**/*.jsx'), GLOB_BRACE) ?: []);
if ($jsxPages === 0) {
    $jsxPages = count(File::allFiles(base_path('resources/js/Pages')));
}

$featureTestsCount = count(glob(base_path('tests/Feature/*.php')) ?: []);
$unitTestsCount = count(glob(base_path('tests/Unit/*.php')) ?: []);

$customSourceDirs = [
    base_path('app'),
    base_path('resources/js'),
    base_path('resources/css'),
    base_path('resources/views'),
    base_path('routes'),
    base_path('tests'),
    base_path('database/migrations'),
    base_path('scripts'),
];
$customFilesCount = 0;
foreach ($customSourceDirs as $dir) {
    if (! is_dir($dir)) {
        continue;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ['php', 'jsx', 'js', 'css', 'blade.php'], true)) {
            $customFilesCount++;
        }
    }
}

$composer = json_decode(file_get_contents(base_path('composer.json')), true);
$package = json_decode(file_get_contents(base_path('package.json')), true);

$data = [
    'generatedAt' => now()->format('d.m.Y H:i'),
    'phpVersion' => PHP_VERSION,
    'laravelVersion' => app()->version(),
    'reactVersion' => '^'.($package['devDependencies']['react'] ?? '19'),
    'inertiaVersion' => '^'.($package['devDependencies']['@inertiajs/react'] ?? '3'),
    'viteVersion' => '^'.($package['devDependencies']['vite'] ?? '5'),
    'featureTestsCount' => $featureTestsCount,
    'unitTestsCount' => $unitTestsCount,
    'models' => $models,
    'controllers' => $controllers,
    'supportClasses' => $supportClasses,
    'stats' => [
        'app_files' => $appStats['files'],
        'app_lines' => $appStats['lines'],
        'js_files' => $jsStats['files'],
        'js_lines' => $jsStats['lines'],
        'css_files' => $cssStats['files'],
        'css_lines' => $cssStats['lines'],
        'views_files' => $viewsStats['files'],
        'views_lines' => $viewsStats['lines'],
        'routes_files' => $routesStats['files'],
        'routes_lines' => $routesStats['lines'],
        'app_total_lines' => $appTotal,
        'tests_files' => $testsStats['files'],
        'tests_lines' => $testsStats['lines'],
        'migrations_count' => $migrationsCount,
        'migrations_lines' => $migrationsStats['lines'],
        'grand_total_lines' => $grandTotal,
        'models_count' => count($models),
        'controllers_count' => count($controllers),
        'support_count' => count($supportClasses),
        'jsx_pages' => $jsxPages,
        'feature_tests_count' => $featureTestsCount,
        'unit_tests_count' => $unitTestsCount,
        'tests_total_count' => $featureTestsCount + $unitTestsCount,
        'custom_files_count' => $customFilesCount,
        'commits_count' => gitCommitCount(),
        'first_commit_date' => gitFirstCommitDate(),
        'last_commit_date' => gitLastCommitDate(),
    ],
];

$outputDir = base_path('docs/pdf');
File::ensureDirectoryExists($outputDir);

$prdPath = $outputDir.'/IMOCORE-PRD-'.now()->format('Y-m-d').'.pdf';
$copyrightPath = $outputDir.'/IMOCORE-Drepturi-Autor-Dan-Iliescu-'.now()->format('Y-m-d').'.pdf';

Pdf::loadView('documents.prd-imocore', $data)
    ->setPaper('a4', 'portrait')
    ->save($prdPath);

Pdf::loadView('documents.drepturi-autor-imocore', $data)
    ->setPaper('a4', 'portrait')
    ->save($copyrightPath);

echo "PDF generat: {$prdPath}\n";
echo "PDF generat: {$copyrightPath}\n";
