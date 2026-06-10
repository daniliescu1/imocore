<?php

namespace Tests\Support;

use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

class BackupExportValidator
{
    /**
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    public static function parseCsvFile(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("CSV file not found: {$path}");
        }

        $content = file_get_contents($path);
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $lines = array_values(array_filter(array_map(function (string $line): string {
            return preg_replace('/^\xEF\xBB\xBF/u', '', trim($line)) ?? trim($line);
        }, $lines), fn (string $line): bool => $line !== ''));

        while ($lines !== [] && str_contains($lines[0], 'sep=')) {
            array_shift($lines);
        }

        if ($lines === []) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = str_getcsv(array_shift($lines), ';', '"', '\\');

        return [
            'headers' => $headers,
            'rows' => array_map(
                fn (string $line): array => str_getcsv($line, ';', '"', '\\'),
                array_filter($lines, fn (string $line): bool => trim($line) !== '')
            ),
        ];
    }

    public static function ascii(mixed $value): string
    {
        return Str::ascii(trim((string) ($value ?? '')));
    }

    public static function csvDecimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $string = trim((string) $value);

        if ($string === '') {
            return '';
        }

        if (is_numeric($string) && str_contains($string, '.')) {
            return str_replace('.', ',', $string);
        }

        return $string;
    }

    /**
     * @return list<string>
     */
    public static function expectedImobileHeaders(): array
    {
        return [
            ...array_map(fn (string $label): string => self::ascii($label), array_values(Imobil::CAMPURI_FORMULAR)),
            'Data export',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function expectedImobilRow(Imobil $imobil, string $exportDate): array
    {
        return [
            'Nume imobil' => self::ascii($imobil->nume),
            'Strada' => self::ascii($imobil->strada),
            'Numar' => self::ascii($imobil->numar),
            'Localitate' => self::ascii($imobil->localitate),
            'Judet' => self::ascii($imobil->judet),
            'Cod postal' => self::ascii($imobil->cod_postal),
            'Numere CF' => self::ascii(collect($imobil->numere_cf ?? [])
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
                ->implode(' | ')),
            'Campuri vizibile formular spatiu' => self::ascii(collect($imobil->campuriSpatiuVizibilePentruForm())
                ->map(fn (string $field): string => Imobil::CAMPURI_SPATIU_CONFIGURABILE[$field] ?? $field)
                ->implode(' | ')),
            'Observatii' => self::ascii($imobil->observatii),
            'Data export' => $exportDate,
        ];
    }

    /**
     * @param  list<string>  $editableFields
     * @return list<string>
     */
    public static function expectedSpatiiHeaders(array $editableFields): array
    {
        $headers = [
            'Identificat la locator cu numarul',
            'Status',
        ];

        foreach ($editableFields as $field) {
            $headers[] = self::ascii(Imobil::CAMPURI_SPATIU_CONFIGURABILE[$field] ?? $field);
        }

        $headers[] = 'Data export';

        return $headers;
    }

    /**
     * @param  list<string>  $editableFields
     * @return array<string, string>
     */
    public static function expectedSpatiuRow(Spatiu $spatiu, array $editableFields, string $exportDate): array
    {
        $row = [
            'Identificat la locator cu numarul' => self::ascii($spatiu->identificator),
            'Status' => self::ascii(match ($spatiu->status) {
                'liber' => 'Liber',
                'rezervat' => 'Rezervat',
                'inchiriat' => 'Inchiriat',
                'comun' => 'Spatiu comun',
                'administrativ' => 'Administrativ',
                default => $spatiu->status ?? '',
            }),
        ];

        foreach ($editableFields as $field) {
            $label = self::ascii(Imobil::CAMPURI_SPATIU_CONFIGURABILE[$field] ?? $field);
            $row[$label] = self::ascii(match ($field) {
                'suprafata_contractuala_mp' => self::csvDecimal($spatiu->suprafata_contractuala_mp),
                'corp' => $spatiu->corp,
                'etaj' => $spatiu->etaj,
                'pret_lunar' => self::csvDecimal($spatiu->pret_lunar),
                'indexare_2025' => self::csvDecimal($spatiu->indexare_2025),
                'indexare_2026' => self::csvDecimal($spatiu->indexare_2026),
                'regim_incalzire' => match ($spatiu->regim_incalzire) {
                    'integral' => 'Incalzit integral',
                    'partial' => 'Incalzire partiala',
                    'neincalzit' => 'Neincalzit',
                    'manual' => 'Exceptie',
                    default => $spatiu->regim_incalzire ?? '',
                },
                'procent_incalzire_override' => self::csvDecimal($spatiu->procent_incalzire_override),
                'locator_id' => $spatiu->locatorEntitate?->nume ?: ($spatiu->getAttribute('locator') ?? ''),
                'configurare_anexa_id' => $spatiu->configurareAnexa?->denumire ?? '',
                'chirias' => $spatiu->chirias,
                'observatii' => $spatiu->observatii,
                default => '',
            });
        }

        $row['Data export'] = $exportDate;

        return $row;
    }

    /**
     * @return array<string, string>
     */
    public static function expectedChiriasRow(Spatiu $spatiu, Imobil $imobil, string $exportDate): array
    {
        $suprafata = $spatiu->suprafata_contractuala_mp;
        $chirieCurenta = $spatiu->indexare_2026 ?: ($spatiu->indexare_2025 ?: $spatiu->pret_lunar);
        $sursaChirieCurenta = $spatiu->indexare_2026
            ? 'Indexare 2026'
            : ($spatiu->indexare_2025 ? 'Indexare 2025' : 'Chirie lunara');
        $pretMpCurent = $suprafata && $chirieCurenta
            ? number_format((float) $chirieCurenta / (float) $suprafata, 2, '.', '')
            : '';

        return [
            'Imobil' => self::ascii($imobil->nume),
            'Localitate' => self::ascii($imobil->localitate),
            'Identificator spatiu' => self::ascii($spatiu->identificator),
            'Chirias' => self::ascii($spatiu->chirias),
            'Locator' => self::ascii($spatiu->locatorEntitate?->nume ?: ($spatiu->getAttribute('locator') ?? '')),
            'Chirie lunara EUR' => self::csvDecimal($spatiu->pret_lunar),
            'Chirie curenta EUR' => self::csvDecimal($chirieCurenta),
            'Sursa chirie curenta' => self::ascii($sursaChirieCurenta),
            'Indexare 2025' => self::csvDecimal($spatiu->indexare_2025),
            'Indexare 2026' => self::csvDecimal($spatiu->indexare_2026),
            'Pret mp curent' => self::csvDecimal($pretMpCurent),
            'Data export' => $exportDate,
        ];
    }

    /**
     * @param  array{headers: list<string>, rows: list<list<string>>}  $csv
     * @param  array<string, string>  $expected
     */
    public static function findMatchingRow(array $csv, array $expected): ?array
    {
        foreach ($csv['rows'] as $row) {
            $assoc = self::associateRow($csv['headers'], $row);
            $matches = true;

            foreach ($expected as $header => $value) {
                if (($assoc[$header] ?? null) !== $value) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return $assoc;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $row
     * @return array<string, string>
     */
    public static function associateRow(array $headers, array $row): array
    {
        $assoc = [];

        foreach ($headers as $index => $header) {
            $assoc[$header] = $row[$index] ?? '';
        }

        return $assoc;
    }

    public static function assertNoDiacritics(string $content): bool
    {
        return ! preg_match('/[ăâîșțĂÂÎȘȚ]/u', $content);
    }

    /**
     * @param  list<string>  $tables
     */
    public static function compareDatabaseCounts(string $sourcePath, string $backupPath, array $tables): array
    {
        $source = new PDO('sqlite:'.$sourcePath);
        $backup = new PDO('sqlite:'.$backupPath);
        $mismatches = [];

        foreach ($tables as $table) {
            $sourceCount = (int) $source->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            $backupCount = (int) $backup->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();

            if ($sourceCount !== $backupCount) {
                $mismatches[$table] = ['source' => $sourceCount, 'backup' => $backupCount];
            }
        }

        return $mismatches;
    }

    public static function spatiiCsvFilename(Imobil $imobil): string
    {
        $slug = Str::slug($imobil->nume ?: ('imobil-'.$imobil->id));

        return $imobil->id.'-'.($slug !== '' ? $slug : 'imobil').'.csv';
    }

    /**
     * @return list<string>
     */
    public static function editableSpatiuFieldsForImobil(Imobil $imobil): array
    {
        return collect($imobil->campuriSpatiuVizibilePentruForm())
            ->reject(fn (string $field): bool => in_array($field, ['persoane_standard', 'pret_mp_ultima_indexare'], true))
            ->values()
            ->all();
    }
}
