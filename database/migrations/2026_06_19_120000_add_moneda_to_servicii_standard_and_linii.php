<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicii_standard_anexa', function (Blueprint $table) {
            $table->string('moneda', 3)->default('RON')->after('coeficient');
        });

        Schema::table('configurare_anexa_linii', function (Blueprint $table) {
            $table->string('moneda', 3)->default('RON')->after('pret_unitar');
        });

        Schema::table('anexa_linii', function (Blueprint $table) {
            $table->string('moneda', 3)->default('RON')->after('pret_unitar');
        });
    }

    public function down(): void
    {
        Schema::table('servicii_standard_anexa', function (Blueprint $table) {
            $table->dropColumn('moneda');
        });

        Schema::table('configurare_anexa_linii', function (Blueprint $table) {
            $table->dropColumn('moneda');
        });

        Schema::table('anexa_linii', function (Blueprint $table) {
            $table->dropColumn('moneda');
        });
    }
};
