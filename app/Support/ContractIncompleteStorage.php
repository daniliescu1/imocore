<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class ContractIncompleteStorage
{
    public const NUMAR_PLACEHOLDER = 'Incomplet';

    public const CHIRIAS_PLACEHOLDER = '—';

    public const DATE_PLACEHOLDER = '1970-01-01';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalizeForStorage(array $payload, string $status): array
    {
        if ($status !== 'incomplet') {
            return $payload;
        }

        if (! filled($payload['numar_contract'] ?? null)) {
            $payload['numar_contract'] = self::NUMAR_PLACEHOLDER;
        }

        if (! filled($payload['chirias'] ?? null)) {
            $payload['chirias'] = self::CHIRIAS_PLACEHOLDER;
        }

        if (blank($payload['data_start'] ?? null)) {
            $payload['data_start'] = self::DATE_PLACEHOLDER;
        }

        return $payload;
    }

    public static function displayNumarContract(?string $value): string
    {
        return $value === self::NUMAR_PLACEHOLDER ? '' : ($value ?? '');
    }

    public static function displayChirias(?string $value): string
    {
        return $value === self::CHIRIAS_PLACEHOLDER ? '' : ($value ?? '');
    }

    public static function displayDate(?Carbon $value): string
    {
        if (! $value) {
            return '';
        }

        $formatted = $value->format('Y-m-d');

        return $formatted === self::DATE_PLACEHOLDER ? '' : $formatted;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeInputForCompleteness(array $input): array
    {
        if (($input['numar_contract'] ?? null) === self::NUMAR_PLACEHOLDER) {
            $input['numar_contract'] = '';
        }

        if (($input['chirias_pf']['nume_complet'] ?? null) === self::CHIRIAS_PLACEHOLDER) {
            $input['chirias_pf']['nume_complet'] = '';
        }

        if (($input['chirias_pj']['denumire'] ?? null) === self::CHIRIAS_PLACEHOLDER) {
            $input['chirias_pj']['denumire'] = '';
        }

        if (($input['data_start'] ?? null) === self::DATE_PLACEHOLDER) {
            $input['data_start'] = '';
        }

        return $input;
    }
}
