<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contoare_configurabile', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imobil_id')->constrained('imobile')->cascadeOnDelete();
            $table->foreignId('configurare_anexa_id')->constrained('configurari_anexe_imobil')->cascadeOnDelete();
            $table->foreignId('configurare_anexa_linie_id')->unique()->constrained('configurare_anexa_linii')->cascadeOnDelete();
            $table->json('scaderi')->nullable();
            $table->json('alocari')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contoare_configurabile');
    }
};
