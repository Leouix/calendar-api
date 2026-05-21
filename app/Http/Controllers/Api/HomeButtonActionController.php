<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class HomeButtonActionController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:alpha_earnings,finnhub_future_earnings,alpha_dividends,polygon_divs,moex_divs',
            'year' => 'nullable|integer|min:2000|max:2100',
            'no_throttle' => 'nullable|boolean',
        ]);

        $action = (string) $validated['action'];
        $year = $validated['year'] ?? null;
        $noThrottle = (bool) ($validated['no_throttle'] ?? false);

        $startedAt = microtime(true);
        $command = '';
        $params = [];

        // NOTE: We run synchronously for now. The response format intentionally includes fields that
        // make it easy to switch this endpoint to async jobs later (e.g. add job_id/status polling).
        switch ($action) {
            case 'alpha_earnings':
                $command = 'app:alpha';
                $params = ['--all' => true, '--earnings' => true];
                break;
            case 'finnhub_future_earnings':
                $command = 'app:finnhub';
                $params = ['--all' => true];
                if (is_int($year)) {
                    $params['--year'] = (string) $year;
                }
                break;
            case 'alpha_dividends':
                $command = 'app:alpha';
                $params = ['--all' => true, '--dividends' => true];
                break;
            case 'polygon_divs':
                $command = 'app:polygon';
                $params = ['--all' => true];
                break;
            case 'moex_divs':
                $command = 'app:moex';
                $params = ['--all' => true];
                break;
        }

        if ($noThrottle) {
            // Only commands that support it will accept this flag; we add it selectively.
            if (in_array($command, ['app:alpha', 'app:polygon', 'app:moex'], true)) {
                $params['--no-throttle'] = true;
            }
        }

        $exitCode = Artisan::call($command, $params);
        $output = (string) Artisan::output();

        // Keep output bounded to avoid sending huge payloads to the browser.
        $maxOut = 10_000;
        if (strlen($output) > $maxOut) {
            $output = substr($output, -$maxOut);
        }

        $finishedAt = microtime(true);

        return response()->json([
            'ok' => $exitCode === 0,
            'mode' => 'sync',
            'action' => $action,
            'command' => $command,
            'params' => $params,
            'exit_code' => $exitCode,
            'duration_ms' => (int) round(($finishedAt - $startedAt) * 1000),
            'output' => $output,
        ]);
    }
}

