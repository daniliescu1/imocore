<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurare_anexa_linii', function (Blueprint $table) {
            $table->decimal('coeficient', 14, 4)->nullable()->after('facturat');
        });

        Schema::table('anexa_linii', function (Blueprint $table) {
            $table->decimal('coeficient', 14, 4)->nullable()->after('cantitate');
        });
    }

    public function down(): void
    {
        Schema::table('configurare_anexa_linii', function (Blueprint $table) {
            $table->dropColumn('coeficient');
        });

        Schema::table('anexa_linii', function (Blueprint $table) {
            $table->dropColumn('coeficient');
        });
    }
};
