<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rezervari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spatiu_id')->constrained('spatii')->cascadeOnDelete();
            $table->string('prospect');
            $table->decimal('garantie', 12, 2)->nullable();
            $table->string('moneda', 3)->default('EUR');
            $table->date('data_rezervare')->nullable();
            $table->date('termen_semnare')->nullable();
            $table->boolean('garantie_incasata')->default(false);
            $table->string('status')->default('activa');
            $table->text('observatii')->nullable();
            $table->timestamps();
        });

        Schema::create('contracte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spatiu_id')->constrained('spatii')->cascadeOnDelete();
            $table->string('numar_contract');
            $table->string('chirias');
            $table->date('data_start');
            $table->date('data_end')->nullable();
            $table->decimal('chirie', 12, 2)->default(0);
            $table->string('moneda', 3)->default('EUR');
            $table->string('status')->default('activ');
            $table->text('observatii')->nullable();
            $table->timestamps();
        });

        Schema::create('pv_predare', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spatiu_id')->constrained('spatii')->cascadeOnDelete();
            $table->string('tip')->default('predare');
            $table->date('data_pv');
            $table->string('status')->default('draft');
            $table->text('observatii')->nullable();
            $table->timestamps();
        });

        Schema::create('pv_contor_initial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pv_predare_id')->constrained('pv_predare')->cascadeOnDelete();
            $table->string('tip_utilitate');
            $table->string('cod_contor');
            $table->decimal('index_initial', 14, 3);
            $table->string('fisier_path')->nullable();
            $table->string('fisier_nume')->nullable();
            $table->text('observatii')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pv_contor_initial');
        Schema::dropIfExists('pv_predare');
        Schema::dropIfExists('contracte');
        Schema::dropIfExists('rezervari');
    }
};
