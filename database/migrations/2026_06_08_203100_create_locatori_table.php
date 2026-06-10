<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locatori', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imobil_id')->constrained('imobile')->cascadeOnDelete();
            $table->string('nume');
            $table->timestamps();

            $table->unique(['imobil_id', 'nume']);
        });

        Schema::table('spatii', function (Blueprint $table) {
            $table->foreignId('locator_id')->nullable()->after('moneda')->constrained('locatori')->nullOnDelete();
        });

        DB::table('spatii')
            ->whereNotNull('locator')
            ->where('locator', '!=', '')
            ->orderBy('id')
            ->get(['id', 'imobil_id', 'locator'])
            ->each(function (object $spatiu): void {
                $locatorId = DB::table('locatori')->where([
                    'imobil_id' => $spatiu->imobil_id,
                    'nume' => $spatiu->locator,
                ])->value('id');

                if (! $locatorId) {
                    $locatorId = DB::table('locatori')->insertGetId([
                        'imobil_id' => $spatiu->imobil_id,
                        'nume' => $spatiu->locator,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('spatii')->where('id', $spatiu->id)->update(['locator_id' => $locatorId]);
            });
    }

    public function down(): void
    {
        Schema::table('spatii', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locator_id');
        });

        Schema::dropIfExists('locatori');
    }
};
