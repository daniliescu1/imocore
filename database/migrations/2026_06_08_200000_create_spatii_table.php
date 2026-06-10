<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spatii', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imobil_id')->constrained('imobile')->cascadeOnDelete();
            $table->string('identificator');
            $table->decimal('suprafata_mp', 10, 2)->nullable();
            $table->string('status')->default('liber');
            $table->decimal('pret_lunar', 12, 2)->nullable();
            $table->string('moneda', 3)->default('EUR');
            $table->string('locator')->nullable();
            $table->string('chirias')->nullable();
            $table->text('observatii')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spatii');
    }
};
