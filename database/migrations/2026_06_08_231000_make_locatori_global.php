<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('locatori')
            ->select('nume')
            ->groupBy('nume')
            ->havingRaw('count(*) > 1')
            ->get()
            ->each(function (object $duplicate): void {
                $locatorIds = DB::table('locatori')
                    ->where('nume', $duplicate->nume)
                    ->orderBy('id')
                    ->pluck('id');

                $keepId = $locatorIds->first();
                $removeIds = $locatorIds->slice(1)->values();

                DB::table('spatii')->whereIn('locator_id', $removeIds)->update(['locator_id' => $keepId]);
                DB::table('locatori')->whereIn('id', $removeIds)->delete();
            });

        Schema::table('locatori', function (Blueprint $table) {
            $table->dropUnique('locatori_imobil_id_nume_unique');
        });

        Schema::table('locatori', function (Blueprint $table) {
            $table->foreignId('imobil_id')->nullable()->change();
            $table->unique('nume');
        });
    }

    public function down(): void
    {
        Schema::table('locatori', function (Blueprint $table) {
            $table->dropUnique(['nume']);
        });

        Schema::table('locatori', function (Blueprint $table) {
            $table->foreignId('imobil_id')->nullable()->change();
            $table->unique(['imobil_id', 'nume']);
        });
    }
};
