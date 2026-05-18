<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EventEarning;
use App\Models\EventImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportFinnhubCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_earnings_from_finnhub_and_logs(): void
    {
        config(['services.finnhub.key' => 'test_key']);

        $company = Company::create([
            'ticker' => 'AAPL',
            'name' => 'Apple Inc',
            'is_active' => true,
        ]);

        Http::fake([
            'https://finnhub.io/api/v1/calendar/earnings*' => Http::response([
                'earningsCalendar' => [
                    ['date' => '2026-05-02', 'epsEstimated' => 1.23],
                    ['date' => '2026-08-02', 'epsEstimated' => 1.11],
                ],
            ], 200),
        ]);

        $this->artisan('app:finnhub', ['--ticker' => 'AAPL', '--year' => '2026'])
            ->assertExitCode(0);

        $this->assertSame(2, EventEarning::query()->where('company_id', $company->id)->where('source', 'finnhub')->count());

        $row = EventEarning::query()->where('company_id', $company->id)->where('source', 'finnhub')->orderBy('event_date')->firstOrFail();
        $this->assertSame('AAPL', $row->title);
        $this->assertSame('2026-05-02', $row->event_date->format('Y-m-d'));
        $this->assertIsArray($row->payload_json);
        $this->assertSame(1.23, $row->payload_json['epsEstimated']);

        $this->assertSame(1, EventImportLog::query()->where('provider', 'finnhub')->where('ticker', 'AAPL')->where('status', 'ok')->count());
    }
}

