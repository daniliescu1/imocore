<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'actiune',
        'camp',
        'valoare_veche',
        'valoare_noua',
        'motiv',
        'user_name',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
