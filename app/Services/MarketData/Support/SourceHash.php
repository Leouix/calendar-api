<?php

namespace App\Services\MarketData\Support;

class SourceHash
{
    /**
     * Matches collector/providers/base.py EventDTO::dedup_hash for earnings.
     *
     * raw = f"{company_id}{event_type}{event_date}{source}"
     */
    public static function earnings(int $companyId, string $eventType, string $eventDate, string $source): string
    {
        return sha1("{$companyId}{$eventType}{$eventDate}{$source}");
    }

    /**
     * Matches collector/db.py dividend dedup raw string.
     *
     * raw = f"{company_id}|{symbol or ''}|{declaration_date or ''}|{ex_dividend_date or ''}|{record_date or ''}|{payment_date or ''}|{amount or ''}|{source}"
     */
    public static function dividend(
        int $companyId,
        ?string $symbol,
        ?string $declarationDate,
        ?string $exDividendDate,
        ?string $recordDate,
        ?string $paymentDate,
        ?string $amount,
        string $source
    ): string {
        $symbol = $symbol ?? '';
        $declarationDate = $declarationDate ?? '';
        $exDividendDate = $exDividendDate ?? '';
        $recordDate = $recordDate ?? '';
        $paymentDate = $paymentDate ?? '';
        $amount = $amount ?? '';

        return sha1("{$companyId}|{$symbol}|{$declarationDate}|{$exDividendDate}|{$recordDate}|{$paymentDate}|{$amount}|{$source}");
    }
}

