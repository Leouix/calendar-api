<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\MarketData\Import\FinnhubEarningsImporter;
use App\Services\MarketData\Providers\FinnhubClient;
use Illuminate\Console\Command;

class ImportFinnhubCommand extends Command
{
    protected $signature = 'app:finnhub
        {--ticker= : Single ticker from companies table}
        {--year= : Year for earnings calendar (defaults to текущий год)}
        {--all : Import for all active companies (default if --ticker is omitted)}';

    protected $description = 'Import earnings calendar from Finnhub into event_earnings';

    protected $aliases = ['app:finn'];

    public function handle(): int
    {
        $tickerOpt = $this->option('ticker');
        $yearOpt = $this->option('year');

        $year = null;
        if (is_string($yearOpt) && $yearOpt !== '') {
            $year = (int) $yearOpt;
        }

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

        $client = new FinnhubClient((string) config('services.finnhub.key', ''));
        $importer = new FinnhubEarningsImporter($client);

        $total = 0;
        foreach ($companies as $company) {
            $n = $importer->importCompany($company, $year);
            $this->line("{$company->ticker}: earnings {$n}");
            $total += $n;
        }

        $this->info("Done. Total earnings: {$total}");
        return self::SUCCESS;
    }
}

