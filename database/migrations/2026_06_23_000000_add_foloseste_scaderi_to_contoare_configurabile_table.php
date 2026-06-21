<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contoare_configurabile', function (Blueprint $table) {
            $table->boolean('foloseste_scaderi')->default(false)->after('configurare_anexa_linie_id');
        });

        DB::table('contoare_configurabile')->orderBy('id')->each(function (object $regula): void {
            $scaderi = json_decode($regula->scaderi ?? '[]', true);

            if (is_array($scaderi) && $scaderi !== []) {
                DB::table('contoare_configurabile')
                    ->where('id', $regula->id)
                    ->update(['foloseste_scaderi' => true]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('contoare_configurabile', function (Blueprint $table) {
            $table->dropColumn('foloseste_scaderi');
        });
    }
};
