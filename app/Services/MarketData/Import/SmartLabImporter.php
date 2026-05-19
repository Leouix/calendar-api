<?php

namespace App\Services\MarketData\Import;

use App\Models\Company;
use App\Models\EventDividend;
use App\Models\EventEarning;
use App\Models\EventImportLog;
use App\Services\MarketData\Providers\SmartLabClient;
use App\Services\MarketData\Support\DateNormalizer;
use App\Services\MarketData\Support\SourceHash;
use DOMDocument;
use DOMXPath;

class SmartLabImporter
{
    private const SOURCE = 'smartlab';

    public function __construct(private readonly SmartLabClient $client)
    {
    }

    public function importCompany(Company $company, bool $throttle = true): array
    {
        $ticker = (string) $company->ticker;

        $earningsCount = $this->importEarnings($company);
        if ($throttle) {
            usleep(350_000);
        }

        $dividendsCount = $this->importDividends($company);

        EventImportLog::create([
            'provider' => self::SOURCE,
            'ticker' => $ticker,
            'status' => 'ok',
            'message' => "dividends: {$dividendsCount}, earnings: {$earningsCount}",
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

        $items = $this->parseEarningsHtml($res->body());
        if ($items === null) {
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'error',
                'message' => 'earnings parse_failed',
            ]);
            return 0;
        }

        $count = 0;
        foreach ($items as $item) {
            $date = DateNormalizer::toYmd($item['date'] ?? null);
            if (!$date) {
                continue;
            }

            $hash = SourceHash::earnings((int) $company->id, 'earnings', $date, self::SOURCE);

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

        $items = $this->parseDividendHtml($res->body());
        if ($items === null) {
            EventImportLog::create([
                'provider' => self::SOURCE,
                'ticker' => $ticker,
                'status' => 'error',
                'message' => 'dividends parse_failed',
            ]);
            return 0;
        }

        $count = 0;
        foreach ($items as $item) {
            $exDate = DateNormalizer::toYmd($item['date'] ?? null);
            if (!$exDate) {
                continue;
            }
            $amount = isset($item['amount']) ? trim((string) $item['amount']) : null;
            if ($amount === '') {
                $amount = null;
            }

            $hash = SourceHash::dividend(
                (int) $company->id,
                $ticker,
                null,
                $exDate,
                null,
                null,
                $amount,
                self::SOURCE,
            );

            EventDividend::updateOrCreate(
                ['source_hash' => $hash],
                [
                    'company_id' => $company->id,
                    'symbol' => $ticker,
                    'ex_dividend_date' => $exDate,
                    'amount' => $amount,
                    'source' => self::SOURCE,
                ],
            );
            $count++;
        }

        return $count;
    }

    private function parseDividendHtml(string $html): ?array
    {
        $internalErrors = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_use_internal_errors($internalErrors);

        $xpath = new DOMXPath($doc);
        $rows = $xpath->query("//table[contains(@class, 'dividend_history')]//tr");

        if ($rows === false || $rows->length === 0) {
            return null;
        }

        $result = [];
        foreach ($rows as $i => $row) {
            if ($i === 0) {
                continue;
            }

            $cells = $xpath->query('./td', $row);
            if ($cells === false || $cells->length < 2) {
                continue;
            }

            $date = trim($cells->item(0)->textContent);
            $amount = trim($cells->item(1)->textContent);

            if ($date === '') {
                continue;
            }

            $result[] = [
                'date' => $date,
                'amount' => $amount,
            ];
        }

        return $result;
    }

    private function parseEarningsHtml(string $html): ?array
    {
        $internalErrors = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_use_internal_errors($internalErrors);

        $xpath = new DOMXPath($doc);
        $tables = $xpath->query("//table[contains(@class, 'financial_results') or contains(@class, 'report_table')]");

        if ($tables === false || $tables->length === 0) {
            $tables = $xpath->query("//table");
            if ($tables === false || $tables->length === 0) {
                return null;
            }
        }

        $result = [];
        foreach ($tables as $table) {
            $rows = $xpath->query('.//tr', $table);
            if ($rows === false || $rows->length < 2) {
                continue;
            }

            foreach ($rows as $i => $row) {
                if ($i === 0) {
                    continue;
                }

                $cells = $xpath->query('./td | ./th', $row);
                if ($cells === false || $cells->length === 0) {
                    continue;
                }

                $firstCell = trim($cells->item(0)->textContent);
                if ($firstCell === '') {
                    continue;
                }

                // Collect all cells for payload
                $rowData = [];
                foreach ($cells as $cell) {
                    $rowData[] = trim($cell->textContent);
                }

                // Use first cell as date if it looks like a date or year
                $result[] = [
                    'date' => $firstCell,
                    'values' => $rowData,
                ];
            }
        }

        return $result;
    }
}
