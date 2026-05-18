<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolygonDividend extends Model
{
    protected $fillable = [
        'polygon_id',
        'ticker',
        'cash_amount',
        'currency',
        'declaration_date',
        'dividend_type',
        'ex_dividend_date',
        'frequency',
        'pay_date',
        'record_date',
    ];

    public function company(): BelongsTo
    {
        // Link by ticker (Polygon payload has ticker, we keep schema minimal).
        return $this->belongsTo(Company::class, 'ticker', 'ticker');
    }
}

