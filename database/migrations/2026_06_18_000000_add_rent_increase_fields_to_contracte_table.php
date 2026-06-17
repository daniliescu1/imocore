<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracte', function (Blueprint $table) {
            $table->decimal('crestere_chirie_la', 12, 2)->nullable()->after('chirie');
            $table->date('data_crestere_chirie')->nullable()->after('crestere_chirie_la');
        });
    }

    public function down(): void
    {
        Schema::table('contracte', function (Blueprint $table) {
            $table->dropColumn(['crestere_chirie_la', 'data_crestere_chirie']);
        });
    }
};
