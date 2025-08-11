<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ValidateEnvironmentMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip validation for health check endpoints
        if ($request->is('health*')) {
            return $next($request);
        }

        // Validate critical environment variables
        $criticalVars = [
            'APP_KEY' => config('app.key'),
            'DB_CONNECTION' => config('database.default'),
        ];

        $missing = [];
        foreach ($criticalVars as $var => $value) {
            if (empty($value)) {
                $missing[] = $var;
            }
        }

        if (!empty($missing)) {
            Log::critical('Critical environment variables missing', [
                'missing' => $missing,
                'request_path' => $request->path(),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Application configuration incomplete',
                    'message' => 'Please contact support if this error persists',
                    'code' => 'CONFIG_INCOMPLETE'
                ], 500);
            }

            return response()->view('errors.config-incomplete', [], 500);
        }

        return $next($request);
    }
}
