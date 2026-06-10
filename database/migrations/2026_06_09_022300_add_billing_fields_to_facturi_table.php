<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturi', function (Blueprint $table) {
            $table->decimal('curs_eur', 10, 4)->nullable()->after('numar_factura');
            $table->decimal('chirie_eur', 14, 2)->default(0)->after('curs_eur');
            $table->decimal('chirie_lei', 14, 2)->default(0)->after('chirie_eur');
            $table->decimal('penalitati', 14, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('facturi', function (Blueprint $table) {
            $table->dropColumn([
                'curs_eur',
                'chirie_eur',
                'chirie_lei',
                'penalitati',
            ]);
        });
    }
};
