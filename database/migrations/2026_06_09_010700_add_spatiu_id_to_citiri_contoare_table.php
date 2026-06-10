<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citiri_contoare', function (Blueprint $table) {
            $table->foreignId('spatiu_id')
                ->nullable()
                ->after('contor_id')
                ->constrained('spatii')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('citiri_contoare', function (Blueprint $table) {
            $table->dropConstrainedForeignId('spatiu_id');
        });
    }
};
