<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('owner')->after('email');
        });

        Schema::table('spatii', function (Blueprint $table) {
            $table->boolean('activ')->default(true)->after('status');
            $table->timestamp('arhivat_la')->nullable()->after('activ');
        });

        DB::table('users')->update(['role' => 'owner']);
    }

    public function down(): void
    {
        Schema::table('spatii', function (Blueprint $table) {
            $table->dropColumn(['activ', 'arhivat_la']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
