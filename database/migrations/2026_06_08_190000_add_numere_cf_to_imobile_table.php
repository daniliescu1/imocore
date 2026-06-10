<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imobile', function (Blueprint $table) {
            $table->json('numere_cf')->nullable()->after('cod_postal');
        });

        DB::table('imobile')
            ->whereNotNull('numar_cf')
            ->orderBy('id')
            ->get(['id', 'numar_cf'])
            ->each(function (object $imobil): void {
                if ($imobil->numar_cf === '') {
                    return;
                }

                DB::table('imobile')
                    ->where('id', $imobil->id)
                    ->update([
                        'numere_cf' => json_encode([
                            [
                                'numar' => $imobil->numar_cf,
                                'observatii' => '',
                            ],
                        ]),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('imobile', function (Blueprint $table) {
            $table->dropColumn('numere_cf');
        });
    }
};
