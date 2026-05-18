<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EventDividend;
use App\Models\EventEarning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarDividendsTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_returns_dividends_as_three_separate_events_with_amount(): void
    {
        $company = Company::create([
            'ticker' => 'AAPL',
            'name' => 'Apple Inc',
            'country' => 'US',
            'exchange' => 'NASDAQ',
            'sector' => 'Technology',
            'is_active' => true,
        ]);

        EventDividend::create([
            'company_id' => $company->id,
            'symbol' => 'AAPL',
            'declaration_date' => '2026-04-30',
            'ex_dividend_date' => '2026-05-11',
            'payment_date' => '2026-05-14',
            'record_date' => '2026-05-11',
            'amount' => '0.27',
            'source' => 'alphavantage',
            'source_hash' => 'hash1',
        ]);

        $res = $this->getJson('/api/calendar?from=2026-04-01&to=2026-05-31');
        $res->assertOk();

        $events = $res->json();
        $this->assertCount(3, $events);

        $types = array_map(fn ($e) => $e['event_type'], $events);
        sort($types);
        $this->assertSame(
            ['event_dividend_declaration', 'event_dividend_ex', 'event_dividend_payment'],
            $types
        );

        foreach ($events as $e) {
            $this->assertSame($company->id, $e['company_id']);
            $this->assertSame('AAPL', $e['title']);
            $this->assertSame('0.27', $e['payload']['amount']);
        }
    }

    public function test_calendar_can_filter_dividend_event_type(): void
    {
        $company = Company::create([
            'ticker' => 'AAPL',
            'name' => 'Apple Inc',
            'is_active' => true,
        ]);

        EventDividend::create([
            'company_id' => $company->id,
            'symbol' => 'AAPL',
            'declaration_date' => '2026-04-30',
            'ex_dividend_date' => '2026-05-11',
            'payment_date' => '2026-05-14',
            'amount' => '0.27',
            'source' => 'alphavantage',
            'source_hash' => 'hash1',
        ]);

        $res = $this->getJson('/api/calendar?event_type=event_dividend_ex&from=2026-04-01&to=2026-05-31');
        $res->assertOk();
        $events = $res->json();
        $this->assertCount(1, $events);
        $this->assertSame('event_dividend_ex', $events[0]['event_type']);
        $this->assertSame('2026-05-11', $events[0]['event_date']);
    }

    public function test_calendar_can_return_earnings_and_dividends_together(): void
    {
        $company = Company::create([
            'ticker' => 'AAPL',
            'name' => 'Apple Inc',
            'is_active' => true,
        ]);

        EventEarning::create([
            'company_id' => $company->id,
            'title' => 'AAPL Earnings',
            'event_date' => '2026-05-02',
            'payload_json' => null,
            'source' => 'alphavantage',
            'source_hash' => 'eh1',
        ]);

        EventDividend::create([
            'company_id' => $company->id,
            'symbol' => 'AAPL',
            'ex_dividend_date' => '2026-05-11',
            'amount' => '0.27',
            'source' => 'alphavantage',
            'source_hash' => 'hash1',
        ]);

        $res = $this->getJson('/api/calendar?from=2026-05-01&to=2026-05-31');
        $res->assertOk();
        $events = $res->json();

        $this->assertCount(2, $events);
        $this->assertSame('2026-05-02', $events[0]['event_date']);
        $this->assertSame('earnings', $events[0]['event_type']);
        $this->assertSame('2026-05-11', $events[1]['event_date']);
        $this->assertSame('event_dividend_ex', $events[1]['event_type']);
    }
}

