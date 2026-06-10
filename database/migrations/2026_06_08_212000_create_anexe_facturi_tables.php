<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anexe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracte')->cascadeOnDelete();
            $table->string('luna', 7);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('anexa_linii', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anexa_id')->constrained('anexe')->cascadeOnDelete();
            $table->string('denumire');
            $table->decimal('cantitate', 14, 3)->nullable();
            $table->decimal('pret_unitar', 14, 4)->nullable();
            $table->decimal('valoare', 14, 2)->default(0);
            $table->text('observatii')->nullable();
            $table->timestamps();
        });

        Schema::create('facturi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anexa_id')->constrained('anexe')->cascadeOnDelete();
            $table->string('numar_factura')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->string('status')->default('draft');
            $table->string('email_chirias')->nullable();
            $table->timestamp('trimis_la')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturi');
        Schema::dropIfExists('anexa_linii');
        Schema::dropIfExists('anexe');
    }
};
