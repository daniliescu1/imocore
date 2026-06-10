<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spatii', function (Blueprint $table) {
            $table->decimal('suprafata_contractuala_mp', 10, 2)->nullable()->after('identificator');
            $table->decimal('suprafata_construita_mp', 10, 2)->nullable()->after('suprafata_contractuala_mp');
            $table->decimal('suprafata_spatii_comune_mp', 10, 2)->nullable()->after('suprafata_construita_mp');
            $table->string('corp')->nullable()->after('suprafata_spatii_comune_mp');
            $table->string('etaj')->nullable()->after('corp');
            $table->unsignedInteger('persoane_declarate')->nullable()->after('etaj');
            $table->string('regim_incalzire')->default('integral')->after('persoane_declarate');
            $table->decimal('procent_incalzire_override', 5, 2)->nullable()->after('regim_incalzire');
            $table->boolean('retim_direct')->default(false)->after('procent_incalzire_override');
        });
    }

    public function down(): void
    {
        Schema::table('spatii', function (Blueprint $table) {
            $table->dropColumn([
                'suprafata_contractuala_mp',
                'suprafata_construita_mp',
                'suprafata_spatii_comune_mp',
                'corp',
                'etaj',
                'persoane_declarate',
                'regim_incalzire',
                'procent_incalzire_override',
                'retim_direct',
            ]);
        });
    }
};
