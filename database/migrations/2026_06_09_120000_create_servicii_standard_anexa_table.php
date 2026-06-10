<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicii_standard_anexa', function (Blueprint $table) {
            $table->id();
            $table->string('tip', 50);
            $table->string('valoare');
            $table->string('label')->nullable();
            $table->boolean('activ')->default(true);
            $table->unsignedInteger('ordine')->default(0);
            $table->timestamps();

            $table->unique(['tip', 'valoare']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicii_standard_anexa');
    }
};
