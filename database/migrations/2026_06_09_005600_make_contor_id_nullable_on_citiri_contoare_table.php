<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citiri_contoare_temp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contor_id')->nullable()->constrained('contoare')->cascadeOnDelete();
            $table->foreignId('configurare_anexa_linie_id')->nullable()->constrained('configurare_anexa_linii')->cascadeOnDelete();
            $table->string('luna', 7);
            $table->decimal('index_vechi', 14, 3)->default(0);
            $table->decimal('index_nou', 14, 3)->default(0);
            $table->decimal('consum', 14, 3)->default(0);
            $table->string('fisier_path')->nullable();
            $table->string('fisier_nume')->nullable();
            $table->text('observatii')->nullable();
            $table->timestamps();
        });

        DB::statement('
            INSERT INTO citiri_contoare_temp (
                id, contor_id, configurare_anexa_linie_id, luna, index_vechi, index_nou, consum,
                fisier_path, fisier_nume, observatii, created_at, updated_at
            )
            SELECT
                id, contor_id, configurare_anexa_linie_id, luna, index_vechi, index_nou, consum,
                fisier_path, fisier_nume, observatii, created_at, updated_at
            FROM citiri_contoare
        ');

        Schema::drop('citiri_contoare');
        Schema::rename('citiri_contoare_temp', 'citiri_contoare');
    }

    public function down(): void
    {
        Schema::create('citiri_contoare_temp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contor_id')->constrained('contoare')->cascadeOnDelete();
            $table->foreignId('configurare_anexa_linie_id')->nullable()->constrained('configurare_anexa_linii')->cascadeOnDelete();
            $table->string('luna', 7);
            $table->decimal('index_vechi', 14, 3)->default(0);
            $table->decimal('index_nou', 14, 3)->default(0);
            $table->decimal('consum', 14, 3)->default(0);
            $table->string('fisier_path')->nullable();
            $table->string('fisier_nume')->nullable();
            $table->text('observatii')->nullable();
            $table->timestamps();
        });

        DB::statement('
            INSERT INTO citiri_contoare_temp (
                id, contor_id, configurare_anexa_linie_id, luna, index_vechi, index_nou, consum,
                fisier_path, fisier_nume, observatii, created_at, updated_at
            )
            SELECT
                id, contor_id, configurare_anexa_linie_id, luna, index_vechi, index_nou, consum,
                fisier_path, fisier_nume, observatii, created_at, updated_at
            FROM citiri_contoare
            WHERE contor_id IS NOT NULL
        ');

        Schema::drop('citiri_contoare');
        Schema::rename('citiri_contoare_temp', 'citiri_contoare');
    }
};
