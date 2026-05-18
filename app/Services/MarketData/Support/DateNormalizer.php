<?php

namespace App\Services\MarketData\Support;

use Carbon\Carbon;

class DateNormalizer
{
    /**
     * Normalize various date formats to Y-m-d where possible.
     * Falls back to the original string if parsing fails.
     */
    public static function toYmd(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Common API formats we already see in the Python collector.
        $formats = [
            'Y-m-d',
            'd.m.Y',
            'd/m/Y',
            'm/d/Y',
            'd-m-Y',
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s\Z',
        ];

        foreach ($formats as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $raw)->format('Y-m-d');
            } catch (\Throwable) {
                // try next
            }
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return $raw;
        }
    }
}

