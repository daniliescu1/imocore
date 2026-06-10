<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spatii', function (Blueprint $table) {
            $table->boolean('marcat_galben')->default(false)->after('de_lamurit');
            $table->boolean('marcat_verde')->default(false)->after('marcat_galben');
        });
    }

    public function down(): void
    {
        Schema::table('spatii', function (Blueprint $table) {
            $table->dropColumn(['marcat_galben', 'marcat_verde']);
        });
    }
};
