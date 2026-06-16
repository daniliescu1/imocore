<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracte', function (Blueprint $table) {
            $table->string('chirias_tip', 2)->nullable()->after('chirias');
            $table->json('chirias_date')->nullable()->after('chirias_tip');
        });
    }

    public function down(): void
    {
        Schema::table('contracte', function (Blueprint $table) {
            $table->dropColumn(['chirias_tip', 'chirias_date']);
        });
    }
};
