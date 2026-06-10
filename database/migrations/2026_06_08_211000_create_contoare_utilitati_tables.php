<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contoare', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imobil_id')->constrained('imobile')->cascadeOnDelete();
            $table->foreignId('spatiu_id')->nullable()->constrained('spatii')->nullOnDelete();
            $table->string('tip_utilitate');
            $table->string('cod_contor');
            $table->string('nivel')->default('spatiu');
            $table->boolean('activ')->default(true);
            $table->text('observatii')->nullable();
            $table->timestamps();
        });

        Schema::create('citiri_contoare', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contor_id')->constrained('contoare')->cascadeOnDelete();
            $table->string('luna', 7);
            $table->decimal('index_vechi', 14, 3)->default(0);
            $table->decimal('index_nou', 14, 3)->default(0);
            $table->decimal('consum', 14, 3)->default(0);
            $table->string('fisier_path')->nullable();
            $table->string('fisier_nume')->nullable();
            $table->text('observatii')->nullable();
            $table->timestamps();
        });

        Schema::create('utilitati_lunare', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imobil_id')->constrained('imobile')->cascadeOnDelete();
            $table->string('luna', 7);
            $table->string('tip_utilitate');
            $table->decimal('cantitate', 14, 3)->nullable();
            $table->decimal('cost_total', 14, 2)->default(0);
            $table->decimal('pret_unitar', 14, 4)->nullable();
            $table->boolean('aprobat')->default(false);
            $table->text('observatii')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilitati_lunare');
        Schema::dropIfExists('citiri_contoare');
        Schema::dropIfExists('contoare');
    }
};
