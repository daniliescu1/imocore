<?php

namespace App\Support;

use Illuminate\Http\Request;

class ContractChiriasData
{
    /**
     * @return array{chirias_tip: string, chirias: string, chirias_date: array<string, mixed>}
     */
    public static function validateAndNormalize(Request $request): array
    {
        $tip = $request->string('chirias_tip')->toString();

        abort_unless(in_array($tip, ['pf', 'pj'], true), 422, 'Selectează tipul chiriașului.');

        if ($tip === 'pf') {
            return self::normalizePf($request);
        }

        return self::normalizePj($request);
    }

    /**
     * @return array{chirias_tip: string, chirias: ?string, chirias_date: array<string, mixed>}
     */
    public static function normalizeBestEffort(Request $request): array
    {
        $tip = $request->string('chirias_tip')->toString();
        $tip = in_array($tip, ['pf', 'pj'], true) ? $tip : 'pj';

        if ($tip === 'pf') {
            return self::normalizePfBestEffort($request);
        }

        return self::normalizePjBestEffort($request);
    }

    /**
     * @return array{chirias_tip: string, chirias: ?string, chirias_date: array<string, mixed>}
     */
    private static function normalizePfBestEffort(Request $request): array
    {
        $pf = self::trimStrings(array_merge(self::emptyPf(), $request->input('chirias_pf', [])));
        $nume = $pf['nume_complet'] ?: '';

        return [
            'chirias_tip' => 'pf',
            'chirias' => $nume,
            'chirias_date' => [
                'serie_ci' => $pf['serie_ci'] ?: null,
                'numar_ci' => $pf['numar_ci'] ?: null,
                'cnp' => $pf['cnp'] ?: null,
                'domiciliu' => $pf['domiciliu'] ?: null,
                'email' => $pf['email'] ?: null,
                'email_2' => ($pf['email_2'] ?? null) ?: null,
                'telefon' => $pf['telefon'] ?: null,
                'banca' => $pf['banca'] ?: null,
                'cont_bancar' => $pf['cont_bancar'] ?: null,
            ],
        ];
    }

    /**
     * @return array{chirias_tip: string, chirias: ?string, chirias_date: array<string, mixed>}
     */
    private static function normalizePjBestEffort(Request $request): array
    {
        $pjInput = $request->input('chirias_pj', []);
        $adminInput = is_array($pjInput['administrator'] ?? null) ? $pjInput['administrator'] : [];
        $admin2Input = is_array($pjInput['administrator_2'] ?? null) ? $pjInput['administrator_2'] : [];
        $pj = self::trimStrings(array_merge(self::emptyPj(), $pjInput));
        $admin = self::trimStrings(array_merge(self::emptyAdministrator(), $adminInput));
        $admin2 = self::trimStrings(array_merge(self::emptyAdministrator(), $admin2Input));
        $denumire = $pj['denumire'] ?: '';

        return [
            'chirias_tip' => 'pj',
            'chirias' => $denumire,
            'chirias_date' => [
                'sediu_social' => $pj['sediu_social'] ?: null,
                'telefon' => $pj['telefon'] ?: null,
                'email' => $pj['email'] ?: null,
                'email_2' => ($pj['email_2'] ?? null) ?: null,
                'nr_reg_comert' => $pj['nr_reg_comert'] ?: null,
                'cui' => $pj['cui'] ?: null,
                'banca' => $pj['banca'] ?: null,
                'cont_bancar' => $pj['cont_bancar'] ?: null,
                'administrator' => self::administratorPayload($admin),
                'administrator_2' => self::administratorPayload($admin2, nullWhenEmpty: true),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(?string $tip, ?array $date, ?string $chirias = null): array
    {
        $emptyPf = self::emptyPf();
        $emptyPj = self::emptyPj();

        if ($tip === 'pf') {
            $emptyPf = array_merge($emptyPf, self::onlyKeys($date ?? [], array_keys($emptyPf)));
            self::splitLegacyEmailField($emptyPf);
            self::mergeLegacyPfCiFields($emptyPf);
            if ($emptyPf['nume_complet'] === '' && $chirias) {
                $emptyPf['nume_complet'] = $chirias;
            }

            return [
                'chirias_tip' => 'pf',
                'chirias_pf' => $emptyPf,
                'chirias_pj' => $emptyPj,
            ];
        }

        if ($tip === 'pj') {
            $emptyPj = array_merge($emptyPj, self::onlyKeys($date ?? [], array_keys(array_merge(
                $emptyPj,
                ['administrator' => self::emptyAdministrator(), 'administrator_2' => self::emptyAdministrator()]
            ))));

            if (isset($date['administrator']) && is_array($date['administrator'])) {
                $emptyPj['administrator'] = array_merge(
                    self::emptyAdministrator(),
                    self::onlyKeys($date['administrator'], array_keys(self::emptyAdministrator()))
                );
                self::splitLegacyEmailField($emptyPj['administrator']);
            }

            if (isset($date['administrator_2']) && is_array($date['administrator_2'])) {
                $emptyPj['administrator_2'] = array_merge(
                    self::emptyAdministrator(),
                    self::onlyKeys($date['administrator_2'], array_keys(self::emptyAdministrator()))
                );
                self::splitLegacyEmailField($emptyPj['administrator_2']);
            } else {
                $emptyPj['administrator_2'] = self::emptyAdministrator();
            }

            self::splitLegacyEmailField($emptyPj);

            if ($emptyPj['denumire'] === '' && $chirias) {
                $emptyPj['denumire'] = $chirias;
            }

            return [
                'chirias_tip' => 'pj',
                'chirias_pf' => $emptyPf,
                'chirias_pj' => $emptyPj,
            ];
        }

        return [
            'chirias_tip' => 'pf',
            'chirias_pf' => array_merge($emptyPf, ['nume_complet' => $chirias ?? '']),
            'chirias_pj' => $emptyPj,
        ];
    }

    /**
     * @return array{chirias_tip: string, chirias: string, chirias_date: array<string, mixed>}
     */
    private static function normalizePf(Request $request): array
    {
        $request->merge([
            'chirias_pf' => self::normalizeCnpInRequestGroup($request->input('chirias_pf', [])),
        ]);

        $validated = $request->validate([
            'chirias_pf' => ['required', 'array'],
            'chirias_pf.nume_complet' => ['required', 'string', 'max:255'],
            'chirias_pf.serie_ci' => ['required', 'string', 'max:500'],
            'chirias_pf.cnp' => ['required', 'string', 'max:13'],
            'chirias_pf.domiciliu' => ['required', 'string', 'max:500'],
            'chirias_pf.email' => ['required', 'email', 'max:255'],
            'chirias_pf.email_2' => ['nullable', 'email', 'max:255'],
            'chirias_pf.telefon' => ['required', 'string', 'max:50'],
            'chirias_pf.banca' => ['nullable', 'string', 'max:255'],
            'chirias_pf.cont_bancar' => ['nullable', 'string', 'max:100'],
        ]);

        $pf = self::trimStrings($validated['chirias_pf']);

        return [
            'chirias_tip' => 'pf',
            'chirias' => $pf['nume_complet'],
            'chirias_date' => [
                'serie_ci' => $pf['serie_ci'],
                'numar_ci' => null,
                'cnp' => $pf['cnp'],
                'domiciliu' => $pf['domiciliu'],
                'email' => $pf['email'],
                'email_2' => ($pf['email_2'] ?? null) ?: null,
                'telefon' => ($pf['telefon'] ?? null) ?: null,
                'banca' => ($pf['banca'] ?? null) ?: null,
                'cont_bancar' => ($pf['cont_bancar'] ?? null) ?: null,
            ],
        ];
    }

    /**
     * @return array{chirias_tip: string, chirias: string, chirias_date: array<string, mixed>}
     */
    private static function normalizePj(Request $request): array
    {
        $request->merge([
            'chirias_pj' => self::normalizeCnpInRequestGroup($request->input('chirias_pj', [])),
        ]);

        $validated = $request->validate([
            'chirias_pj' => ['required', 'array'],
            'chirias_pj.denumire' => ['required', 'string', 'max:255'],
            'chirias_pj.sediu_social' => ['required', 'string', 'max:500'],
            'chirias_pj.telefon' => ['required', 'string', 'max:50'],
            'chirias_pj.email' => ['required', 'email', 'max:255'],
            'chirias_pj.email_2' => ['nullable', 'email', 'max:255'],
            'chirias_pj.nr_reg_comert' => ['required', 'string', 'max:100'],
            'chirias_pj.cui' => ['required', 'string', 'max:20'],
            'chirias_pj.banca' => ['nullable', 'string', 'max:255'],
            'chirias_pj.cont_bancar' => ['nullable', 'string', 'max:100'],
            ...self::administratorValidationRules('chirias_pj.administrator', requireNume: true),
            ...self::administratorValidationRules('chirias_pj.administrator_2'),
        ]);

        $pj = self::trimStrings($validated['chirias_pj']);
        $admin = self::trimStrings($pj['administrator']);
        $admin2 = self::trimStrings($pj['administrator_2'] ?? []);

        return [
            'chirias_tip' => 'pj',
            'chirias' => $pj['denumire'],
            'chirias_date' => [
                'sediu_social' => $pj['sediu_social'],
                'telefon' => ($pj['telefon'] ?? null) ?: null,
                'email' => $pj['email'],
                'email_2' => ($pj['email_2'] ?? null) ?: null,
                'nr_reg_comert' => $pj['nr_reg_comert'],
                'cui' => $pj['cui'],
                'banca' => ($pj['banca'] ?? null) ?: null,
                'cont_bancar' => ($pj['cont_bancar'] ?? null) ?: null,
                'administrator' => self::administratorPayload($admin),
                'administrator_2' => self::administratorPayload($admin2, nullWhenEmpty: true),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $pf
     */
    public static function mergeLegacyPfCiFields(array &$pf): void
    {
        $serie = trim((string) ($pf['serie_ci'] ?? ''));
        $numar = trim((string) ($pf['numar_ci'] ?? ''));

        if ($serie !== '') {
            return;
        }

        if ($numar !== '') {
            $pf['serie_ci'] = $numar;
        }
    }

    /**
     * @return array<string, string>
     */
    private static function emptyPf(): array
    {
        return [
            'nume_complet' => '',
            'serie_ci' => '',
            'numar_ci' => '',
            'cnp' => '',
            'domiciliu' => '',
            'email' => '',
            'email_2' => '',
            'telefon' => '',
            'banca' => '',
            'cont_bancar' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyPj(): array
    {
        return [
            'denumire' => '',
            'sediu_social' => '',
            'telefon' => '',
            'email' => '',
            'email_2' => '',
            'nr_reg_comert' => '',
            'cui' => '',
            'banca' => '',
            'cont_bancar' => '',
            'administrator' => self::emptyAdministrator(),
            'administrator_2' => self::emptyAdministrator(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function emptyAdministrator(): array
    {
        return [
            'nume_complet' => '',
            'calitate' => 'administrator',
            'serie_ci' => '',
            'numar_ci' => '',
            'cnp' => '',
            'domiciliu' => '',
            'email' => '',
            'email_2' => '',
            'telefon' => '',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function administratorPayload(array $admin, bool $nullWhenEmpty = false): ?array
    {
        $payload = [
            'nume_complet' => ($admin['nume_complet'] ?? null) ?: null,
            'calitate' => ($admin['calitate'] ?? null) ?: 'administrator',
            'serie_ci' => ($admin['serie_ci'] ?? null) ?: null,
            'numar_ci' => ($admin['numar_ci'] ?? null) ?: null,
            'cnp' => ($admin['cnp'] ?? null) ?: null,
            'domiciliu' => ($admin['domiciliu'] ?? null) ?: null,
            'email' => ($admin['email'] ?? null) ?: null,
            'email_2' => ($admin['email_2'] ?? null) ?: null,
            'telefon' => ($admin['telefon'] ?? null) ?: null,
        ];

        if ($nullWhenEmpty && blank($payload['nume_complet'])) {
            return null;
        }

        return $payload;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function administratorValidationRulesForCompleteness(string $prefix): array
    {
        return self::administratorValidationRules($prefix, requireNume: false);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function administratorValidationRules(string $prefix, bool $requireNume = false): array
    {
        return [
            $prefix => [$requireNume ? 'required' : 'nullable', 'array'],
            "{$prefix}.nume_complet" => [$requireNume ? 'required' : 'nullable', 'string', 'max:255'],
            "{$prefix}.calitate" => ['nullable', 'in:administrator,asociat,presedinte,reprezentant_legal,imputernicit_notarial'],
            "{$prefix}.serie_ci" => ['nullable', 'string', 'max:500'],
            "{$prefix}.numar_ci" => ['nullable', 'string', 'max:20'],
            "{$prefix}.cnp" => ['nullable', 'string', 'max:13'],
            "{$prefix}.domiciliu" => ['nullable', 'string', 'max:500'],
            "{$prefix}.email" => ['nullable', 'email', 'max:255'],
            "{$prefix}.email_2" => ['nullable', 'email', 'max:255'],
            "{$prefix}.telefon" => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function splitLegacyEmailField(array &$input): void
    {
        if (($input['email_2'] ?? '') !== '') {
            return;
        }

        $email = trim((string) ($input['email'] ?? ''));

        if (! str_contains($email, ',')) {
            return;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $email, 2))));

        $input['email'] = $parts[0] ?? '';
        $input['email_2'] = $parts[1] ?? '';
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private static function onlyKeys(array $input, array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            if ($key === 'administrator' || $key === 'administrator_2') {
                continue;
            }

            if (array_key_exists($key, $input)) {
                $result[$key] = is_string($input[$key]) ? trim($input[$key]) : $input[$key];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeCnpInRequestGroup(array $input): array
    {
        foreach ($input as $key => $value) {
            if ($key === 'cnp' && is_string($value)) {
                $input[$key] = ContractIncompleteStorage::normalizeCnpValue($value) ?? '';

                continue;
            }

            if (is_array($value)) {
                $input[$key] = self::normalizeCnpInRequestGroup($value);
            }
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function trimStrings(array $input): array
    {
        $result = [];

        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::trimStrings($value);
                continue;
            }

            if ($key === 'cnp' && is_string($value)) {
                $result[$key] = ContractIncompleteStorage::normalizeCnpValue($value) ?? '';

                continue;
            }

            $result[$key] = is_string($value) ? trim($value) : $value;
        }

        return $result;
    }
}
