<?php

namespace App\Services\MarketData\Providers;

use App\ClientProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PolygonClient implements ClientProviderInterface
{
    private const BASE_URL = 'https://api.polygon.io';
    private const PAGE_LIMIT = 1000;

    public function __construct(private readonly string $apiKey)
    {
    }

    public function hasKey(): bool
    {
        return $this->apiKey !== '';
    }

    public function dividendsReference(string $ticker): Response
    {
        return Http::timeout(15)->get(self::BASE_URL . '/v3/reference/dividends', [
            'ticker' => strtoupper($ticker),
            // Reduce pagination requests; Polygon supports large limits (plan-dependent).
            'limit' => self::PAGE_LIMIT,
            'apiKey' => $this->apiKey,
        ]);
    }

    /**
     * Polygon pagination returns a next_url that may not include apiKey.
     * We always enforce apiKey on the request.
     */
    public function getNext(string $nextUrl): Response
    {
        $url = $this->ensureApiKey($nextUrl);

        return Http::timeout(15)->get($url);
    }

    private function ensureApiKey(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        // If it's a relative URL, treat it as polygon host path.
        if (str_starts_with($url, '/')) {
            $url = self::BASE_URL . $url;
        }

        // Append apiKey if missing.
        $hasApiKey = str_contains($url, 'apiKey=');
        if ($hasApiKey) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'apiKey=' . urlencode($this->apiKey);
    }
}
