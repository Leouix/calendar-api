<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'ticker',
        'name',
        'country',
        'exchange',
        'sector',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function events(): HasMany
    {
        // Back-compat alias. The domain model is now earnings-only.
        return $this->eventEarnings();
    }

    public function eventEarnings(): HasMany
    {
        return $this->hasMany(EventEarning::class);
    }
}
