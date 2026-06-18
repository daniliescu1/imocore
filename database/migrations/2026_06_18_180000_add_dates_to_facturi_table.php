<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturi', function (Blueprint $table) {
            $table->date('data_emitere')->nullable()->after('numar_factura');
            $table->date('data_scadenta')->nullable()->after('data_emitere');
        });

        DB::table('facturi')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $factura): void {
                $dataEmitere = $factura->created_at
                    ? date('Y-m-d', strtotime((string) $factura->created_at))
                    : now()->toDateString();

                DB::table('facturi')
                    ->where('id', $factura->id)
                    ->update([
                        'data_emitere' => $dataEmitere,
                        'data_scadenta' => date('Y-m-d', strtotime($dataEmitere.' +5 days')),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('facturi', function (Blueprint $table) {
            $table->dropColumn(['data_emitere', 'data_scadenta']);
        });
    }
};
