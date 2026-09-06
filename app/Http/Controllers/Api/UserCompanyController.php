<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\EventDividend;
use App\Models\EventEarning;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class UserCompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::findOrFail($validated['user_id']);

        $companies = $user->companies()
            ->orderBy('ticker')
            ->get(['companies.id', 'companies.ticker', 'companies.name', 'companies.country', 'companies.exchange', 'companies.sector'])
            ->map(function (Company $company) {
                $company->has_data = $this->companyHasData($company->id);

                return $company;
            });

        return response()->json($companies);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'ticker' => 'required|string|max:20',
            'name' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:4',
            'exchange' => 'nullable|string|max:100',
            'sector' => 'nullable|string|max:100',
        ]);

        $user = User::findOrFail($validated['user_id']);

        $ticker = strtoupper(trim($validated['ticker']));

        $company = Company::firstOrCreate(
            ['ticker' => $ticker],
            [
                'name' => $validated['name'] ?? $ticker,
                'country' => $validated['country'] ?? null,
                'exchange' => $validated['exchange'] ?? null,
                'sector' => $validated['sector'] ?? null,
                'is_active' => true,
            ],
        );

        $hasData = $this->companyHasData($company->id);

        // Idempotent: repeated adds do not duplicate the link or enqueue a new job.
        $changes = $user->companies()->syncWithoutDetaching([$company->id]);
        $isNewLink = collect($changes['attached'] ?? [])->contains($company->id);

        if ($isNewLink && ! $hasData) {
            $this->publishCollectionJob($ticker);
        }

        return response()->json([
            'company' => $company,
            'has_data' => $hasData,
            'collected' => $hasData,
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        $user = User::findOrFail($validated['user_id']);

        $user->companies()->detach($validated['company_id']);

        return response()->json(null, 204);
    }

    private function companyHasData(int $companyId): bool
    {
        return EventEarning::where('company_id', $companyId)->exists()
            || EventDividend::where('company_id', $companyId)->exists();
    }

    private function publishCollectionJob(string $ticker): void
    {
        // The 'queue' redis connection has an empty prefix, so the stream is
        // the literal key `collector:jobs` that the Python worker consumes.
        Redis::connection('queue')
            ->command('xadd', [
                'collector:jobs',
                '*',
                [
                    'action' => 'company',
                    'ticker' => $ticker,
                ],
            ]);
    }
}
