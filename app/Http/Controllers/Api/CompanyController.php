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

    public function searchRu(string $ticker): JsonResponse
    {
        $ticker = strtoupper($ticker);

        $cacheKey = "moex_security_description_{$ticker}";
        $descMap = Cache::get($cacheKey);

        if (!is_array($descMap) || empty($descMap)) {
            $response = Http::timeout(15)->get("https://iss.moex.com/iss/securities/{$ticker}.json", [
                'iss.meta' => 'off',
                'iss.only' => 'description',
            ]);

            if (!$response->successful()) {
                return response()->json(['error' => 'Company not found or MOEX ISS unavailable'], 404);
            }

            $descMap = $this->extractMoexDescriptionMap($response->json());
            if (!is_array($descMap) || empty($descMap)) {
                return response()->json(['error' => 'Company not found'], 404);
            }

            Cache::put($cacheKey, $descMap, 86400);
        }

        $name = $descMap['SHORTNAME'] ?? $descMap['NAME'] ?? $descMap['SECNAME'] ?? null;
        if (!is_string($name) || trim($name) === '') {
            $name = $ticker;
        }

        return response()->json([
            'ticker' => $descMap['SECID'] ?? $ticker,
            'name' => $name,
            'sector' => null,
            'exchange' => 'MOEX',
            'country' => 'RU',
        ]);
    }

    private function extractMoexDescriptionMap(mixed $payload): ?array
    {
        if (!is_array($payload)) {
            return null;
        }

        $description = $payload['description'] ?? null;
        if (!is_array($description)) {
            return null;
        }

        $columns = $description['columns'] ?? null;
        $rows = $description['data'] ?? null;
        if (!is_array($columns) || !is_array($rows)) {
            return null;
        }

        $nameIdx = array_search('name', $columns, true);
        $valueIdx = array_search('value', $columns, true);
        if ($nameIdx === false || $valueIdx === false) {
            return null;
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = $row[$nameIdx] ?? null;
            if (!is_string($key) || $key === '') {
                continue;
            }

            $out[$key] = $row[$valueIdx] ?? null;
        }

        return $out;
    }
}
