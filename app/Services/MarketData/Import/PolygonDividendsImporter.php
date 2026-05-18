<?php

namespace App\Services\MarketData\Import;

use App\Models\Company;
use App\Models\EventImportLog;
use App\Models\PolygonDividend;
use App\Services\MarketData\Providers\PolygonClient;
use App\Services\MarketData\Support\DateNormalizer;
use Illuminate\Http\Client\Response;

class PolygonDividendsImporter
{
    private const SOURCE = 'polygon';

    public function __construct(private readonly PolygonClient $client)
    {
    }

    public function importCompany(Company $company, bool $throttle = true): int
    {
        $ticker = (string) $company->ticker;

        if (!$this->client->hasKey()) {
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'skip',
                'message' => 'POLYGON_API_KEY not set',
            ]);
            return 0;
        }

        $count = 0;
        $pages = 0;
        $maxPages = 30;
        $status = 'ok';
        $statusMessage = null;

        try {
            $res = $this->requestWithRetry(
                fn () => $this->client->dividendsReference($ticker),
                $ticker,
                $throttle,
                'initial',
            );
        } catch (RateLimitedException $e) {
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'error',
                'message' => 'rate_limited (HTTP 429); dividends: 0, pages: 0',
            ]);
            throw $e;
        } catch (\Throwable $e) {
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'error',
                'message' => get_class($e) . ': ' . $e->getMessage(),
            ]);
            return 0;
        }

        while (true) {
            $pages++;
            if ($pages > $maxPages) {
                $status = 'error';
                $statusMessage = "pagination_overflow (>{$maxPages} pages)";
                break;
            }

            if (!$res->successful()) {
                $status = 'error';
                $statusMessage = 'http_error ' . $res->status();
                break;
            }

            $data = $res->json();
            $items = is_array($data) ? ($data['results'] ?? []) : [];
            if (!is_array($items)) {
                $items = [];
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $polygonId = isset($item['id']) ? trim((string) $item['id']) : '';
                if ($polygonId === '') {
                    // Without a stable id, we'd risk de-dupe collisions. Skip.
                    continue;
                }

                $rowTicker = isset($item['ticker']) ? strtoupper(trim((string) $item['ticker'])) : strtoupper($ticker);
                $cashAmount = array_key_exists('cash_amount', $item) && $item['cash_amount'] !== null ? (string) $item['cash_amount'] : null;

                PolygonDividend::updateOrCreate(
                    ['polygon_id' => $polygonId],
                    [
                        'polygon_id' => $polygonId,
                        'ticker' => $rowTicker ?: null,
                        'cash_amount' => $cashAmount,
                        'currency' => isset($item['currency']) ? (string) $item['currency'] : null,
                        'declaration_date' => DateNormalizer::toYmd(isset($item['declaration_date']) ? (string) $item['declaration_date'] : null),
                        'dividend_type' => isset($item['dividend_type']) ? (string) $item['dividend_type'] : null,
                        'ex_dividend_date' => DateNormalizer::toYmd(isset($item['ex_dividend_date']) ? (string) $item['ex_dividend_date'] : null),
                        'frequency' => isset($item['frequency']) ? (is_numeric($item['frequency']) ? (int) $item['frequency'] : null) : null,
                        'pay_date' => DateNormalizer::toYmd(isset($item['pay_date']) ? (string) $item['pay_date'] : null),
                        'record_date' => DateNormalizer::toYmd(isset($item['record_date']) ? (string) $item['record_date'] : null),
                    ],
                );
                $count++;
            }

            $nextUrl = is_array($data) ? ($data['next_url'] ?? null) : null;
            if (!is_string($nextUrl) || trim($nextUrl) === '') {
                break;
            }

            try {
                $res = $this->requestWithRetry(
                    fn () => $this->client->getNext($nextUrl),
                    $ticker,
                    $throttle,
                    'next_url',
                );
            } catch (RateLimitedException $e) {
                $status = 'error';
                $statusMessage = 'rate_limited (HTTP 429)';
                break;
            } catch (\Throwable $e) {
                $status = 'error';
                $statusMessage = 'next_url ' . get_class($e) . ': ' . $e->getMessage();
                break;
            }
        }

        EventImportLog::create([
            'provider' => self::SOURCE,
            'ticker' => $ticker,
            'status' => $status,
            'message' => ($statusMessage ? ($statusMessage . '; ') : '') . "dividends: {$count}, pages: {$pages}",
        ]);

        return $count;
    }

    /**
     * Retries Polygon requests on HTTP 429 with exponential backoff.
     * Throws RateLimitedException if exhausted.
     */
    private function requestWithRetry(callable $fn, string $ticker, bool $throttle, string $context): Response
    {
        $attempt = 0;
        $maxAttempts = 6;
        $sleepSec = 2;

        while (true) {
            $attempt++;
            /** @var Response $res */
            $res = $fn();

            if ($res->status() !== 429) {
                if ($throttle) {
                    // Small spacing even on success to avoid bursting.
                    usleep(350_000);
                }
                return $res;
            }

            if ($attempt >= $maxAttempts) {
                EventImportLog::create([
                    'provider' => self::SOURCE,
                    'ticker' => $ticker,
                    'status' => 'error',
                    'message' => "rate_limited (HTTP 429) during {$context}; attempts: {$attempt}",
                ]);
                throw new RateLimitedException("Polygon rate limited during {$context}");
            }

            $retryAfter = $res->header('Retry-After');
            $wait = is_string($retryAfter) && is_numeric($retryAfter) ? (int) $retryAfter : $sleepSec;
            $wait = max(1, min(120, $wait));

            sleep($wait);
            $sleepSec = min(120, $sleepSec * 2);
        }
    }
}
