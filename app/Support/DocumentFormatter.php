<?php

namespace App\Support;

class DocumentFormatter
{
    public static function decimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $formatted = (string) (float) $value;

        return preg_replace(['/(\.\d*?)0+$/', '/\.$/'], ['$1', ''], $formatted) ?: '0';
    }

    public static function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 2, '.', '').' lei';
    }

    public static function moneyValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 2, '.', '');
    }

    public static function amount(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 2, ',', '.');
    }

    public static function display(mixed $value): string
    {
        if ($value === null || $value === '' || trim((string) $value) === '') {
            return '—';
        }

        return (string) $value;
    }

    public static function faraDiacritice(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $map = [
            'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
            'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ş' => 'S', 'Ț' => 'T', 'Ţ' => 'T',
        ];

        return strtr($text, $map);
    }

    public static function pdfText(mixed $value): string
    {
        $display = self::display($value);

        if ($display === '—') {
            return $display;
        }

        return self::faraDiacritice($display);
    }

    public static function lunaText(?string $luna): string
    {
        $luni = [
            '01' => 'Ianuarie',
            '02' => 'Februarie',
            '03' => 'Martie',
            '04' => 'Aprilie',
            '05' => 'Mai',
            '06' => 'Iunie',
            '07' => 'Iulie',
            '08' => 'August',
            '09' => 'Septembrie',
            '10' => 'Octombrie',
            '11' => 'Noiembrie',
            '12' => 'Decembrie',
        ];

        [$year, $month] = array_pad(explode('-', (string) $luna, 2), 2, null);

        if (! $year || ! $month) {
            return '—';
        }

        return trim("{$month} ".($luni[$month] ?? '')." {$year}");
    }

    public static function perioadaCitireDefault(?string $luna): string
    {
        if (! $luna) {
            return '—';
        }

        $numarLuna = substr($luna, -2);
        $an = substr($luna, 0, 4);

        return "20.{$numarLuna}.{$an} - 25.{$numarLuna}.{$an}";
    }

    public static function safeFilename(string $name, string $fallback, string $extension): string
    {
        $base = trim(preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', $name) ?? '', '-');

        if ($base === '') {
            $base = $fallback;
        }

        return $base.'.'.$extension;
    }

    public static function facturaDownloadFilename(string $numeFirma, ?string $dataEmitere): string
    {
        $firma = self::numeFirmaPentruFisier($numeFirma);
        $data = self::formatDataFisier($dataEmitere);

        return self::safeFilename("Factura {$firma} {$data}", 'factura', 'pdf');
    }

    public static function anexaDownloadFilename(string $numeFirma, ?string $luna): string
    {
        $firma = self::numeFirmaPentruFisier($numeFirma);
        $lunaLabel = self::lunaFisier($luna);

        return self::safeFilename("Anexa {$firma} {$lunaLabel}", 'anexa', 'pdf');
    }

    public static function lunaFisier(?string $luna): string
    {
        $luni = [
            '01' => 'Ianuarie',
            '02' => 'Februarie',
            '03' => 'Martie',
            '04' => 'Aprilie',
            '05' => 'Mai',
            '06' => 'Iunie',
            '07' => 'Iulie',
            '08' => 'August',
            '09' => 'Septembrie',
            '10' => 'Octombrie',
            '11' => 'Noiembrie',
            '12' => 'Decembrie',
        ];

        [$year, $month] = array_pad(explode('-', (string) $luna, 2), 2, null);

        if (! $year || ! $month) {
            return 'fara-luna';
        }

        return ($luni[$month] ?? $month).'-'.$year;
    }

    private static function numeFirmaPentruFisier(string $numeFirma): string
    {
        $nume = trim($numeFirma);

        if ($nume === '' || $nume === '—') {
            return 'firma';
        }

        return $nume;
    }

    private static function formatDataFisier(?string $data): string
    {
        if ($data === null || trim($data) === '') {
            return 'fara-data';
        }

        try {
            return \Carbon\Carbon::parse($data)->format('d-m-Y');
        } catch (\Throwable) {
            return 'fara-data';
        }
    }
}
