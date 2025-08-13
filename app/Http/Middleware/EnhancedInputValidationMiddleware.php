<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Exceptions\SecurityException;
use Symfony\Component\HttpFoundation\Response;

class EnhancedInputValidationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip validation for certain routes
        if ($this->shouldSkipValidation($request)) {
            return $next($request);
        }

        // Sanitize input
        $this->sanitizeInput($request);
        
        // Validate input for security threats
        $this->validateSecurity($request);
        
        // Validate file uploads
        $this->validateUploads($request);

        return $next($request);
    }

    /**
     * Check if validation should be skipped
     */
    protected function shouldSkipValidation(Request $request): bool
    {
        $skipRoutes = [
            'health',
            'up',
            'login',
            'register',
            'password.email',
            'password.reset',
            'verification.notice',
            'verification.verify',
            'verification.send'
        ];

        $routeName = $request->route()?->getName();
        
        return in_array($routeName, $skipRoutes) || 
               str_starts_with($request->path(), 'api/') ||
               str_starts_with($request->path(), '_debugbar') ||
               str_starts_with($request->path(), 'telescope');
    }

    /**
     * Sanitize input data
     */
    protected function sanitizeInput(Request $request): void
    {
        $input = $request->all();
        $sanitized = $this->recursiveSanitize($input);
        $request->replace($sanitized);
    }

    /**
     * Recursively sanitize input
     */
    protected function recursiveSanitize($data): array
    {
        if (!is_array($data)) {
            return [];
        }

        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->recursiveSanitize($value);
            } elseif (is_string($value)) {
                // Basic XSS protection
                $sanitized[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                
                // Remove potential script tags
                $sanitized[$key] = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $sanitized[$key]);
                
                // Remove javascript: protocol
                $sanitized[$key] = preg_replace('/javascript:/i', '', $sanitized[$key]);
                
                // Remove on* event handlers
                $sanitized[$key] = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $sanitized[$key]);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Validate input for security threats
     */
    protected function validateSecurity(Request $request): void
    {
        $input = $request->all();
        
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                // Check for SQL injection patterns
                if ($this->detectSqlInjection($value)) {
                    throw new SecurityException("Potential SQL injection detected in field: {$key}");
                }
                
                // Check for XSS attempts
                if ($this->detectXss($value)) {
                    throw new SecurityException("Potential XSS attempt detected in field: {$key}");
                }
                
                // Check for path traversal
                if ($this->detectPathTraversal($value)) {
                    throw new SecurityException("Potential path traversal detected in field: {$key}");
                }
                
                // Check for command injection
                if ($this->detectCommandInjection($value)) {
                    throw new SecurityException("Potential command injection detected in field: {$key}");
                }
            }
        }
    }

    /**
     * Detect SQL injection patterns
     */
    protected function detectSqlInjection(string $value): bool
    {
        $patterns = [
            '/(\bselect\b.*\bfrom\b)/i',
            '/(\bunion\b.*\bselect\b)/i',
            '/(\binsert\b.*\binto\b)/i',
            '/(\bupdate\b.*\bset\b)/i',
            '/(\bdelete\b.*\bfrom\b)/i',
            '/(\bdrop\b.*\btable\b)/i',
            '/(\btruncate\b.*\btable\b)/i',
            '/(\balter\b.*\btable\b)/i',
            '/(\bcreate\b.*\btable\b)/i',
            '/(\bexec\b|\bexecute\b)/i',
            '/(\bor\b.*\b1\s*=\s*1\b)/i',
            '/(\band\b.*\b1\s*=\s*1\b)/i',
            '/(\'.*\'.*=.*\'.*\')/i',
            '/(\-\-.*)/i',
            '/(\/\*.*\*\/)/i',
            '/(\bxp_cmdshell\b)/i',
            '/(\bsp_executesql\b)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect XSS attempts
     */
    protected function detectXss(string $value): bool
    {
        $patterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
            '/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/mi',
            '/<embed\b[^>]*>/mi',
            '/<applet\b[^<]*(?:(?!<\/applet>)<[^<]*)*<\/applet>/mi',
            '/javascript:/i',
            '/vbscript:/i',
            '/data:/i',
            '/on\w+\s*=\s*["\'][^"\']*["\']/i',
            '/<.*\s+(on\w+)\s*=\s*["\'][^"\']*["\'].*>/i',
            '/eval\s*\(/i',
            '/expression\s*\(/i',
            '/setTimeout\s*\(/i',
            '/setInterval\s*\(/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect path traversal attempts
     */
    protected function detectPathTraversal(string $value): bool
    {
        $patterns = [
            '/\.\.\//',
            '/\.\.\\\/',
            '/%2e%2e%2f/i',
            '/%2e%2e%5c/i',
            '/\.\.%2f/i',
            '/\.\.%5c/i',
            '/%252e%252e%252f/i',
            '/%252e%252e%255c/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect command injection attempts
     */
    protected function detectCommandInjection(string $value): bool
    {
        $patterns = [
            '/[;&|`$\(\)]/',
            '/\|\s*\w+/',
            '/&&\s*\w+/',
            '/;\s*\w+/',
            '/`[^`]*`/',
            '/\$\([^)]*\)/',
            '/\${[^}]*}/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate file uploads for security
     */
    protected function validateUploads(Request $request): void
    {
        $allFiles = $request->allFiles();
        
        // Check if request has any files
        if (empty($allFiles)) {
            return;
        }

        foreach ($allFiles as $fieldName => $files) {
            $files = is_array($files) ? $files : [$files];
            
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $this->validateSingleFile($file, $fieldName);
                }
            }
        }
    }

    /**
     * Validate a single uploaded file
     */
    protected function validateSingleFile($file, string $fieldName): void
    {
        // Check file size (max 10MB)
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file->getSize() > $maxSize) {
            throw new SecurityException("File size exceeds maximum allowed size for field: {$fieldName}");
        }

        // Check file extension
        $allowedExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'csv', 'zip', 'rar', '7z',
            'mp3', 'mp4', 'avi', 'mov', 'wmv'
        ];

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions)) {
            throw new SecurityException("File type not allowed for field: {$fieldName}");
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        $allowedMimeTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp', 'image/svg+xml',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain', 'text/csv',
            'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
            'audio/mpeg', 'video/mp4', 'video/x-msvideo', 'video/quicktime', 'video/x-ms-wmv'
        ];

        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new SecurityException("MIME type not allowed for field: {$fieldName}");
        }

        // Check for malicious content in file name
        $filename = $file->getClientOriginalName();
        if ($this->detectMaliciousFilename($filename)) {
            throw new SecurityException("Malicious filename detected for field: {$fieldName}");
        }

        // Additional checks for specific file types
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
            $this->validateImageFile($file, $fieldName);
        }

        if ($extension === 'svg') {
            $this->validateSvgFile($file, $fieldName);
        }
    }

    /**
     * Detect malicious filenames
     */
    protected function detectMaliciousFilename(string $filename): bool
    {
        $patterns = [
            '/\.php$/i',
            '/\.asp$/i',
            '/\.aspx$/i',
            '/\.jsp$/i',
            '/\.exe$/i',
            '/\.bat$/i',
            '/\.cmd$/i',
            '/\.com$/i',
            '/\.scr$/i',
            '/\.pif$/i',
            '/\.js$/i',
            '/\.vbs$/i',
            '/\.sh$/i',
            '/\.htaccess$/i',
            '/\.\./',
            '/[<>:"\\|?*]/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate image files
     */
    protected function validateImageFile($file, string $fieldName): void
    {
        // Verify it's actually an image
        $imageInfo = @getimagesize($file->getRealPath());
        if ($imageInfo === false) {
            throw new SecurityException("Invalid image file for field: {$fieldName}");
        }

        // Check image dimensions (max 4000x4000)
        if ($imageInfo[0] > 4000 || $imageInfo[1] > 4000) {
            throw new SecurityException("Image dimensions too large for field: {$fieldName}");
        }
    }

    /**
     * Validate SVG files
     */
    protected function validateSvgFile($file, string $fieldName): void
    {
        $content = file_get_contents($file->getRealPath());
        
        // Check for JavaScript in SVG
        if (preg_match('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', $content)) {
            throw new SecurityException("JavaScript detected in SVG file for field: {$fieldName}");
        }

        // Check for external references
        if (preg_match('/xlink:href\s*=\s*["\'](?:(?!data:)[^"\']*)["\']/', $content)) {
            throw new SecurityException("External references detected in SVG file for field: {$fieldName}");
        }
    }
}
