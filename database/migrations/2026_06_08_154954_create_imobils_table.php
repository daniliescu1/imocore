<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imobile', function (Blueprint $table) {
            $table->id();
            $table->string('nume');
            $table->string('strada');
            $table->string('numar', 50);
            $table->string('localitate');
            $table->string('judet')->nullable();
            $table->string('cod_postal', 20)->nullable();
            $table->string('numar_cf')->nullable();
            $table->string('numar_topo')->nullable();
            $table->unsignedInteger('spatii_total')->default(0);
            $table->unsignedInteger('spatii_libere')->default(0);
            $table->unsignedInteger('spatii_inchiriate')->default(0);
            $table->text('observatii')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imobile');
    }
};