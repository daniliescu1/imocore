<?php

namespace Database\Seeders;

use App\Models\Imobil;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Owner',
            'email' => 'owner@imocore.local',
        ]);

        Imobil::query()->firstOrCreate(
            ['nume' => 'Dumbrăvița Office Conac 60'],
            [
                'strada' => 'Str. Conac',
                'numar' => '60',
                'localitate' => 'Dumbrăvița',
                'judet' => 'Timiș',
                'numere_cf' => [
                    ['numar' => '123456', 'observatii' => 'CF principal'],
                ],
                'spatii_total' => 60,
                'spatii_libere' => 6,
                'spatii_inchiriate' => 52,
                'spatii_comune' => 2,
            ]
        );

        Imobil::query()->firstOrCreate(
            ['nume' => '700 Office Gheorghe Lazăr 9'],
            [
                'strada' => 'Str. Gheorghe Lazăr',
                'numar' => '9',
                'localitate' => 'Timișoara',
                'judet' => 'Timiș',
                'numere_cf' => [
                    ['numar' => '234567', 'observatii' => 'CF principal'],
                ],
                'spatii_total' => 187,
                'spatii_libere' => 12,
                'spatii_inchiriate' => 171,
                'spatii_comune' => 4,
            ]
        );

        $this->call(LocalRestoreSeeder::class);
    }
}