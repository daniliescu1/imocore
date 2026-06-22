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

    public static function normalizeDateValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $trimmed, $matches)) {
            return $matches[3].'-'.str_pad($matches[2], 2, '0', STR_PAD_LEFT).'-'.str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        }

        return $trimmed;
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

        foreach (['data_start', 'data_end'] as $field) {
            if (array_key_exists($field, $input)) {
                $input[$field] = self::normalizeDateValue($input[$field]);
            }
        }

        if (isset($input['chirias_pf']) && is_array($input['chirias_pf'])) {
            $input['chirias_pf'] = self::normalizeChiriasGroup($input['chirias_pf']);
        }

        if (isset($input['chirias_pj']) && is_array($input['chirias_pj'])) {
            $input['chirias_pj'] = self::normalizeChiriasGroup($input['chirias_pj']);

            if (blank($input['chirias_pj']['administrator_2']['nume_complet'] ?? null)) {
                unset($input['chirias_pj']['administrator_2']);
            }
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function normalizeChiriasGroup(array $input): array
    {
        foreach ($input as $key => $value) {
            if ($key === 'cnp') {
                $input[$key] = self::normalizeCnpValue(is_string($value) ? $value : null);

                continue;
            }

            if (is_array($value)) {
                $input[$key] = self::normalizeChiriasGroup($value);
            }
        }

        return $input;
    }

    public static function normalizeCnpValue(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) > 13) {
            $digits = substr($digits, -13);
        }

        return $digits;
    }
}
