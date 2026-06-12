<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imobile', function (Blueprint $table) {
            $table->unsignedInteger('ordine')->default(0)->after('id');
        });

        $imobile = DB::table('imobile')
            ->orderBy('nume')
            ->orderBy('id')
            ->get(['id']);

        foreach ($imobile as $index => $imobil) {
            DB::table('imobile')
                ->where('id', $imobil->id)
                ->update(['ordine' => $index + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('imobile', function (Blueprint $table) {
            $table->dropColumn('ordine');
        });
    }
};
