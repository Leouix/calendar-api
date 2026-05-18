<?php

namespace App\Services\MarketData\Providers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AlphaVantageClient
{
    private const BASE_URL = 'https://www.alphavantage.co/query';

    public function __construct(private readonly string $apiKey)
    {
    }

    public function hasKey(): bool
    {
        return $this->apiKey !== '';
    }

    public function earnings(string $ticker): Response
    {
        return Http::timeout(15)->get(self::BASE_URL, [
            'function' => 'EARNINGS',
            'symbol' => strtoupper($ticker),
            'apikey' => $this->apiKey,
        ]);
    }

    public function dividends(string $ticker): Response
    {
        return Http::timeout(15)->get(self::BASE_URL, [
            'function' => 'DIVIDENDS',
            'symbol' => strtoupper($ticker),
            'apikey' => $this->apiKey,
        ]);
    }
}

