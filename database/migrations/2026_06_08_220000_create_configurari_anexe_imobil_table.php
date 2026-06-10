<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurari_anexe_imobil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imobil_id')->constrained('imobile')->cascadeOnDelete();
            $table->string('denumire');
            $table->boolean('implicit')->default(false);
            $table->boolean('activ')->default(true);
            $table->text('observatii')->nullable();
            $table->timestamps();
        });

        Schema::create('configurare_anexa_linii', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configurare_anexa_id')->constrained('configurari_anexe_imobil')->cascadeOnDelete();
            $table->string('denumire');
            $table->string('tip_calcul')->default('manual');
            $table->boolean('apare_cu_zero')->default(true);
            $table->boolean('activ')->default(true);
            $table->text('observatii')->nullable();
            $table->timestamps();
        });

        Schema::table('spatii', function (Blueprint $table) {
            $table->foreignId('configurare_anexa_id')->nullable()->after('locator_id')->constrained('configurari_anexe_imobil')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('spatii', function (Blueprint $table) {
            $table->dropConstrainedForeignId('configurare_anexa_id');
        });

        Schema::dropIfExists('configurare_anexa_linii');
        Schema::dropIfExists('configurari_anexe_imobil');
    }
};
