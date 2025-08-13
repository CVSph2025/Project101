<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate or extract request ID
        $requestId = $request->header('X-Request-ID') ?? $this->generateRequestId();
        
        // Add request ID to request headers
        $request->headers->set('X-Request-ID', $requestId);
        
        // Log request start
        $this->logRequestStart($request, $requestId);
        
        $startTime = microtime(true);
        
        // Process request
        $response = $next($request);
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        // Add request ID to response headers
        $response->headers->set('X-Request-ID', $requestId);
        
        // Log request completion
        $this->logRequestEnd($request, $response, $requestId, $duration);
        
        return $response;
    }

    /**
     * Generate a unique request ID
     */
    protected function generateRequestId(): string
    {
        return sprintf(
            '%s-%s-%s',
            date('Ymd'),
            substr(uniqid(), -8),
            substr(md5(request()->ip() . request()->userAgent()), 0, 6)
        );
    }

    /**
     * Log request start
     */
    protected function logRequestStart(Request $request, string $requestId): void
    {
        $logData = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
            'content_length' => $request->header('Content-Length', 0),
            'referer' => $request->header('Referer'),
        ];

        // Don't log sensitive data
        $input = $request->except([
            'password', 'password_confirmation', 'token', 'secret', 
            'api_key', 'stripe_key', 'credit_card', 'cvv'
        ]);

        if (!empty($input)) {
            $logData['input'] = $input;
        }

        Log::info('Request started', $logData);
    }

    /**
     * Log request completion
     */
    protected function logRequestEnd(
        Request $request,
        Response $response,
        string $requestId,
        float $duration
    ): void {
        $logData = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'content_length' => $response->headers->get('Content-Length', 0),
            'user_id' => auth()->id(),
        ];

        $logLevel = $this->getLogLevel($response->getStatusCode(), $duration);
        
        Log::log($logLevel, 'Request completed', $logData);

        // Log slow requests separately
        if ($duration > 1000) { // More than 1 second
            Log::warning('Slow request detected', array_merge($logData, [
                'threshold_ms' => 1000,
                'performance_impact' => 'high'
            ]));
        }

        // Log error responses
        if ($response->getStatusCode() >= 400) {
            $errorData = $logData;
            
            // Try to extract error message from JSON response
            if ($response->headers->get('Content-Type') === 'application/json') {
                $content = $response->getContent();
                $jsonData = json_decode($content, true);
                
                if (json_last_error() === JSON_ERROR_NONE && isset($jsonData['message'])) {
                    $errorData['error_message'] = $jsonData['message'];
                    $errorData['error_code'] = $jsonData['error_code'] ?? 'UNKNOWN';
                }
            }
            
            Log::error('Request failed', $errorData);
        }
    }

    /**
     * Determine appropriate log level based on response
     */
    protected function getLogLevel(int $statusCode, float $duration): string
    {
        if ($statusCode >= 500) {
            return 'error';
        }
        
        if ($statusCode >= 400) {
            return 'warning';
        }
        
        if ($duration > 2000) { // More than 2 seconds
            return 'warning';
        }
        
        return 'info';
    }
}
