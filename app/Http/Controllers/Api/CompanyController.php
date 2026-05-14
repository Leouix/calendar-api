<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
