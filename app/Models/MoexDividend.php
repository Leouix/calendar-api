<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoexDividend extends Model
{
    protected $fillable = [
        'company_id',
        'symbol',
        'ex_dividend_date',
        'amount',
        'source_hash',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
