<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicii_standard_anexa', function (Blueprint $table) {
            $table->decimal('coeficient', 12, 4)->nullable()->after('label');
        });

        DB::table('servicii_standard_anexa')
            ->where('tip', 'tip_calcul')
            ->where('valoare', 'mp_coeficient')
            ->update(['coeficient' => '0.0900']);
    }

    public function down(): void
    {
        Schema::table('servicii_standard_anexa', function (Blueprint $table) {
            $table->dropColumn('coeficient');
        });
    }
};
