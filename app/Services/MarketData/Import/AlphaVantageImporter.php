<?php

namespace App\Services\MarketData\Import;

use App\Models\Company;
use App\Models\EventDividend;
use App\Models\EventEarning;
use App\Models\EventImportLog;
use App\Services\MarketData\Providers\AlphaVantageClient;
use App\Services\MarketData\Support\DateNormalizer;
use App\Services\MarketData\Support\SourceHash;

class AlphaVantageImporter
{
    private const SOURCE = 'alphavantage';

    public function __construct(private readonly AlphaVantageClient $client)
    {
    }

    public function importCompany(Company $company, bool $doEarnings = true, bool $doDividends = true, bool $throttle = true): array
    {
        $ticker = (string) $company->ticker;

        if (!$this->client->hasKey()) {
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'skip',
                'message' => 'ALPHAVANTAGE_API_KEY not set',
            ]);
            return ['earnings' => 0, 'dividends' => 0];
        }

        $earningsCount = 0;
        $dividendsCount = 0;

        if ($doEarnings) {
            $earningsCount = $this->importEarnings($company);
            if ($throttle) {
                sleep(12);
            }
        }

        if ($doDividends) {
            $dividendsCount = $this->importDividends($company);
            if ($throttle) {
                sleep(12);
            }
        }

        EventImportLog::create([
            'provider' => self::SOURCE,
            'ticker' => $ticker,
            'status' => 'ok',
            'message' => "earnings: {$earningsCount}, dividends: {$dividendsCount}",
        ]);

        return ['earnings' => $earningsCount, 'dividends' => $dividendsCount];
    }

    private function importEarnings(Company $company): int
    {
        $ticker = (string) $company->ticker;

        try {
            $res = $this->client->earnings($ticker);
        } catch (\Throwable $e) {
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'error',
                'message' => 'earnings ' . get_class($e) . ': ' . $e->getMessage(),
            ]);
            return 0;
        }

        if (!$res->successful()) {
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'error',
                'message' => 'earnings http_error ' . $res->status(),
            ]);
            return 0;
        }

        $data = $res->json();
        if (!is_array($data) || !isset($data['quarterlyEarnings']) || !is_array($data['quarterlyEarnings'])) {
            $note = is_array($data) ? ($data['Note'] ?? $data['Information'] ?? null) : null;
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'error',
                'message' => 'earnings unexpected_response' . ($note ? (': ' . (string) $note) : ''),
            ]);
            return 0;
        }

        $count = 0;
        foreach ($data['quarterlyEarnings'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $date = DateNormalizer::toYmd(isset($item['reportedDate']) ? (string) $item['reportedDate'] : null);
            if (!$date) {
                continue;
            }

            $hash = SourceHash::earnings((int) $company->id, 'earnings', $date, self::SOURCE);

            // No title computations; keep stable non-empty value for the current schema.
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

        return $count;
    }

    private function importDividends(Company $company): int
    {
        $ticker = (string) $company->ticker;

        try {
            $res = $this->client->dividends($ticker);
        } catch (\Throwable $e) {
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'error',
                'message' => 'dividends ' . get_class($e) . ': ' . $e->getMessage(),
            ]);
            return 0;
        }

        if (!$res->successful()) {
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'error',
                'message' => 'dividends http_error ' . $res->status(),
            ]);
            return 0;
        }

        $data = $res->json();
        if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
            $note = is_array($data) ? ($data['Note'] ?? $data['Information'] ?? null) : null;
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'error',
                'message' => 'dividends unexpected_response' . ($note ? (': ' . (string) $note) : ''),
            ]);
            return 0;
        }

        $symbol = isset($data['symbol']) && is_string($data['symbol']) ? $data['symbol'] : $ticker;

        $count = 0;
        foreach ($data['data'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $ex = DateNormalizer::toYmd(isset($item['ex_dividend_date']) ? (string) $item['ex_dividend_date'] : null);
            $decl = DateNormalizer::toYmd(isset($item['declaration_date']) ? (string) $item['declaration_date'] : null);
            $rec = DateNormalizer::toYmd(isset($item['record_date']) ? (string) $item['record_date'] : null);
            $pay = DateNormalizer::toYmd(isset($item['payment_date']) ? (string) $item['payment_date'] : null);
            $amount = isset($item['amount']) ? (string) $item['amount'] : null;

            $hash = SourceHash::dividend(
                (int) $company->id,
                $symbol,
                $decl,
                $ex,
                $rec,
                $pay,
                $amount,
                self::SOURCE,
            );

            EventDividend::updateOrCreate(
                ['source_hash' => $hash],
                [
                    'company_id' => $company->id,
                    'symbol' => $symbol,
                    'ex_dividend_date' => $ex,
                    'declaration_date' => $decl,
                    'record_date' => $rec,
                    'payment_date' => $pay,
                    'amount' => $amount,
                    'source' => self::SOURCE,
                ],
            );
            $count++;
        }

        return $count;
    }
}

