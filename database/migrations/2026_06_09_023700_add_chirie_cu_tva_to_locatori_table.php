<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locatori', function (Blueprint $table) {
            $table->boolean('chirie_cu_tva')->default(false)->after('cont_bancar');
        });
    }

    public function down(): void
    {
        Schema::table('locatori', function (Blueprint $table) {
            $table->dropColumn('chirie_cu_tva');
        });
    }
};
