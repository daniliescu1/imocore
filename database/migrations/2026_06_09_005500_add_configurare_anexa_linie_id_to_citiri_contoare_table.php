<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citiri_contoare', function (Blueprint $table) {
            $table->foreignId('configurare_anexa_linie_id')
                ->nullable()
                ->after('contor_id')
                ->constrained('configurare_anexa_linii')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('citiri_contoare', function (Blueprint $table) {
            $table->dropConstrainedForeignId('configurare_anexa_linie_id');
        });
    }
};
