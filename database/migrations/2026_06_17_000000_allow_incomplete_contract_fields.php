<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracte', function (Blueprint $table) {
            $table->string('numar_contract')->nullable()->change();
            $table->string('chirias')->nullable()->change();
            $table->date('data_start')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contracte', function (Blueprint $table) {
            $table->string('numar_contract')->nullable(false)->change();
            $table->string('chirias')->nullable(false)->change();
            $table->date('data_start')->nullable(false)->change();
        });
    }
};
