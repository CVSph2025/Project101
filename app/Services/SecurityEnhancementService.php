<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class SecurityEnhancementService
{
    /**
     * Enhanced input sanitization
     */
    public static function sanitizeInput(array $input): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                // Remove potentially dangerous characters
                $value = strip_tags($value);
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                
                // Remove SQL injection patterns
                $sqlPatterns = [
                    '/(\bunion\b.*\bselect\b)/i',
                    '/(\bselect\b.*\bfrom\b)/i',
                    '/(\binsert\b.*\binto\b)/i',
                    '/(\bupdate\b.*\bset\b)/i',
                    '/(\bdelete\b.*\bfrom\b)/i',
                    '/(\bdrop\b.*\btable\b)/i',
                ];
                
                foreach ($sqlPatterns as $pattern) {
                    $value = preg_replace($pattern, '', $value);
                }
                
                // Remove XSS patterns
                $xssPatterns = [
                    '/<script[^>]*>.*?<\/script>/si',
                    '/javascript:/i',
                    '/on\w+\s*=/i',
                    '/<iframe[^>]*>.*?<\/iframe>/si',
                ];
                
                foreach ($xssPatterns as $pattern) {
                    $value = preg_replace($pattern, '', $value);
                }
            }
            
            return $value;
        }, $input);
    }

    /**
     * Enhanced file upload validation
     */
    public static function validateFileUpload($file, array $allowedTypes = [], int $maxSize = 5120): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        if (!$file || !$file->isValid()) {
            $result['valid'] = false;
            $result['errors'][] = 'Invalid file upload';
            return $result;
        }

        // Check file size
        if ($file->getSize() > $maxSize * 1024) {
            $result['valid'] = false;
            $result['errors'][] = "File size exceeds maximum allowed size of {$maxSize}KB";
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'text/plain', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        if (!empty($allowedTypes)) {
            $allowedMimes = $allowedTypes;
        }

        if (!in_array($mimeType, $allowedMimes)) {
            $result['valid'] = false;
            $result['errors'][] = 'File type not allowed';
        }

        // Check file extension matches MIME type
        $extension = strtolower($file->getClientOriginalExtension());
        $expectedExtensions = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'application/pdf' => ['pdf'],
            'text/plain' => ['txt'],
        ];

        if (isset($expectedExtensions[$mimeType])) {
            if (!in_array($extension, $expectedExtensions[$mimeType])) {
                $result['valid'] = false;
                $result['errors'][] = 'File extension does not match file type';
            }
        }

        // Basic malware detection
        $content = file_get_contents($file->getPathname());
        $suspiciousPatterns = [
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/<\?php/i',
            '/<script/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $result['valid'] = false;
                $result['errors'][] = 'File contains suspicious content';
                break;
            }
        }

        return $result;
    }

    /**
     * Enhanced rate limiting with IP reputation
     */
    public static function checkAdvancedRateLimit(Request $request, string $action = 'default'): bool
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        
        // Check IP reputation
        $ipReputation = self::getIPReputation($ip);
        
        // Adjust rate limits based on reputation
        $baseLimits = [
            'login' => 5,
            'api' => 100,
            'default' => 60
        ];
        
        $limit = $baseLimits[$action] ?? $baseLimits['default'];
        
        // Reduce limits for suspicious IPs
        if ($ipReputation < 0.5) {
            $limit = intval($limit * 0.5);
        }
        
        // Increase limits for trusted IPs
        if ($ipReputation > 0.8) {
            $limit = intval($limit * 1.5);
        }
        
        $key = "rate_limit:{$action}:{$ip}";
        
        return RateLimiter::tooManyAttempts($key, $limit);
    }

    /**
     * Get IP reputation score (0-1)
     */
    private static function getIPReputation(string $ip): float
    {
        $cacheKey = "ip_reputation:{$ip}";
        
        return Cache::remember($cacheKey, 3600, function () use ($ip) {
            $score = 0.5; // Neutral score
            
            // Check against known malicious IPs
            $maliciousIPs = Cache::get('malicious_ips', []);
            if (in_array($ip, $maliciousIPs)) {
                return 0.0;
            }
            
            // Check against trusted IPs
            $trustedIPs = Cache::get('trusted_ips', []);
            if (in_array($ip, $trustedIPs)) {
                return 1.0;
            }
            
            // Historical behavior analysis
            $violations = Cache::get("violations:{$ip}", 0);
            if ($violations > 10) {
                $score -= 0.3;
            } elseif ($violations > 5) {
                $score -= 0.2;
            }
            
            $successfulLogins = Cache::get("successful_logins:{$ip}", 0);
            if ($successfulLogins > 10) {
                $score += 0.2;
            }
            
            return max(0.0, min(1.0, $score));
        });
    }

    /**
     * Enhanced session security
     */
    public static function validateSessionSecurity(Request $request): array
    {
        $violations = [];
        
        // Check session fingerprint
        $currentFingerprint = self::generateSessionFingerprint($request);
        $storedFingerprint = session('security_fingerprint');
        
        if ($storedFingerprint && $currentFingerprint !== $storedFingerprint) {
            $violations[] = 'session_fingerprint_mismatch';
        }
        
        if (!$storedFingerprint) {
            session(['security_fingerprint' => $currentFingerprint]);
        }
        
        // Check session age
        $sessionStart = session('session_start', now()->timestamp);
        $sessionAge = now()->timestamp - $sessionStart;
        
        // Sessions older than 24 hours are suspicious
        if ($sessionAge > 86400) {
            $violations[] = 'session_too_old';
        }
        
        // Check for concurrent sessions (if user is logged in)
        if (auth()->check()) {
            $userId = auth()->id();
            $currentSessionId = session()->getId();
            
            // This would require implementing a sessions tracking system
            // For now, we'll just log the session ID
            Log::info('Session validation', [
                'user_id' => $userId,
                'session_id' => $currentSessionId,
                'ip' => $request->ip()
            ]);
        }
        
        return [
            'valid' => empty($violations),
            'violations' => $violations,
            'fingerprint' => $currentFingerprint
        ];
    }

    /**
     * Generate session fingerprint
     */
    private static function generateSessionFingerprint(Request $request): string
    {
        $components = [
            $request->userAgent(),
            $request->header('Accept-Language'),
            $request->header('Accept-Encoding'),
            $request->ip()
        ];
        
        return hash('sha256', implode('|', array_filter($components)));
    }

    /**
     * Log security events with enhanced context
     */
    public static function logSecurityEvent(string $type, Request $request, array $context = []): void
    {
        $baseContext = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'timestamp' => now()->toISOString(),
            'session_id' => session()->getId(),
            'user_id' => auth()->id(),
            'referer' => $request->header('Referer'),
            'accept_language' => $request->header('Accept-Language'),
        ];
        
        $logContext = array_merge($baseContext, $context);
        
        // Determine log level based on event type
        $criticalEvents = ['sql_injection', 'xss_attempt', 'malware_upload', 'session_hijack'];
        $warningEvents = ['suspicious_activity', 'rate_limit_exceeded', 'failed_login'];
        
        if (in_array($type, $criticalEvents)) {
            Log::critical("Security Event: {$type}", $logContext);
        } elseif (in_array($type, $warningEvents)) {
            Log::warning("Security Event: {$type}", $logContext);
        } else {
            Log::info("Security Event: {$type}", $logContext);
        }
        
        // Store in security logs table if available
        try {
            \App\Models\SecurityLog::create([
                'type' => $type,
                'severity' => in_array($type, $criticalEvents) ? 'critical' : 
                           (in_array($type, $warningEvents) ? 'warning' : 'info'),
                'data' => $logContext,
                'ip_address' => $request->ip(),
                'user_id' => auth()->id()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store security log', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Generate secure API keys
     */
    public static function generateSecureApiKey(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Validate API key format and strength
     */
    public static function validateApiKey(string $apiKey): bool
    {
        // Check length
        if (strlen($apiKey) < 32) {
            return false;
        }
        
        // Check if it's hexadecimal
        if (!ctype_xdigit($apiKey)) {
            return false;
        }
        
        return true;
    }
}
