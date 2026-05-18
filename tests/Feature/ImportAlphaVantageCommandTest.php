<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EventDividend;
use App\Models\EventEarning;
use App\Models\EventImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportAlphaVantageCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_earnings_and_dividends_from_alphavantage_and_logs(): void
    {
        config(['services.alphavantage.key' => 'test_key']);

        $company = Company::create([
            'ticker' => 'AAPL',
            'name' => 'Apple Inc',
            'is_active' => true,
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();
            if (!str_starts_with($url, 'https://www.alphavantage.co/query')) {
                return Http::response([], 404);
            }

            $fn = $request->data()['function'] ?? null;
            if ($fn === 'EARNINGS') {
                return Http::response([
                    'symbol' => 'AAPL',
                    'quarterlyEarnings' => [
                        ['reportedDate' => '2026-05-01', 'reportedEPS' => '1.20'],
                    ],
                ], 200);
            }

            if ($fn === 'DIVIDENDS') {
                return Http::response([
                    'symbol' => 'AAPL',
                    'data' => [
                        [
                            'ex_dividend_date' => '2026-05-11',
                            'declaration_date' => '2026-04-30',
                            'record_date' => '2026-05-11',
                            'payment_date' => '2026-05-14',
                            'amount' => '0.27',
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 400);
        });

        $this->artisan('app:alpha', ['--ticker' => 'AAPL', '--no-throttle' => true])
            ->assertExitCode(0);

        $this->assertSame(1, EventEarning::query()->where('company_id', $company->id)->where('source', 'alphavantage')->count());
        $this->assertSame(1, EventDividend::query()->where('company_id', $company->id)->where('source', 'alphavantage')->count());

        $earning = EventEarning::query()->where('company_id', $company->id)->where('source', 'alphavantage')->firstOrFail();
        $this->assertSame('AAPL', $earning->title);
        $this->assertSame('2026-05-01', $earning->event_date->format('Y-m-d'));

        $div = EventDividend::query()->where('company_id', $company->id)->where('source', 'alphavantage')->firstOrFail();
        $this->assertSame('AAPL', $div->symbol);
        $this->assertSame('2026-05-11', $div->ex_dividend_date);
        $this->assertSame('0.27', $div->amount);

        $this->assertSame(1, EventImportLog::query()->where('provider', 'alphavantage')->where('ticker', 'AAPL')->where('status', 'ok')->count());
    }
}

