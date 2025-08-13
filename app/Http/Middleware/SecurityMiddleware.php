<?php

namespace App\Http\Middleware;

use App\Services\SecurityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SecurityMiddleware
{
    protected $securityService;

    public function __construct(SecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        // Get client IP and user agent
        $clientIp = $request->ip();
        $userAgent = $request->userAgent();
        $user = Auth::user();

        // 1. Rate Limiting Check
        if ($this->isRateLimited($request, $clientIp, $user)) {
            return response()->json([
                'error' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $this->getRateLimitRetryAfter($clientIp)
            ], 429);
        }

        // 2. Suspicious Activity Detection
        if ($this->detectSuspiciousActivity($request, $clientIp, $userAgent, $user)) {
            $this->logSecurityEvent('suspicious_activity', $request, $user);
            
            // For high-risk activities, require additional verification
            if ($this->isHighRiskRequest($request)) {
                return response()->json([
                    'error' => 'Additional verification required',
                    'requires_mfa' => true
                ], 403);
            }
        }

        // 3. Device Fingerprinting
        if ($user) {
            $this->trackDeviceFingerprint($request, $user);
        }

        // 4. Threat Intelligence Check
        if ($this->isThreatIP($clientIp)) {
            $this->logSecurityEvent('threat_ip_detected', $request, $user);
            return response()->json([
                'error' => 'Access denied for security reasons'
            ], 403);
        }

        // 5. Session Security
        if ($user && $this->isSessionCompromised($request, $user)) {
            Auth::logout();
            $this->logSecurityEvent('session_compromised', $request, $user);
            return response()->json([
                'error' => 'Session security violation. Please log in again.',
                'requires_login' => true
            ], 401);
        }

        // 6. Update user activity
        if ($user) {
            $this->updateUserActivity($user, $request);
        }

        // Process the request
        $response = $next($request);

        // 7. Post-request security checks
        $this->performPostRequestChecks($request, $response, $user);

        return $response;
    }

    /**
     * Check if request is rate limited
     */
    protected function isRateLimited(Request $request, string $clientIp, $user = null): bool
    {
        // Skip rate limiting entirely in development
        if (app()->environment('development', 'local')) {
            return false;
        }
        
        // Skip rate limiting if cache is not available
        try {
            $key = $user ? "rate_limit:user:{$user->id}" : "rate_limit:ip:{$clientIp}";
            
            // Different limits for different types of requests - DEVELOPMENT SETTINGS
            $limits = [
                'login' => ['max' => 100, 'window' => 900], // 100 attempts per 15 minutes
                'api' => ['max' => 10000, 'window' => 3600], // 10,000 requests per hour
                'booking' => ['max' => 1000, 'window' => 600], // 1,000 bookings per 10 minutes
                'default' => ['max' => 5000, 'window' => 3600], // 5,000 requests per hour (very generous)
            ];

            $requestType = $this->getRequestType($request);
            $limit = $limits[$requestType] ?? $limits['default'];

            $current = Cache::get($key, 0);
            
            if ($current >= $limit['max']) {
                return true;
            }

            Cache::put($key, $current + 1, $limit['window']);
            return false;
        } catch (\Exception $e) {
            // If cache fails, log the error but don't block the request
            \Log::warning('Rate limiting cache failed: ' . $e->getMessage());
            return false; // Allow request to proceed
        }
    }

    /**
     * Get request type for rate limiting
     */
    protected function getRequestType(Request $request): string
    {
        $path = $request->path();
        
        if (str_contains($path, 'login') || str_contains($path, 'auth')) {
            return 'login';
        }
        
        if (str_contains($path, 'api/')) {
            return 'api';
        }
        
        if (str_contains($path, 'booking')) {
            return 'booking';
        }
        
        return 'default';
    }

    /**
     * Get rate limit retry after time
     */
    protected function getRateLimitRetryAfter(string $clientIp): int
    {
        $key = "rate_limit:ip:{$clientIp}";
        
        // For file/database cache stores that don't support ttl(), return default
        try {
            $store = Cache::getStore();
            if (method_exists($store, 'ttl')) {
                return $store->ttl($key) ?? 3600;
            }
        } catch (\Exception $e) {
            // Fall back to default if cache operation fails
        }
        
        return 3600; // Default 1 hour retry after
    }

    /**
     * Detect suspicious activity patterns
     */
    protected function detectSuspiciousActivity(Request $request, string $clientIp, ?string $userAgent, $user = null): bool
    {
        $suspiciousPatterns = [
            // Rapid requests from same IP
            $this->detectRapidRequests($clientIp),
            
            // Unusual user agent patterns
            $this->detectSuspiciousUserAgent($userAgent),
            
            // Geolocation anomalies
            $this->detectLocationAnomalies($clientIp, $user),
            
            // SQL injection attempts
            $this->detectSQLInjection($request),
            
            // XSS attempts
            $this->detectXSS($request),
            
            // Bot-like behavior
            $this->detectBotBehavior($request, $userAgent),
        ];

        return collect($suspiciousPatterns)->contains(true);
    }

    /**
     * Detect rapid requests from same IP
     */
    protected function detectRapidRequests(string $clientIp): bool
    {
        $key = "rapid_requests:{$clientIp}";
        $requests = Cache::get($key, []);
        $now = time();
        
        // Remove requests older than 1 minute
        $requests = array_filter($requests, fn($timestamp) => $now - $timestamp < 60);
        
        // Add current request
        $requests[] = $now;
        
        Cache::put($key, $requests, 300); // Store for 5 minutes
        
        // More than 30 requests in 1 minute is suspicious
        return count($requests) > 30;
    }

    /**
     * Detect suspicious user agent patterns
     */
    protected function detectSuspiciousUserAgent(?string $userAgent): bool
    {
        if (!$userAgent) {
            return true; // No user agent is suspicious
        }

        $suspiciousPatterns = [
            'bot', 'crawl', 'spider', 'scrape', 'hack', 'scan',
            'curl', 'wget', 'python', 'php', 'java', 'perl'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect location anomalies
     */
    protected function detectLocationAnomalies(string $clientIp, $user = null): bool
    {
        if (!$user) {
            return false;
        }

        // Get current location from IP
        $currentLocation = $this->getLocationFromIP($clientIp);
        
        // Get user's typical locations
        $typicalLocations = $this->getUserTypicalLocations($user);
        
        // If no typical locations, this is not suspicious
        if (empty($typicalLocations)) {
            return false;
        }

        // Check if current location is far from typical locations
        foreach ($typicalLocations as $location) {
            $distance = $this->calculateDistance($currentLocation, $location);
            if ($distance < 1000) { // Within 1000km
                return false;
            }
        }

        return true; // All locations are far away
    }

    /**
     * Detect SQL injection attempts
     */
    protected function detectSQLInjection(Request $request): bool
    {
        $sqlPatterns = [
            "/(union|select|insert|update|delete|drop|create|alter|exec|execute)/i",
            "/('|(\\x27)|(\\x2D\\x2D)|(%27)|(%2D%2D))/i",
            "/(\\x00|\\n|\\r|\\x1a)/i"
        ];

        $input = json_encode($request->all());
        
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect XSS attempts
     */
    protected function detectXSS(Request $request): bool
    {
        $xssPatterns = [
            "/<script[^>]*>.*?<\/script>/si",
            "/javascript:/i",
            "/on\w+\s*=/i",
            "/<iframe[^>]*>.*?<\/iframe>/si"
        ];

        $input = json_encode($request->all());
        
        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect bot-like behavior
     */
    protected function detectBotBehavior(Request $request, ?string $userAgent): bool
    {
        // Check for missing common headers
        $requiredHeaders = ['accept', 'accept-language', 'accept-encoding'];
        foreach ($requiredHeaders as $header) {
            if (!$request->header($header)) {
                return true;
            }
        }

        // Check for unusual request patterns
        if (!$userAgent || strlen($userAgent) < 10) {
            return true;
        }

        return false;
    }

    /**
     * Check if this is a high-risk request
     */
    protected function isHighRiskRequest(Request $request): bool
    {
        $highRiskPaths = [
            'admin', 'payment', 'booking', 'profile', 'settings'
        ];

        $path = $request->path();
        
        foreach ($highRiskPaths as $riskPath) {
            if (str_contains($path, $riskPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is in threat intelligence database
     */
    protected function isThreatIP(string $clientIp): bool
    {
        // Check local blacklist
        $blacklistedIPs = Cache::get('blacklisted_ips', []);
        if (in_array($clientIp, $blacklistedIPs)) {
            return true;
        }

        // Check threat intelligence feeds (implement as needed)
        return false;
    }

    /**
     * Track device fingerprint
     */
    protected function trackDeviceFingerprint(Request $request, $user): void
    {
        $fingerprint = [
            'user_agent' => $request->userAgent(),
            'accept_language' => $request->header('Accept-Language'),
            'ip_address' => $request->ip(),
            'screen_resolution' => $request->header('X-Screen-Resolution'),
            'timezone' => $request->header('X-Timezone'),
        ];

        $fingerprintHash = hash('sha256', json_encode($fingerprint));
        
        $deviceFingerprints = $user->device_fingerprints ?? [];
        
        if (!in_array($fingerprintHash, $deviceFingerprints)) {
            $deviceFingerprints[] = $fingerprintHash;
            $user->update(['device_fingerprints' => $deviceFingerprints]);
            
            $this->logSecurityEvent('new_device_detected', $request, $user, [
                'fingerprint' => $fingerprintHash
            ]);
        }
    }

    /**
     * Check if session is compromised
     */
    protected function isSessionCompromised(Request $request, $user): bool
    {
        // Check for session hijacking indicators
        $sessionFingerprint = $this->getSessionFingerprint($request);
        $storedFingerprint = session('security_fingerprint');
        
        if ($storedFingerprint && $storedFingerprint !== $sessionFingerprint) {
            return true;
        }
        
        if (!$storedFingerprint) {
            session(['security_fingerprint' => $sessionFingerprint]);
        }

        return false;
    }

    /**
     * Get session fingerprint
     */
    protected function getSessionFingerprint(Request $request): string
    {
        return hash('sha256', 
            $request->userAgent() . 
            $request->header('Accept-Language') . 
            $request->ip()
        );
    }

    /**
     * Update user activity
     */
    protected function updateUserActivity($user, Request $request): void
    {
        $user->update([
            'last_activity_at' => now(),
            'risk_score' => $this->calculateRiskScore($user, $request)
        ]);
    }

    /**
     * Calculate user risk score
     */
    protected function calculateRiskScore($user, Request $request): float
    {
        $baseScore = 0.0;
        
        // Factors that increase risk score
        if ($this->detectLocationAnomalies($request->ip(), $user)) {
            $baseScore += 0.3;
        }
        
        if ($this->detectSuspiciousUserAgent($request->userAgent())) {
            $baseScore += 0.2;
        }
        
        if ($this->detectRapidRequests($request->ip())) {
            $baseScore += 0.4;
        }
        
        // Factors that decrease risk score
        if ($user->mfa_enabled) {
            $baseScore -= 0.1;
        }
        
        if ($user->government_id_verified_at) {
            $baseScore -= 0.1;
        }
        
        return max(0.0, min(1.0, $baseScore));
    }

    /**
     * Perform post-request security checks
     */
    protected function performPostRequestChecks(Request $request, Response $response, $user = null): void
    {
        // Log failed login attempts
        if ($response->getStatusCode() === 401 && str_contains($request->path(), 'login')) {
            $this->logSecurityEvent('failed_login', $request, $user);
        }
        
        // Log successful high-risk operations
        if ($response->getStatusCode() < 300 && $this->isHighRiskRequest($request)) {
            $this->logSecurityEvent('high_risk_operation', $request, $user);
        }
    }

        /**
     * Log security event
     */
    protected function logSecurityEvent(string $eventType, Request $request, $user = null, array $additionalData = []): void
    {
        $this->securityService->logSecurityEvent($eventType, $request, array_merge([
            'user_id' => $user?->id,
        ], $additionalData));
    }

    /**
     * Helper methods for geolocation
     */
    protected function getLocationFromIP(string $ip): array
    {
        // Implement IP geolocation (use a service like MaxMind)
        return ['lat' => 0, 'lng' => 0, 'country' => 'Unknown'];
    }

    protected function getUserTypicalLocations($user): array
    {
        // Get locations from user's booking history
        return [];
    }

    protected function calculateDistance(array $location1, array $location2): float
    {
        // Implement haversine formula for distance calculation
        $earthRadius = 6371; // km
        
        $lat1 = deg2rad($location1['lat']);
        $lon1 = deg2rad($location1['lng']);
        $lat2 = deg2rad($location2['lat']);
        $lon2 = deg2rad($location2['lng']);
        
        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;
        
        $a = sin($deltaLat/2) * sin($deltaLat/2) + 
             cos($lat1) * cos($lat2) * 
             sin($deltaLon/2) * sin($deltaLon/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
}
