<?php

namespace App\Services\MarketData\Providers;

use App\ClientProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

readonly class FinnhubClient implements ClientProviderInterface
{
    public function __construct(private string $apiKey) {}

    public function hasKey(): bool
    {
        return $this->apiKey !== '';
    }

    public function earningsCalendar(string $ticker, string $from, string $to): Response
    {
        return Http::timeout(15)->get('https://finnhub.io/api/v1/calendar/earnings', [
            'symbol' => strtoupper($ticker),
            'from' => $from,
            'to' => $to,
            'token' => $this->apiKey,
        ]);
    }
}

