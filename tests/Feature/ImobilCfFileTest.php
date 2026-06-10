<?php

namespace Tests\Feature;

use App\Models\Imobil;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImobilCfFileTest extends TestCase
{
    public function test_cf_file_can_be_opened_inline_and_downloaded(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('imobile/cf/cf-test.pdf', 'test file');

        $imobil = Imobil::query()->create([
            'nume' => 'Imobil test',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
            'numere_cf' => [
                [
                    'numar' => '123',
                    'observatii' => 'Test',
                    'poza_path' => 'imobile/cf/cf-test.pdf',
                    'poza_nume' => 'CF Test.pdf',
                ],
            ],
        ]);

        $this->get(route('imobile.cf.view', [$imobil, 0]))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename="CF Test.pdf"');

        $this->get(route('imobile.cf.download', [$imobil, 0]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="CF Test.pdf"');
    }
}
