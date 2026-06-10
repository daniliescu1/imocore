<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locatori', function (Blueprint $table) {
            $table->boolean('cui_are_ro')->default(false)->after('nume');
            $table->string('cui')->nullable()->after('cui_are_ro');
            $table->string('registrul_comertului')->nullable()->after('cui');
            $table->string('adresa')->nullable()->after('registrul_comertului');
            $table->string('banca')->nullable()->after('adresa');
            $table->string('cont_bancar')->nullable()->after('banca');
        });
    }

    public function down(): void
    {
        Schema::table('locatori', function (Blueprint $table) {
            $table->dropColumn([
                'cui_are_ro',
                'cui',
                'registrul_comertului',
                'adresa',
                'banca',
                'cont_bancar',
            ]);
        });
    }
};
