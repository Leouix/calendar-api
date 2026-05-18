<?php

namespace App\Services\MarketData\Import;

use App\Models\Company;
use App\Models\EventEarning;
use App\Models\EventImportLog;
use App\Services\MarketData\Providers\FinnhubClient;
use App\Services\MarketData\Support\DateNormalizer;
use App\Services\MarketData\Support\SourceHash;
use Carbon\Carbon;

class FinnhubEarningsImporter
{
    private const SOURCE = 'finnhub';

    public function __construct(private readonly FinnhubClient $client)
    {
    }

    public function importCompany(Company $company, ?int $year = null): int
    {
        $ticker = (string) $company->ticker;

        if (!$this->client->hasKey()) {
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'skip',
                'message' => 'FINNHUB_API_KEY not set',
            ]);
            return 0;
        }

        $y = $year ?? (int) Carbon::now()->format('Y');
        $from = "{$y}-01-01";
        $to = "{$y}-12-31";

        try {
            $res = $this->client->earningsCalendar($ticker, $from, $to);
        } catch (\Throwable $e) {
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'error',
                'message' => get_class($e) . ': ' . $e->getMessage(),
            ]);
            return 0;
        }

        if ($res->status() === 429) {
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'error',
                'message' => 'rate_limited (HTTP 429)',
            ]);
            return 0;
        }

        if (!$res->successful()) {
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'error',
                'message' => 'http_error ' . $res->status(),
            ]);
            return 0;
        }

        $data = $res->json();
        $items = is_array($data) ? ($data['earningsCalendar'] ?? []) : [];
        if (!is_array($items)) {
            $items = [];
        }

        $count = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $date = DateNormalizer::toYmd(isset($item['date']) ? (string) $item['date'] : null);
            if (!$date) {
                continue;
            }

            $hash = SourceHash::earnings((int) $company->id, 'earnings', $date, self::SOURCE);

            // Title isn't meaningful for you right now; keep a stable non-empty value to satisfy schema/UI.
            EventEarning::updateOrCreate(
                ['source_hash' => $hash],
                [
                    'company_id' => $company->id,
                    'title' => (string) $company->ticker,
                    'event_date' => $date,
                    'payload_json' => $item,
                    'source' => self::SOURCE,
                ],
            );
            $count++;
        }

        EventImportLog::create([
            'provider' => self::SOURCE,
            'ticker' => $ticker,
            'status' => 'ok',
            'message' => "earnings: {$count}",
        ]);

        return $count;
    }
}

