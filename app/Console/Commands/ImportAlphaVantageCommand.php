<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\MarketData\Import\AlphaVantageImporter;
use App\Services\MarketData\Providers\AlphaVantageClient;
use Illuminate\Console\Command;

class ImportAlphaVantageCommand extends Command
{
    protected $signature = 'app:alpha
        {--ticker= : Single ticker from companies table}
        {--all : Import for all active companies (default if --ticker is omitted)}
        {--earnings : Import earnings (default)}
        {--dividends : Import dividends (default)}
        {--no-throttle : Do not sleep between AlphaVantage calls}';

    protected $description = 'Import earnings/dividends from Alpha Vantage into event_earnings/event_dividends';

    protected $aliases = ['app:alphavantage', 'app:av'];

    public function handle(): int
    {
        $tickerOpt = $this->option('ticker');

        $companies = collect();
        if (is_string($tickerOpt) && $tickerOpt !== '') {
            $ticker = strtoupper(trim($tickerOpt));
            $company = Company::query()->where('ticker', $ticker)->first();
            if (!$company) {
                $this->error("Company not found in DB: {$ticker}");
                return self::FAILURE;
            }
            $companies = collect([$company]);
        } else {
            $companies = Company::query()
                ->where('is_active', true)
                ->orderBy('ticker')
                ->get();
        }

        // If neither flag is explicitly passed, default to both (similar to Python collector).
        $earningsFlag = (bool) $this->option('earnings');
        $dividendsFlag = (bool) $this->option('dividends');
        if (!$earningsFlag && !$dividendsFlag) {
            $earningsFlag = true;
            $dividendsFlag = true;
        }

        $throttle = !$this->option('no-throttle');

        $client = new AlphaVantageClient((string) config('services.alphavantage.key', ''));
        $importer = new AlphaVantageImporter($client);

        $totalE = 0;
        $totalD = 0;
        foreach ($companies as $company) {
            $r = $importer->importCompany($company, $earningsFlag, $dividendsFlag, $throttle);
            $this->line("{$company->ticker}: earnings {$r['earnings']}, dividends {$r['dividends']}");
            $totalE += (int) $r['earnings'];
            $totalD += (int) $r['dividends'];
        }

        $this->info("Done. Total earnings: {$totalE}, dividends: {$totalD}");
        return self::SUCCESS;
    }
}

