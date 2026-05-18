<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventDividend;
use App\Models\EventEarning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $from = $request->filled('from') ? (string) $request->from : null;
        $to = $request->filled('to') ? (string) $request->to : null;
        $eventType = $request->filled('event_type') ? (string) $request->event_type : null;

        $events = [];

        if ($eventType === null || $eventType === 'earnings') {
            $events = array_merge($events, $this->fetchEarnings($request, $from, $to));
        }

        if ($eventType === null || str_starts_with($eventType, 'event_dividend')) {
            $events = array_merge($events, $this->fetchDividendCalendarEvents($request, $from, $to, $eventType));
        }

        usort($events, fn ($a, $b) => strcmp($a['event_date'], $b['event_date']));

        return response()->json($events);
    }

    private function fetchEarnings(Request $request, ?string $from, ?string $to): array
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

        return $result;
    }

    private function fetchDividendCalendarEvents(Request $request, ?string $from, ?string $to, ?string $eventType): array
    {
        $query = EventDividend::with('company:id,ticker,name');

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

        $rows = $query->orderBy('id')->get();

        $result = [];
        foreach ($rows as $d) {
            $ticker = $d->company?->ticker ?? $d->symbol ?? '';
            $title = $ticker ?: 'Dividend';
            $company = $d->company;

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

                $result[] = [
                    'id' => "dividend:{$d->id}:{$type}",
                    'company_id' => $d->company_id,
                    'event_type' => $type,
                    'title' => $title,
                    'event_date' => $date,
                    'source' => $d->source,
                    'company' => $company,
                    'payload' => [
                        'amount' => $d->amount,
                    ],
                ];
            }
        }

        return $result;
    }
}
