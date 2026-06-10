<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citiri_contoare', function (Blueprint $table) {
            $table->dateTime('data_citire')->nullable()->after('luna');
        });
    }

    public function down(): void
    {
        Schema::table('citiri_contoare', function (Blueprint $table) {
            $table->dropColumn('data_citire');
        });
    }
};
