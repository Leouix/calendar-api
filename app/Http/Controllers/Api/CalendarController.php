<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventEarning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EventEarning::with('company:id,ticker,name');

        if ($request->filled('from')) {
            $query->where('event_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('event_date', '<=', $request->to);
        }

        if ($request->filled('event_type')) {
            // Back-compat for the existing frontend filter. We only store earnings now.
            if ($request->event_type !== 'earnings') {
                return response()->json([]);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('company', function ($q) use ($search) {
                $q->where('ticker', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $events = $query->orderBy('event_date')
            ->get([
                'id',
                'company_id',
                'title',
                'event_date',
                'source',
            ]);

        $events = $this->deduplicateSources($events);

        return response()->json($events);
    }

    private function deduplicateSources($events): Collection
    {
        $grouped = $events->groupBy('company_id');

        $filtered = [];
        foreach ($grouped as $companyEvents) {
            $avDates = $companyEvents
                ->filter(fn($e) => $e->source === 'alphavantage')
                ->pluck('event_date')
                ->toArray();

            foreach ($companyEvents as $event) {
                if ($event->source === 'finnhub'
                    && in_array($event->event_date, $avDates)) {
                    continue;
                }
                $filtered[] = $event;
            }
        }

        return collect($filtered);
    }
}
