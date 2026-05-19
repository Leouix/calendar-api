<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\MarketData\Import\SmartLabImporter;
use App\Services\MarketData\Providers\SmartLabClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:smartlab
    {--ticker= : Single ticker from companies table}
    {--all : Import all active RU companies (default if --ticker is omitted)}
    {--no-throttle : Do not sleep between requests}')]
#[Description('Import earnings and dividends from smart-lab.ru for RU companies')]
class ImportSmartLabCommand extends Command
{
    protected $aliases = ['app:sl', 'app:import-smartlab'];

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

        $client = new SmartLabClient;
        $importer = new SmartLabImporter($client);

        $totalE = 0;
        $totalD = 0;
        foreach ($companies as $company) {
            $r = $importer->importCompany($company, $throttle);
            $this->line("{$company->ticker}: earnings {$r['earnings']}, dividends {$r['dividends']}");
            $totalE += (int) ($r['earnings'] ?? 0);
            $totalD += (int) ($r['dividends'] ?? 0);
        }

        $this->info("Done. Total earnings: {$totalE}, dividends: {$totalD}");
        return self::SUCCESS;
    }
}
