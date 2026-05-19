<?php

namespace App\Services\MarketData\Import;

use App\Models\Company;
use App\Models\MoexDividend;
use App\Models\EventImportLog;
use App\Services\MarketData\Providers\MoexClient;
use App\Services\MarketData\Support\DateNormalizer;
use App\Services\MarketData\Support\SourceHash;

class MoexImporter
{
    private const SOURCE = 'moex';

    public function __construct(private readonly MoexClient $client)
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

        $items = $this->parseDividendJson($res->json());
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
            if ($amount === '' || $amount === '0') {
                continue;
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

            MoexDividend::updateOrCreate(
                ['source_hash' => $hash],
                [
                    'company_id' => $company->id,
                    'symbol' => $ticker,
                    'ex_dividend_date' => $exDate,
                    'amount' => $amount,
                ],
            );
            $count++;
        }

        return $count;
    }

    private function parseDividendJson(?array $body): ?array
    {
        if ($body === null || !isset($body['dividends'])) {
            return null;
        }

        $dividends = $body['dividends'];

        if (!isset($dividends['columns'], $dividends['data']) || !is_array($dividends['data'])) {
            return null;
        }

        $columns = $dividends['columns'];
        $data = $dividends['data'];

        if (empty($data)) {
            return [];
        }

        $colMap = [];
        foreach ($columns as $i => $col) {
            $colMap[$col] = $i;
        }

        $result = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $amount = $row[$colMap['value_rub'] ?? $colMap['value'] ?? null] ?? null;
            $rawDate = $row[$colMap['registryclosedate'] ?? null] ?? null;

            if ($amount === null || $rawDate === null) {
                continue;
            }

            $result[] = [
                'date' => $rawDate,
                'amount' => $amount,
            ];
        }

        return $result;
    }
}
