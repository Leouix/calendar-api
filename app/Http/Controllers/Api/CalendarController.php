<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventDividend;
use App\Models\EventEarning;
use App\Models\PolygonDividend;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $from = $request->filled('from') ? (string) $request->from : null;
        $to = $request->filled('to') ? (string) $request->to : null;
        $eventType = $request->filled('event_type') ? (string) $request->event_type : null;

        $events = collect();

        if ($eventType === null || $eventType === 'earnings') {
            $events = $events->merge($this->fetchEarnings($request, $from, $to));
        }

        if ($eventType === null || str_starts_with($eventType, 'event_dividend')) {
            $events = $events->merge($this->fetchDividendCalendarEvents($request, $from, $to, $eventType));
        }

        $events = $events
            ->unique(function ($e) {
                $ticker = (string) data_get($e, 'company.ticker', '');
                if ($ticker === '') {
                    // Dividend events may not always have the related company loaded; title is the ticker there.
                    $ticker = (string) data_get($e, 'ticker', data_get($e, 'title', ''));
                }
                $date = (string) data_get($e, 'event_date', '');

                return "{$ticker}|{$date}";
            })
            ->sortBy('event_date')
            ->values();

        return response()->json($events);
    }

    private function fetchEarnings(Request $request, ?string $from, ?string $to): Collection
    {
        $query = EventEarning::with('company:id,ticker,name');

        if ($from !== null) {
            $query->where('event_date', '>=', $from);
        }
        if ($to !== null) {
            $query->where('event_date', '<=', $to);
        }

        if ($request->filled('search')) {
            $search = (string) $request->search;
            $query->whereHas('company', function ($q) use ($search) {
                $q->where('ticker', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $rows = $query->orderBy('event_date')->get([
            'id',
            'company_id',
            'title',
            'event_date',
            'source',
        ]);

        // Deduplicate per company_id + event_date between AlphaVantage and Finnhub (prefer AlphaVantage).
        $byCompany = [];
        foreach ($rows as $row) {
            $byCompany[$row->company_id][] = $row;
        }

        $result = [];
        foreach ($byCompany as $companyEvents) {
            $avDates = [];
            foreach ($companyEvents as $e) {
                if ($e->source === 'alphavantage') {
                    $dateKey = is_string($e->event_date) ? $e->event_date : $e->event_date->format('Y-m-d');
                    $avDates[$dateKey] = true;
                }
            }

            foreach ($companyEvents as $e) {
                $dateKey = is_string($e->event_date) ? $e->event_date : $e->event_date->format('Y-m-d');
                if ($e->source === 'finnhub' && isset($avDates[$dateKey])) {
                    continue;
                }
                $result[] = [
                    'id' => $e->id,
                    'company_id' => $e->company_id,
                    'event_type' => 'earnings',
                    'title' => $e->title,
                    'event_date' => $dateKey,
                    'source' => $e->source,
                    'company' => $e->company,
                ];
            }
        }

        return collect($result);
    }

    private function fetchDividendCalendarEvents(Request $request, ?string $from, ?string $to, ?string $eventType): Collection
    {
        $query = PolygonDividend::with('company:id,ticker,name');

        if ($request->filled('search')) {
            $search = (string) $request->search;
            $query->whereHas('company', function ($q) use ($search) {
                $q->where('ticker', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Dividend dates are stored as strings. This assumes YYYY-MM-DD lexical ordering.
        if ($from !== null || $to !== null) {
            $query->where(function ($q) use ($from, $to) {
                foreach (['declaration_date', 'ex_dividend_date', 'pay_date'] as $field) {
                    $q->orWhere(function ($qq) use ($field, $from, $to) {
                        $qq->whereNotNull($field);
                        if ($from !== null) {
                            $qq->where($field, '>=', $from);
                        }
                        if ($to !== null) {
                            $qq->where($field, '<=', $to);
                        }
                    });
                }
            });
        }

        $rows = $query->orderBy('id')->get();

        $result = collect();
        foreach ($rows as $d) {
            $ticker = $d->company?->ticker ?? $d->ticker ?? '';
            $title = $ticker ?: 'Dividend';
            $company = $d->company;

            $items = [
                'event_dividend_declaration' => $d->declaration_date,
                'event_dividend_ex' => $d->ex_dividend_date,
                'event_dividend_payment' => $d->pay_date,
            ];

            foreach ($items as $type => $date) {
                if (!$date) {
                    continue;
                }
                if ($eventType !== null && $eventType !== 'event_dividend' && $eventType !== $type) {
                    continue;
                }

                $result->push([
                    'id' => "dividend:{$d->id}:{$type}",
                    'company_id' => $d->company?->id,
                    'event_type' => $type,
                    'title' => $title,
                    'event_date' => $date,
                    'source' => 'polygon',
                    'company' => $company,
                    'payload' => [
                        'amount' => $d->cash_amount,
                        'currency' => $d->currency,
                        'dividend_type' => $d->dividend_type,
                        'frequency' => $d->frequency,
                        'record_date' => $d->record_date,
                    ],
                ]);
            }
        }

        $eventDivQuery = EventDividend::with('company:id,ticker,name');

        if ($request->filled('search')) {
            $search = (string) $request->search;
            $eventDivQuery->whereHas('company', function ($q) use ($search) {
                $q->where('ticker', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($from !== null || $to !== null) {
            $eventDivQuery->where(function ($q) use ($from, $to) {
                foreach (['declaration_date', 'ex_dividend_date', 'payment_date'] as $field) {
                    $q->orWhere(function ($qq) use ($field, $from, $to) {
                        $qq->whereNotNull($field);
                        if ($from !== null) {
                            $qq->where($field, '>=', $from);
                        }
                        if ($to !== null) {
                            $qq->where($field, '<=', $to);
                        }
                    });
                }
            });
        }

        $eventDivRows = $eventDivQuery->orderBy('id')->get();

        foreach ($eventDivRows as $d) {
            $company = $d->company;
            $ticker = $company?->ticker ?? '';
            $title = $ticker ?: 'Dividend';

            $items = [
                'event_dividend_declaration' => $d->declaration_date,
                'event_dividend_ex' => $d->ex_dividend_date,
                'event_dividend_payment' => $d->payment_date,
            ];

            foreach ($items as $type => $date) {
                if (!$date) {
                    continue;
                }
                if ($eventType !== null && $eventType !== 'event_dividend' && $eventType !== $type) {
                    continue;
                }

                $result->push([
                    'id' => "event_div:{$d->id}:{$type}",
                    'company_id' => $d->company_id,
                    'event_type' => $type,
                    'title' => $title,
                    'event_date' => $date,
                    'source' => $d->source,
                    'company' => $company,
                    'payload' => [
                        'amount' => $d->amount,
                    ],
                ]);
            }
        }

        return $result;
    }
}
