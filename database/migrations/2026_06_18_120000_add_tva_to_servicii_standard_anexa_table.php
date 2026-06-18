<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicii_standard_anexa', function (Blueprint $table) {
            $table->string('tva', 16)->nullable()->after('coeficient');
        });
    }

    public function down(): void
    {
        Schema::table('servicii_standard_anexa', function (Blueprint $table) {
            $table->dropColumn('tva');
        });
    }
};
