<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anexa_linii', function (Blueprint $table) {
            $table->unsignedInteger('nr_crt')->nullable()->after('anexa_id');
            $table->string('um')->nullable()->after('denumire');
            $table->string('tip_calcul')->nullable()->after('um');
            $table->decimal('index_vechi', 14, 3)->nullable()->after('tip_calcul');
            $table->decimal('index_nou', 14, 3)->nullable()->after('index_vechi');
            $table->decimal('tva_21', 14, 2)->nullable()->after('valoare');
        });
    }

    public function down(): void
    {
        Schema::table('anexa_linii', function (Blueprint $table) {
            $table->dropColumn([
                'nr_crt',
                'um',
                'tip_calcul',
                'index_vechi',
                'index_nou',
                'tva_21',
            ]);
        });
    }
};
