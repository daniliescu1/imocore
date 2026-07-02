<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicii_standard_anexa', function (Blueprint $table) {
            $table->decimal('coeficient_cantitate', 8, 4)->nullable()->after('coeficient');
        });

        DB::table('servicii_standard_anexa')
            ->where('tip', 'pret')
            ->where(function ($query): void {
                $query->whereNull('label')
                    ->orWhereColumn('label', 'valoare');
            })
            ->update([
                'label' => 'Standard',
                'coeficient_cantitate' => 1,
            ]);

        DB::table('servicii_standard_anexa')
            ->where('tip', 'pret')
            ->whereNull('coeficient_cantitate')
            ->update(['coeficient_cantitate' => 1]);

        Schema::table('servicii_standard_anexa', function (Blueprint $table) {
            $table->dropUnique(['tip', 'valoare']);
            $table->unique(['tip', 'valoare', 'label']);
        });

        Schema::table('configurare_anexa_linii', function (Blueprint $table) {
            $table->foreignId('serviciu_standard_pret_id')
                ->nullable()
                ->after('denumire')
                ->constrained('servicii_standard_anexa')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('configurare_anexa_linii', function (Blueprint $table) {
            $table->dropConstrainedForeignId('serviciu_standard_pret_id');
        });

        Schema::table('servicii_standard_anexa', function (Blueprint $table) {
            $table->dropUnique(['tip', 'valoare', 'label']);
            $table->unique(['tip', 'valoare']);
            $table->dropColumn('coeficient_cantitate');
        });
    }
};
