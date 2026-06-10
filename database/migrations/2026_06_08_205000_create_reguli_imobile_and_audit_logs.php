<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reguli_imobile', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imobil_id')->unique()->constrained('imobile')->cascadeOnDelete();
            $table->string('metoda_curent')->default('standard');
            $table->decimal('procent_pierdere_curent', 5, 2)->default(0);
            $table->string('metoda_apa')->default('contoare_si_persoane');
            $table->string('metoda_canalizare')->default('ca_apa');
            $table->decimal('coeficient_apa_pluviala', 10, 4)->nullable();
            $table->boolean('coeficient_apa_pluviala_aprobat')->default(false);
            $table->decimal('procent_incalzire_partial', 5, 2)->default(33);
            $table->date('incalzire_start')->nullable();
            $table->date('incalzire_end')->nullable();
            $table->string('metoda_spatii_comune')->default('sub_50_persoane_peste_50_mp');
            $table->string('metoda_retim')->default('persoane');
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('auditable');
            $table->string('actiune');
            $table->string('camp')->nullable();
            $table->text('valoare_veche')->nullable();
            $table->text('valoare_noua')->nullable();
            $table->text('motiv')->nullable();
            $table->string('user_name')->default('Owner');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reguli_imobile');
    }
};
