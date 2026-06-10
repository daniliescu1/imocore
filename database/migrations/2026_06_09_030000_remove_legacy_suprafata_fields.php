<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CAMPURI_ELIMINATE = [
        'suprafata_construita_mp',
        'suprafata_spatii_comune_mp',
        'suprafata_mp',
    ];

    public function up(): void
    {
        DB::table('spatii')
            ->whereNull('suprafata_contractuala_mp')
            ->whereNotNull('suprafata_mp')
            ->update([
                'suprafata_contractuala_mp' => DB::raw('suprafata_mp'),
            ]);

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
                    fn ($camp): bool => ! in_array($camp, self::CAMPURI_ELIMINATE, true)
                ));

                DB::table('imobile')->where('id', $imobil->id)->update([
                    'campuri_spatiu_vizibile' => json_encode($campuriCurate),
                ]);
            });

        Schema::table('spatii', function (Blueprint $table): void {
            $table->dropColumn(self::CAMPURI_ELIMINATE);
        });
    }

    public function down(): void
    {
        Schema::table('spatii', function (Blueprint $table): void {
            $table->decimal('suprafata_mp', 10, 2)->nullable()->after('identificator');
            $table->decimal('suprafata_construita_mp', 10, 2)->nullable()->after('suprafata_contractuala_mp');
            $table->decimal('suprafata_spatii_comune_mp', 10, 2)->nullable()->after('suprafata_construita_mp');
        });

        DB::table('spatii')
            ->whereNotNull('suprafata_contractuala_mp')
            ->update([
                'suprafata_mp' => DB::raw('suprafata_contractuala_mp'),
            ]);
    }
};
