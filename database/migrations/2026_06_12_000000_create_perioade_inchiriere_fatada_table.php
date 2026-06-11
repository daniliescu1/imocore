<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perioade_inchiriere_fatada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spatiu_id')->constrained('spatii')->cascadeOnDelete();
            $table->date('data_start');
            $table->date('data_end');
            $table->string('chirias');
            $table->decimal('chirie_lunara', 12, 2);
            $table->string('moneda', 3)->default('EUR');
            $table->timestamps();

            $table->index(['spatiu_id', 'data_start', 'data_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perioade_inchiriere_fatada');
    }
};
