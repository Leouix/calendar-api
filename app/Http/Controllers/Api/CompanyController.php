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
    public function index(): JsonResponse
    {
        $companies = Company::where('is_active', true)
            ->orderBy('ticker')
            ->get(['id', 'ticker', 'name', 'country', 'exchange', 'sector']);

        return response()->json($companies);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticker' => 'required|string|max:20|unique:companies',
            'name' => 'required|string|max:255',
            'country' => 'nullable|string|max:4',
            'exchange' => 'nullable|string|max:20',
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
            'exchange' => 'nullable|string|max:20',
            'sector' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $company->update($validated);

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

        $data = Cache::remember("alphavantage_overview_{$ticker}", 86400, function () use ($ticker) {
            $response = Http::get('https://www.alphavantage.co/query', [
                'function' => 'OVERVIEW',
                'symbol' => $ticker,
                'apikey' => config('services.alphavantage.key'),
            ]);

            return $response->json();
        });

        if (empty($data) || isset($data['Note']) || isset($data['Error Message'])) {
            return response()->json(['error' => 'Company not found or API limit exceeded'], 404);
        }

        return response()->json([
            'ticker' => $data['Symbol'],
            'name' => $data['Name'],
            'sector' => $data['Sector'] ?? null,
            'exchange' => $data['Exchange'] ?? null,
        ]);
    }
}
