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
        $pj = self::trimStrings(array_merge(self::emptyPj(), $pjInput));
        $admin = self::trimStrings(array_merge(self::emptyAdministrator(), $adminInput));
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
                'administrator' => [
                    'nume_complet' => $admin['nume_complet'] ?: null,
                    'serie_ci' => $admin['serie_ci'] ?: null,
                    'numar_ci' => $admin['numar_ci'] ?: null,
                    'cnp' => $admin['cnp'] ?: null,
                    'domiciliu' => $admin['domiciliu'] ?: null,
                    'email' => $admin['email'] ?: null,
                    'email_2' => ($admin['email_2'] ?? null) ?: null,
                    'telefon' => $admin['telefon'] ?: null,
                ],
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
                ['administrator' => self::emptyAdministrator()]
            ))));

            if (isset($date['administrator']) && is_array($date['administrator'])) {
                $emptyPj['administrator'] = array_merge(
                    self::emptyAdministrator(),
                    self::onlyKeys($date['administrator'], array_keys(self::emptyAdministrator()))
                );
                self::splitLegacyEmailField($emptyPj['administrator']);
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
        $validated = $request->validate([
            'chirias_pf' => ['required', 'array'],
            'chirias_pf.nume_complet' => ['required', 'string', 'max:255'],
            'chirias_pf.serie_ci' => ['required', 'string', 'max:10'],
            'chirias_pf.numar_ci' => ['required', 'string', 'max:20'],
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
                'numar_ci' => $pf['numar_ci'],
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
            'chirias_pj.administrator' => ['required', 'array'],
            'chirias_pj.administrator.nume_complet' => ['required', 'string', 'max:255'],
            'chirias_pj.administrator.serie_ci' => ['nullable', 'string', 'max:10'],
            'chirias_pj.administrator.numar_ci' => ['nullable', 'string', 'max:20'],
            'chirias_pj.administrator.cnp' => ['nullable', 'string', 'max:13'],
            'chirias_pj.administrator.domiciliu' => ['nullable', 'string', 'max:500'],
            'chirias_pj.administrator.email' => ['nullable', 'email', 'max:255'],
            'chirias_pj.administrator.email_2' => ['nullable', 'email', 'max:255'],
            'chirias_pj.administrator.telefon' => ['nullable', 'string', 'max:50'],
        ]);

        $pj = self::trimStrings($validated['chirias_pj']);
        $admin = self::trimStrings($pj['administrator']);

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
                'administrator' => [
                    'nume_complet' => $admin['nume_complet'],
                    'serie_ci' => ($admin['serie_ci'] ?? null) ?: null,
                    'numar_ci' => ($admin['numar_ci'] ?? null) ?: null,
                    'cnp' => ($admin['cnp'] ?? null) ?: null,
                    'domiciliu' => ($admin['domiciliu'] ?? null) ?: null,
                    'email' => ($admin['email'] ?? null) ?: null,
                    'email_2' => ($admin['email_2'] ?? null) ?: null,
                    'telefon' => ($admin['telefon'] ?? null) ?: null,
                ],
            ],
        ];
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
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function emptyAdministrator(): array
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
            if ($key === 'administrator') {
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
    private static function trimStrings(array $input): array
    {
        $result = [];

        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::trimStrings($value);
                continue;
            }

            $result[$key] = is_string($value) ? trim($value) : $value;
        }

        return $result;
    }
}
