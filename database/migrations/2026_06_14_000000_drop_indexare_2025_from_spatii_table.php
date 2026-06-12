<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spatii', function (Blueprint $table) {
            $table->dropColumn('indexare_2025');
        });
    }

    public function down(): void
    {
        Schema::table('spatii', function (Blueprint $table) {
            $table->decimal('indexare_2025', 12, 2)->nullable()->after('pret_lunar');
        });
    }
};
