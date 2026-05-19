<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\MarketData\Import\MoexImporter;
use App\Services\MarketData\Providers\MoexClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:moex
    {--ticker= : Single ticker from companies table}
    {--all : Import all active RU companies (default if --ticker is omitted)}
    {--no-throttle : Do not sleep between requests}')]
#[Description('Import dividends from MOEX ISS API for RU companies')]
class ImportMoexCommand extends Command
{
    protected $aliases = ['app:mx', 'app:import-moex'];

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
                ->where('country', 'RU')
                ->orderBy('ticker')
                ->get();
        }

        $throttle = !$this->option('no-throttle');

        $client = new MoexClient;
        $importer = new MoexImporter($client);

        $totalD = 0;
        foreach ($companies as $company) {
            $r = $importer->importCompany($company, $throttle);
            $this->line("{$company->ticker}: dividends {$r['dividends']}");
            $totalD += (int) ($r['dividends'] ?? 0);
        }

        $this->info("Done. Total dividends: {$totalD}");
        return self::SUCCESS;
    }
}
