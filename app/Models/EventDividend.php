<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventDividend extends Model
{
    protected $fillable = [
        'company_id',
        'symbol',
        'ex_dividend_date',
        'declaration_date',
        'record_date',
        'payment_date',
        'amount',
        'source',
        'source_hash',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

