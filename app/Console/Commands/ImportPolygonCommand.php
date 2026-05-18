<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\MarketData\Import\PolygonDividendsImporter;
use App\Services\MarketData\Import\RateLimitedException;
use App\Services\MarketData\Providers\PolygonClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:polygon
    {--ticker= : Single ticker from companies table}
    {--all : Import for all active companies (default if --ticker is omitted)}
    {--no-throttle : Do not sleep between Polygon calls}')]
#[Description('Import dividends from Polygon into polygon_dividends')]
class ImportPolygonCommand extends Command
{
    protected $aliases = ['app:poly', 'app:import-polygon'];

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

        $throttle = !$this->option('no-throttle');

        $client = new PolygonClient((string) config('services.polygon.key', ''));
        $importer = new PolygonDividendsImporter($client);

        $total = 0;
        foreach ($companies as $company) {
            try {
                $n = $importer->importCompany($company, $throttle);
            } catch (RateLimitedException) {
                $this->error("Polygon rate limited. Stopping early on {$company->ticker} to avoid spamming requests.");
                break;
            }
            $this->line("{$company->ticker}: dividends {$n}");
            $total += $n;
            if ($throttle) {
                usleep(250_000);
            }
        }

        $this->info("Done. Total dividends: {$total}");
        return self::SUCCESS;
    }
}
