<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturi', function (Blueprint $table) {
            $table->dropForeign(['anexa_id']);
        });

        Schema::table('facturi', function (Blueprint $table) {
            $table->foreignId('anexa_id')->nullable()->change();
            $table->foreignId('contract_id')->nullable()->after('anexa_id')->constrained('contracte')->cascadeOnDelete();
            $table->string('luna', 7)->nullable()->after('contract_id');
            $table->foreign('anexa_id')->references('id')->on('anexe')->nullOnDelete();
            $table->unique(['contract_id', 'luna']);
        });
    }

    public function down(): void
    {
        Schema::table('facturi', function (Blueprint $table) {
            $table->dropUnique(['contract_id', 'luna']);
            $table->dropForeign(['contract_id']);
            $table->dropForeign(['anexa_id']);
            $table->dropColumn(['contract_id', 'luna']);
        });

        Schema::table('facturi', function (Blueprint $table) {
            $table->foreignId('anexa_id')->nullable(false)->change();
            $table->foreign('anexa_id')->references('id')->on('anexe')->cascadeOnDelete();
        });
    }
};
