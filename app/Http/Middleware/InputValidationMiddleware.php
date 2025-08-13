<?php

namespace App\Http\Middleware;

use App\Exceptions\SecurityException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class InputValidationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check for malicious patterns in all input
        $this->validateAllInput($request);

        // 2. Sanitize file uploads
        $this->validateFileUploads($request);

        // 3. Check request size limits
        $this->validateRequestSize($request);

        // 4. Validate content type
        $this->validateContentType($request);

        return $next($request);
    }

    /**
     * Validate all request input for malicious patterns
     */
    protected function validateAllInput(Request $request): void
    {
        $allInput = $request->all();
        
        foreach ($allInput as $key => $value) {
            if (is_string($value)) {
                $this->detectXSS($key, $value);
                $this->detectSQLInjection($key, $value);
                $this->detectPathTraversal($key, $value);
                $this->detectCodeInjection($key, $value);
            } elseif (is_array($value)) {
                $this->validateArrayInput($key, $value);
            }
        }
    }

    /**
     * Recursively validate array input
     */
    protected function validateArrayInput(string $parentKey, array $array): void
    {
        foreach ($array as $key => $value) {
            $fullKey = "{$parentKey}.{$key}";
            
            if (is_string($value)) {
                $this->detectXSS($fullKey, $value);
                $this->detectSQLInjection($fullKey, $value);
                $this->detectPathTraversal($fullKey, $value);
                $this->detectCodeInjection($fullKey, $value);
            } elseif (is_array($value)) {
                $this->validateArrayInput($fullKey, $value);
            }
        }
    }

    /**
     * Detect XSS attempts
     */
    protected function detectXSS(string $field, string $value): void
    {
        $xssPatterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/<link/i',
            '/<meta/i',
            '/expression\s*\(/i',
            '/vbscript:/i',
            '/data:text\/html/i',
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $this->logSecurityEvent('xss_attempt', $field, $value);
                throw SecurityException::xssAttempt();
            }
        }
    }

    /**
     * Detect SQL injection attempts
     */
    protected function detectSQLInjection(string $field, string $value): void
    {
        $sqlPatterns = [
            '/(\s|^)(union|select|insert|update|delete|drop|create|alter|exec|execute)\s/i',
            '/(--|#|\/\*|\*\/)/i',
            '/(\s|^)(or|and)\s+[\'"]?\w+[\'"]?\s*=\s*[\'"]?\w+[\'"]?/i',
            '/[\'";]\s*(or|and|union|select|insert|update|delete)\s/i',
            '/\s+(or|and)\s+[\'"]?1[\'"]?\s*=\s*[\'"]?1[\'"]?/i',
            '/\s+(or|and)\s+[\'"]?true[\'"]?\s*=\s*[\'"]?true[\'"]?/i',
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $this->logSecurityEvent('sql_injection_attempt', $field, $value);
                throw SecurityException::sqlInjectionAttempt();
            }
        }
    }

    /**
     * Detect path traversal attempts
     */
    protected function detectPathTraversal(string $field, string $value): void
    {
        $traversalPatterns = [
            '/\.\.\//i',
            '/\.\.\\\/i',
            '/%2e%2e%2f/i',
            '/%2e%2e\\\/i',
            '/\.\.%2f/i',
            '/\.\.%5c/i',
        ];

        foreach ($traversalPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $this->logSecurityEvent('path_traversal_attempt', $field, $value);
                throw SecurityException::suspiciousActivity('Path traversal attempt detected');
            }
        }
    }

    /**
     * Detect code injection attempts
     */
    protected function detectCodeInjection(string $field, string $value): void
    {
        $codePatterns = [
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/`.*`/i',
            '/<\?php/i',
            '/<\?=/i',
            '/<%.*%>/i',
        ];

        foreach ($codePatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $this->logSecurityEvent('code_injection_attempt', $field, $value);
                throw SecurityException::suspiciousActivity('Code injection attempt detected');
            }
        }
    }

    /**
     * Validate file uploads
     */
    protected function validateFileUploads(Request $request): void
    {
        if (!$request->hasFile(array_keys($request->allFiles()))) {
            return;
        }

        foreach ($request->allFiles() as $key => $files) {
            $files = is_array($files) ? $files : [$files];
            
            foreach ($files as $file) {
                if (!$file->isValid()) {
                    continue;
                }

                // Check file size
                $maxSize = config('app.max_upload_size', 10240); // 10MB default
                if ($file->getSize() > $maxSize * 1024) {
                    throw SecurityException::suspiciousActivity('File size exceeds limit');
                }

                // Check file extension
                $allowedExtensions = config('app.allowed_file_extensions', [
                    'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'
                ]);
                
                $extension = strtolower($file->getClientOriginalExtension());
                if (!in_array($extension, $allowedExtensions)) {
                    throw SecurityException::suspiciousActivity('Disallowed file type uploaded');
                }

                // Check MIME type
                $allowedMimes = config('app.allowed_mime_types', [
                    'image/jpeg', 'image/png', 'image/gif', 'application/pdf',
                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'text/plain'
                ]);
                
                if (!in_array($file->getMimeType(), $allowedMimes)) {
                    throw SecurityException::suspiciousActivity('Disallowed MIME type uploaded');
                }

                // Check for executable files
                $executableExtensions = ['exe', 'bat', 'com', 'scr', 'pif', 'cmd', 'sh'];
                if (in_array($extension, $executableExtensions)) {
                    throw SecurityException::suspiciousActivity('Executable file upload attempt');
                }
            }
        }
    }

    /**
     * Validate request size
     */
    protected function validateRequestSize(Request $request): void
    {
        $maxRequestSize = config('app.max_request_size', 50); // 50MB default
        $contentLength = $request->header('Content-Length', 0);
        
        if ($contentLength > $maxRequestSize * 1024 * 1024) {
            throw SecurityException::suspiciousActivity('Request size exceeds limit');
        }
    }

    /**
     * Validate content type for API requests
     */
    protected function validateContentType(Request $request): void
    {
        if (!$request->is('api/*')) {
            return;
        }

        $contentType = $request->header('Content-Type', '');
        $allowedTypes = [
            'application/json',
            'application/x-www-form-urlencoded',
            'multipart/form-data',
            'text/plain'
        ];

        $isValidType = false;
        foreach ($allowedTypes as $type) {
            if (str_starts_with($contentType, $type)) {
                $isValidType = true;
                break;
            }
        }

        if (!$isValidType && !empty($contentType)) {
            throw SecurityException::suspiciousActivity('Invalid content type for API request');
        }
    }

    /**
     * Log security events
     */
    protected function logSecurityEvent(string $type, string $field, string $value): void
    {
        Log::channel('security')->warning("Input validation security event: {$type}", [
            'type' => $type,
            'field' => $field,
            'value' => substr($value, 0, 100), // Truncate for logging
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => auth()->id(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ]);
    }
}
