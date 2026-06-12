<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spatii', function (Blueprint $table) {
            $table->text('de_lamurit_detaliu')->nullable()->after('de_lamurit');
        });
    }

    public function down(): void
    {
        Schema::table('spatii', function (Blueprint $table) {
            $table->dropColumn('de_lamurit_detaliu');
        });
    }
};
