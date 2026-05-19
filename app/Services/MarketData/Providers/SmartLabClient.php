<?php

namespace App\Services\MarketData\Providers;

use App\ClientProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SmartLabClient implements ClientProviderInterface
{
    private const BASE_URL = 'https://smart-lab.ru';

    public function hasKey(): bool
    {
        return true;
    }

    public function dividends(string $ticker): Response
    {
        return Http::timeout(15)
            ->withUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36')
            ->get(self::BASE_URL . '/q/' . strtoupper($ticker) . '/dividend/');
    }

    public function earnings(string $ticker): Response
    {
        return Http::timeout(15)
            ->withUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36')
            ->get(self::BASE_URL . '/q/' . strtoupper($ticker) . '/fyy/');
    }
}
