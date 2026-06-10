<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setari_aplicatie', function (Blueprint $table) {
            $table->id();
            $table->string('cheie')->unique();
            $table->text('valoare')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setari_aplicatie');
    }
};
