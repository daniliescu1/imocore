<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurare_anexa_linii', function (Blueprint $table) {
            $table->unsignedInteger('ordine')->default(0)->after('configurare_anexa_id');
            $table->string('tip_linie', 20)->default('serviciu')->after('ordine');
        });

        Schema::table('anexa_linii', function (Blueprint $table) {
            $table->unsignedInteger('ordine')->default(0)->after('anexa_id');
            $table->string('tip_linie', 20)->default('serviciu')->after('ordine');
        });

        DB::table('configurare_anexa_linii')
            ->select('configurare_anexa_id')
            ->distinct()
            ->pluck('configurare_anexa_id')
            ->each(function ($configurareId): void {
                DB::table('configurare_anexa_linii')
                    ->where('configurare_anexa_id', $configurareId)
                    ->orderBy('nr_crt')
                    ->orderBy('id')
                    ->pluck('id')
                    ->each(function ($linieId, int $index): void {
                        DB::table('configurare_anexa_linii')->where('id', $linieId)->update([
                            'ordine' => $index + 1,
                        ]);
                    });
            });

        DB::table('anexa_linii')
            ->select('anexa_id')
            ->distinct()
            ->pluck('anexa_id')
            ->each(function ($anexaId): void {
                DB::table('anexa_linii')
                    ->where('anexa_id', $anexaId)
                    ->orderBy('nr_crt')
                    ->orderBy('id')
                    ->pluck('id')
                    ->each(function ($linieId, int $index): void {
                        DB::table('anexa_linii')->where('id', $linieId)->update([
                            'ordine' => $index + 1,
                        ]);
                    });
            });
    }

    public function down(): void
    {
        Schema::table('configurare_anexa_linii', function (Blueprint $table) {
            $table->dropColumn(['ordine', 'tip_linie']);
        });

        Schema::table('anexa_linii', function (Blueprint $table) {
            $table->dropColumn(['ordine', 'tip_linie']);
        });
    }
};
