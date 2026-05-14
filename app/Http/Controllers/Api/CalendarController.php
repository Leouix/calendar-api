<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Event::with('company:id,ticker,name');

        if ($request->filled('from')) {
            $query->where('event_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('event_date', '<=', $request->to);
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
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
                'event_type',
                'title',
                'event_date',
                'source',
            ]);

        return response()->json($events);
    }
}
