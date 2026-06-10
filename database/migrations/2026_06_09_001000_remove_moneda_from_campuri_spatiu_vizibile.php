<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('imobile')
            ->whereNotNull('campuri_spatiu_vizibile')
            ->get(['id', 'campuri_spatiu_vizibile'])
            ->each(function (object $imobil): void {
                $campuri = json_decode($imobil->campuri_spatiu_vizibile, true);

                if (! is_array($campuri)) {
                    return;
                }

                $campuriCurate = array_values(array_filter(
                    $campuri,
                    fn ($camp): bool => $camp !== 'moneda'
                ));

                DB::table('imobile')->where('id', $imobil->id)->update([
                    'campuri_spatiu_vizibile' => json_encode($campuriCurate),
                ]);
            });
    }

    public function down(): void
    {
        //
    }
};
