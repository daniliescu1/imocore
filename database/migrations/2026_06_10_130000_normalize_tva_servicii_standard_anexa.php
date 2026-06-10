<?php

use App\Models\ConfigurareAnexaLinie;
use App\Models\ServiciuStandardAnexa;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ServiciuStandardAnexa::query()
            ->where('tip', ServiciuStandardAnexa::TIP_TVA)
            ->each(function (ServiciuStandardAnexa $item): void {
                $valoare = ServiciuStandardAnexa::normalizeValoare(
                    ServiciuStandardAnexa::TIP_TVA,
                    $item->valoare
                );

                if ($valoare === '') {
                    return;
                }

                $existing = ServiciuStandardAnexa::query()
                    ->where('tip', ServiciuStandardAnexa::TIP_TVA)
                    ->where('valoare', $valoare)
                    ->whereKeyNot($item->id)
                    ->first();

                if ($existing) {
                    $item->delete();

                    return;
                }

                $item->update([
                    'valoare' => $valoare,
                    'label' => ServiciuStandardAnexa::tvaLabel($valoare),
                ]);
            });

        ConfigurareAnexaLinie::query()
            ->whereNotNull('tva_21')
            ->each(function (ConfigurareAnexaLinie $linie): void {
                $valoare = ServiciuStandardAnexa::normalizeValoare(
                    ServiciuStandardAnexa::TIP_TVA,
                    (string) $linie->tva_21
                );

                if ($valoare === '') {
                    $linie->update(['tva_21' => null]);

                    return;
                }

                $linie->update(['tva_21' => $valoare]);
            });
    }

    public function down(): void
    {
        //
    }
};
