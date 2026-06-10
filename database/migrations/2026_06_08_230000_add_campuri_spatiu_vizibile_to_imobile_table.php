<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imobile', function (Blueprint $table) {
            $table->json('campuri_spatiu_vizibile')->nullable()->after('numere_cf');
        });
    }

    public function down(): void
    {
        Schema::table('imobile', function (Blueprint $table) {
            $table->dropColumn('campuri_spatiu_vizibile');
        });
    }
};
