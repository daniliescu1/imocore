<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contoare', function (Blueprint $table) {
            $table->foreignId('configurare_anexa_linie_id')
                ->nullable()
                ->after('spatiu_id')
                ->constrained('configurare_anexa_linii')
                ->cascadeOnDelete();

            $table->unique(['spatiu_id', 'configurare_anexa_linie_id'], 'contoare_spatiu_linie_anexa_unique');
        });
    }

    public function down(): void
    {
        Schema::table('contoare', function (Blueprint $table) {
            $table->dropUnique('contoare_spatiu_linie_anexa_unique');
            $table->dropConstrainedForeignId('configurare_anexa_linie_id');
        });
    }
};
