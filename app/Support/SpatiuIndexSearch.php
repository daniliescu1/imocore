<?php

namespace App\Support;

use App\Models\Imobil;
use App\Models\Spatiu;

class SpatiuIndexSearch
{
    /**
     * @return list<string>
     */
    public static function localitati(): array
    {
        return Imobil::query()
            ->select('localitate')
            ->distinct()
            ->orderBy('localitate')
            ->pluck('localitate')
            ->all();
    }

    public static function matchesSpatii(string $search, string $localitate = ''): bool
    {
        if ($search === '') {
            return false;
        }

        $query = Spatiu::query();

        if ($localitate !== '') {
            $query->whereHas('imobil', fn ($imobilQuery) => $imobilQuery->where('localitate', $localitate));
        }

        return $query->where(function ($query) use ($search) {
            $query->where('identificator', 'like', "%{$search}%")
                ->orWhere('locator', 'like', "%{$search}%")
                ->orWhere('chirias', 'like', "%{$search}%");
        })->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function spatiiForSearch(string $search, string $localitate = ''): array
    {
        $query = Spatiu::query()
            ->with([
                'imobil',
                'locatorEntitate',
                'contracte' => fn ($query) => $query->where('status', 'activ')->latest('id'),
            ])
            ->withExists(['contracte as are_contract_inregistrat'])
            ->withExists(['contracte as are_contract_activ' => fn ($query) => $query->where('status', 'activ')]);

        if ($localitate !== '') {
            $query->whereHas('imobil', fn ($imobilQuery) => $imobilQuery->where('localitate', $localitate));
        }

        $query->where(function ($query) use ($search) {
            $query->where('identificator', 'like', "%{$search}%")
                ->orWhere('locator', 'like', "%{$search}%")
                ->orWhere('chirias', 'like', "%{$search}%");
        });

        return $query
            ->join('imobile', 'spatii.imobil_id', '=', 'imobile.id')
            ->orderBy('imobile.ordine')
            ->orderBy('imobile.id')
            ->orderBy('spatii.ordine')
            ->orderBy('spatii.id')
            ->select('spatii.*')
            ->get()
            ->map(fn (Spatiu $spatiu): array => self::mapSpatiuForList($spatiu))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapSpatiuForList(Spatiu $spatiu): array
    {
        $suprafata = $spatiu->suprafata_contractuala_mp;
        $contractActiv = $spatiu->contracte->first();
        $chirieCurenta = $contractActiv
            ? $contractActiv->chirieAplicabilaLa()
            : ($spatiu->indexare_2026 ?: $spatiu->pret_lunar);
        $sursaChirieCurenta = $contractActiv
            ? ($contractActiv->folosesteCrestereChirieLa() ? 'Creștere chirie' : 'Contract activ')
            : ($spatiu->indexare_2026 ? 'Indexare 2026' : null);
        $pretMpCurent = $suprafata && $chirieCurenta
            ? number_format((float) $chirieCurenta / (float) $suprafata, 2, '.', '')
            : null;

        return [
            'id' => $spatiu->id,
            'imobil_id' => $spatiu->imobil_id,
            'identificator' => $spatiu->identificator,
            'etaj' => $spatiu->etaj ?: '—',
            'imobil' => $spatiu->imobil?->nume ?: '—',
            'localitate' => $spatiu->imobil?->localitate ?: '—',
            'suprafata_contractuala_mp' => $suprafata,
            'status' => $spatiu->status,
            'pret_lunar' => $spatiu->pret_lunar,
            'indexare_2026' => $spatiu->indexare_2026,
            'chirie_lunara_curenta' => $chirieCurenta,
            'sursa_chirie_curenta' => $sursaChirieCurenta,
            'pret_mp_curent' => $pretMpCurent,
            'moneda' => $spatiu->moneda,
            'moneda_label' => $spatiu->monedaLabel(),
            'locator' => $spatiu->locatorEntitate?->nume ?: ($spatiu->getAttribute('locator') ?: '—'),
            'chirias' => $spatiu->chirias ?: '—',
            'de_lamurit' => (bool) $spatiu->de_lamurit,
            'de_lamurit_detaliu' => $spatiu->de_lamurit ? ($spatiu->de_lamurit_detaliu ?: null) : null,
            'marcat_galben' => (bool) $spatiu->marcat_galben,
            'marcat_verde' => (bool) $spatiu->marcat_verde,
            'necesita_anexa' => ! in_array($spatiu->status, ['administrativ', 'comun'], true),
            'are_anexa_alocata' => $spatiu->configurare_anexa_id !== null,
            'are_contract_inregistrat' => (bool) ($spatiu->are_contract_inregistrat ?? false),
            'are_contract_activ' => (bool) ($spatiu->are_contract_activ ?? false),
        ];
    }
}
