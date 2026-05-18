<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventEarning extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'event_date',
        'payload_json',
        'source',
        'source_hash',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date:Y-m-d',
            'payload_json' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

