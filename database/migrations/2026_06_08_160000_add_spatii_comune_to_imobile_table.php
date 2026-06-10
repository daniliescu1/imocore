<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imobile', function (Blueprint $table) {
            $table->unsignedInteger('spatii_comune')->default(0)->after('spatii_inchiriate');
        });
    }

    public function down(): void
    {
        Schema::table('imobile', function (Blueprint $table) {
            $table->dropColumn('spatii_comune');
        });
    }
};