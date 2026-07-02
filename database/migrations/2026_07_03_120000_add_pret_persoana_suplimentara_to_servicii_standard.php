<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicii_standard_anexa', function (Blueprint $table) {
            $table->decimal('pret_persoana_suplimentara', 10, 4)
                ->nullable()
                ->after('coeficient_cantitate');
        });
    }

    public function down(): void
    {
        Schema::table('servicii_standard_anexa', function (Blueprint $table) {
            $table->dropColumn('pret_persoana_suplimentara');
        });
    }
};
