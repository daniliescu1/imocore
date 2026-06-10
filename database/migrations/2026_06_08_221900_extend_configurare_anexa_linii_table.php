<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurare_anexa_linii', function (Blueprint $table) {
            $table->unsignedInteger('nr_crt')->nullable()->after('denumire');
            $table->string('index_vechi')->nullable()->after('nr_crt');
            $table->string('index_nou')->nullable()->after('index_vechi');
            $table->decimal('facturat', 14, 3)->nullable()->after('index_nou');
            $table->string('um')->nullable()->after('facturat');
            $table->decimal('pret_unitar', 14, 4)->nullable()->after('um');
            $table->decimal('valoare', 14, 2)->nullable()->after('pret_unitar');
            $table->decimal('tva_21', 14, 2)->nullable()->after('valoare');
        });
    }

    public function down(): void
    {
        Schema::table('configurare_anexa_linii', function (Blueprint $table) {
            $table->dropColumn([
                'nr_crt',
                'index_vechi',
                'index_nou',
                'facturat',
                'um',
                'pret_unitar',
                'valoare',
                'tva_21',
            ]);
        });
    }
};
