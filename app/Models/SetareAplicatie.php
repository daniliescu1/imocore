<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SetareAplicatie extends Model
{
    protected $table = 'setari_aplicatie';

    protected $fillable = [
        'cheie',
        'valoare',
    ];

    public static function valoare(string $cheie, mixed $implicit = null): mixed
    {
        return static::query()->where('cheie', $cheie)->value('valoare') ?? $implicit;
    }

    public static function seteaza(string $cheie, mixed $valoare): void
    {
        static::query()->updateOrCreate(
            ['cheie' => $cheie],
            ['valoare' => (string) $valoare]
        );
    }
}
