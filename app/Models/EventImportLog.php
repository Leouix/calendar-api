<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventImportLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'provider',
        'ticker',
        'status',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
