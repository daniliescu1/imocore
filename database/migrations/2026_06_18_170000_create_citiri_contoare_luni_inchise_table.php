<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citiri_contoare_luni_inchise', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imobil_id')->constrained('imobile')->cascadeOnDelete();
            $table->string('luna', 7);
            $table->timestamp('inchis_at');
            $table->timestamps();

            $table->unique(['imobil_id', 'luna']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citiri_contoare_luni_inchise');
    }
};
