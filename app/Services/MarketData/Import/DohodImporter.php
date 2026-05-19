<?php

namespace App\Services\MarketData\Import;

use App\Models\Company;
use App\Models\EventDividend;
use App\Models\EventImportLog;
use App\Services\MarketData\Providers\DohodClient;
use App\Services\MarketData\Support\DateNormalizer;
use App\Services\MarketData\Support\SourceHash;
use DOMDocument;
use DOMXPath;

class DohodImporter
{
    private const SOURCE = 'dohod';

    public function __construct(private readonly DohodClient $client)
    {
    }

    public function importCompany(Company $company, bool $throttle = true): array
    {
        $ticker = (string) $company->ticker;

        $dividendsCount = $this->importDividends($company);

        EventImportLog::create([
            'provider' => self::SOURCE,
            'ticker' => $ticker,
            'status' => 'ok',
            'message' => "dividends: {$dividendsCount}",
        ]);

        return ['dividends' => $dividendsCount];
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
        $rows = $xpath->query("//table[contains(@class, 'dividend-table')]//tbody/tr");

        if ($rows === false || $rows->length === 0) {
            return null;
        }

        $result = [];
        foreach ($rows as $row) {
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
}
