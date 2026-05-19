<?php

namespace App\Services\MarketData\Providers;

use App\ClientProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class DohodClient implements ClientProviderInterface
{
    private const BASE_URL = 'https://www.dohod.ru';

    public function hasKey(): bool
    {
        return true;
    }

    public function dividends(string $ticker): Response
    {
        return Http::timeout(15)
            ->withUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36')
            ->get(self::BASE_URL . '/ik/analytics/dividend', [
                'q' => strtoupper($ticker),
            ]);
    }
}
