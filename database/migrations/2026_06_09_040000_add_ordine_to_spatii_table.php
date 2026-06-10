<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spatii', function (Blueprint $table): void {
            $table->unsignedInteger('ordine')->default(0)->after('imobil_id');
        });

        DB::table('spatii')
            ->select('id', 'imobil_id')
            ->orderBy('imobil_id')
            ->orderBy('id')
            ->get()
            ->groupBy('imobil_id')
            ->each(function ($spatii, $imobilId): void {
                foreach ($spatii->values() as $index => $spatiu) {
                    DB::table('spatii')->where('id', $spatiu->id)->update([
                        'ordine' => $index + 1,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('spatii', function (Blueprint $table): void {
            $table->dropColumn('ordine');
        });
    }
};
