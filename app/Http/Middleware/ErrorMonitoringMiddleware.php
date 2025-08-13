<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ErrorMonitoringMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        try {
            $response = $next($request);

            // Log successful requests with performance metrics
            $this->logRequest($request, $response, $startTime, $startMemory);

            return $response;
        } catch (\Throwable $e) {
            // Log errors with context
            $this->logError($request, $e, $startTime, $startMemory);
            
            throw $e;
        }
    }

    /**
     * Log request details
     */
    private function logRequest(Request $request, Response $response, float $startTime, int $startMemory): void
    {
        $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $memoryUsage = memory_get_usage() - $startMemory;
        $statusCode = $response->getStatusCode();

        $context = [
            'request_id' => $this->generateRequestId(),
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'status_code' => $statusCode,
            'duration_ms' => round($executionTime, 2),
            'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'content_length' => $response->headers->get('Content-Length', strlen($response->getContent())),
            'user_id' => Auth::id(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ];

        // Log level based on status code
        if ($statusCode >= 500) {
            Log::error('Server error', $context);
        } elseif ($statusCode >= 400) {
            Log::warning('Client error', $context);
        } else {
            Log::info('Request completed', $context);
        }

        // Performance monitoring - log slow requests
        if ($executionTime > 1000) { // Requests taking more than 1 second
            Log::warning('Slow request detected', array_merge($context, [
                'performance_alert' => 'slow_request',
                'threshold_ms' => 1000
            ]));
        }

        // Memory usage monitoring
        if ($memoryUsage > 50 * 1024 * 1024) { // More than 50MB
            Log::warning('High memory usage detected', array_merge($context, [
                'performance_alert' => 'high_memory',
                'threshold_mb' => 50
            ]));
        }
    }

    /**
     * Log error details
     */
    private function logError(Request $request, \Throwable $e, float $startTime, int $startMemory): void
    {
        $executionTime = (microtime(true) - $startTime) * 1000;
        $memoryUsage = memory_get_usage() - $startMemory;

        $context = [
            'request_id' => $this->generateRequestId(),
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'error_type' => get_class($e),
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'stack_trace' => $e->getTraceAsString(),
            'duration_ms' => round($executionTime, 2),
            'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'user_id' => Auth::id(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_data' => $this->sanitizeRequestData($request)
        ];

        Log::error('Request failed', $context);
    }

    /**
     * Generate unique request ID
     */
    private function generateRequestId(): string
    {
        return date('Ymd') . '-' . substr(md5(uniqid()), 0, 8) . '-' . substr(str_pad(dechex(mt_rand()), 6, '0', STR_PAD_LEFT), 0, 6);
    }

    /**
     * Sanitize request data to avoid logging sensitive information
     */
    private function sanitizeRequestData(Request $request): array
    {
        $data = $request->all();
        
        // Remove sensitive fields
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'stripe_token', 'card_number', 'cvv'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }
}
