<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Company::query();
        if (!$request->boolean('all')) {
            $query->where('is_active', true);
        }

        $companies = $query
            ->orderBy('ticker')
            ->get(['id', 'ticker', 'name', 'country', 'exchange', 'sector', 'is_active']);

        return response()->json($companies);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticker' => 'required|string|max:20|unique:companies',
            'name' => 'required|string|max:255',
            'country' => 'nullable|string|max:4',
            'exchange' => 'nullable|string|max:100',
            'sector' => 'nullable|string|max:100',
        ]);

        $company = Company::create($validated + ['is_active' => true]);

        return response()->json($company, 201);
    }

    public function show(Company $company): JsonResponse
    {
        return response()->json($company);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'ticker' => 'sometimes|string|max:20|unique:companies,ticker,' . $company->id,
            'name' => 'sometimes|string|max:255',
            'country' => 'nullable|string|max:4',
            'exchange' => 'nullable|string|max:100',
            'sector' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $company->update($validated);

        return response()->json($company);
    }

    public function updateCompany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:companies,id',
            'is_active' => 'required|boolean',
        ]);

        $company = Company::findOrFail($validated['id']);
        $company->update(['is_active' => $validated['is_active']]);

        return response()->json($company);
    }

    public function destroy(Company $company): JsonResponse
    {
        $company->delete();

        return response()->json(null, 204);
    }

    public function search(string $ticker): JsonResponse
    {
        $ticker = strtoupper($ticker);

        $apiKey = (string) config('services.finnhub.key');
        if ($apiKey === '') {
            return response()->json(['error' => 'FINNHUB_API_KEY not set'], 503);
        }

        $cacheKey = "finnhub_profile2_{$ticker}";
        $data = Cache::get($cacheKey);

        if (!is_array($data) || empty($data)) {
            $response = Http::timeout(15)->get('https://finnhub.io/api/v1/stock/profile2', [
                'symbol' => $ticker,
                'token' => $apiKey,
            ]);

            if ($response->status() === 429) {
                return response()->json(['error' => 'Finnhub API rate limit exceeded'], 429);
            }

            if (!$response->successful()) {
                return response()->json(['error' => 'Company not found or Finnhub unavailable'], 404);
            }

            $data = $response->json();

            // Finnhub returns {} (HTTP 200) for unknown symbols.
            if (!is_array($data) || empty($data)) {
                return response()->json(['error' => 'Company not found'], 404);
            }

            Cache::put($cacheKey, $data, 86400);
        }

        return response()->json([
            'ticker' => $data['ticker'] ?? $ticker,
            'name' => $data['name'] ?? null,
            'sector' => $data['finnhubIndustry'] ?? null,
            'exchange' => $data['exchange'] ?? null,
        ]);
    }
}
