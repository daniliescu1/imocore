<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;

class ContractCompleteness
{
    /**
     * @return array<string, string>
     */
    public static function fieldLabels(): array
    {
        return [
            'spatiu_id' => 'Spațiu',
            'locator_id' => 'Locator',
            'numar_contract' => 'Număr contract',
            'data_start' => 'Data start',
            'data_end' => 'Data end',
            'chirie' => 'Chirie lunară',
            'chirias_tip' => 'Tip chiriaș',
            'chirias_pf.nume_complet' => 'Nume complet chiriaș',
            'chirias_pf.serie_ci' => 'Serie CI chiriaș',
            'chirias_pf.numar_ci' => 'Număr CI chiriaș',
            'chirias_pf.cnp' => 'CNP chiriaș',
            'chirias_pf.domiciliu' => 'Domiciliu chiriaș',
            'chirias_pf.email' => 'Email chiriaș',
            'chirias_pf.email_2' => 'Al doilea email chiriaș',
            'chirias_pf.telefon' => 'Telefon chiriaș',
            'chirias_pj.denumire' => 'Denumire firmă',
            'chirias_pj.sediu_social' => 'Sediu social',
            'chirias_pj.email' => 'Email firmă',
            'chirias_pj.email_2' => 'Al doilea email firmă',
            'chirias_pj.telefon' => 'Telefon firmă',
            'chirias_pj.nr_reg_comert' => 'Registrul Comerțului',
            'chirias_pj.cui' => 'CUI',
            'chirias_pj.administrator.nume_complet' => 'Nume administrator',
            'chirias_pj.administrator.serie_ci' => 'Serie CI administrator',
            'chirias_pj.administrator.numar_ci' => 'Număr CI administrator',
            'chirias_pj.administrator.cnp' => 'CNP administrator',
            'chirias_pj.administrator.domiciliu' => 'Domiciliu administrator',
            'chirias_pj.administrator.email' => 'Email administrator',
            'chirias_pj.administrator.email_2' => 'Al doilea email administrator',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    public static function missingFieldKeys(array $input): array
    {
        $validator = Validator::make(
            ContractIncompleteStorage::normalizeInputForCompleteness($input),
            self::activeRules($input),
        );

        return self::keysFromErrors($validator->errors());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    public static function missingFieldLabels(array $input): array
    {
        $labels = self::fieldLabels();

        return array_values(array_map(
            fn (string $key): string => $labels[$key] ?? $key,
            self::missingFieldKeys($input),
        ));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function isComplete(array $input): bool
    {
        return self::missingFieldKeys($input) === [];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function activeRules(array $input): array
    {
        $tip = $input['chirias_tip'] ?? 'pj';

        $rules = [
            'spatiu_id' => ['required', 'exists:spatii,id'],
            'locator_id' => ['required', 'exists:locatori,id'],
            'numar_contract' => ['required', 'string', 'max:255'],
            'chirias_tip' => ['required', 'in:pf,pj'],
            'data_start' => ['required', 'date'],
            'data_end' => ['required', 'date', 'after_or_equal:data_start'],
            'chirie' => ['required', 'numeric', 'min:0'],
        ];

        if ($tip === 'pf') {
            return [
                ...$rules,
                'chirias_pf' => ['required', 'array'],
                'chirias_pf.nume_complet' => ['required', 'string', 'max:255'],
                'chirias_pf.serie_ci' => ['required', 'string', 'max:10'],
                'chirias_pf.numar_ci' => ['required', 'string', 'max:20'],
                'chirias_pf.cnp' => ['required', 'string', 'max:13'],
                'chirias_pf.domiciliu' => ['required', 'string', 'max:500'],
                'chirias_pf.email' => ['required', 'email', 'max:255'],
                'chirias_pf.email_2' => ['nullable', 'email', 'max:255'],
                'chirias_pf.telefon' => ['required', 'string', 'max:50'],
            ];
        }

        return [
            ...$rules,
            'chirias_pj' => ['required', 'array'],
            'chirias_pj.denumire' => ['required', 'string', 'max:255'],
            'chirias_pj.sediu_social' => ['required', 'string', 'max:500'],
            'chirias_pj.email' => ['required', 'email', 'max:255'],
            'chirias_pj.email_2' => ['nullable', 'email', 'max:255'],
            'chirias_pj.telefon' => ['required', 'string', 'max:50'],
            'chirias_pj.nr_reg_comert' => ['required', 'string', 'max:100'],
            'chirias_pj.cui' => ['required', 'string', 'max:20'],
            'chirias_pj.administrator' => ['required', 'array'],
            'chirias_pj.administrator.nume_complet' => ['required', 'string', 'max:255'],
            'chirias_pj.administrator.serie_ci' => ['nullable', 'string', 'max:500'],
            'chirias_pj.administrator.numar_ci' => ['nullable', 'string', 'max:20'],
            'chirias_pj.administrator.cnp' => ['nullable', 'string', 'max:13'],
            'chirias_pj.administrator.domiciliu' => ['nullable', 'string', 'max:500'],
            'chirias_pj.administrator.email' => ['nullable', 'email', 'max:255'],
            'chirias_pj.administrator.email_2' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return list<string>
     */
    private static function keysFromErrors(MessageBag $errors): array
    {
        $keys = array_keys($errors->toArray());

        return array_values(array_filter($keys, fn (string $key): bool => $key !== 'chirias_pf' && $key !== 'chirias_pj'));
    }
}
