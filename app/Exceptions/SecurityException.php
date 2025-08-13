<?php

namespace App\Exceptions;

use Exception;

class SecurityException extends Exception
{
    protected $errorCode;
    protected $securityLevel;

    public function __construct(
        string $message = 'Security violation detected',
        string $errorCode = 'SECURITY_VIOLATION',
        string $securityLevel = 'high',
        int $code = 403,
        ?Exception $previous = null
    ) {
        $this->errorCode = $errorCode;
        $this->securityLevel = $securityLevel;
        
        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getSecurityLevel(): string
    {
        return $this->securityLevel;
    }

    /**
     * Create a new instance for rate limiting violations
     */
    public static function rateLimitExceeded(string $action = 'action'): static
    {
        return new static(
            message: "Rate limit exceeded for {$action}. Please try again later.",
            errorCode: 'RATE_LIMIT_EXCEEDED',
            securityLevel: 'medium',
            code: 429
        );
    }

    /**
     * Create a new instance for suspicious activity
     */
    public static function suspiciousActivity(string $details = ''): static
    {
        return new static(
            message: "Suspicious activity detected. {$details}",
            errorCode: 'SUSPICIOUS_ACTIVITY',
            securityLevel: 'high'
        );
    }

    /**
     * Create a new instance for XSS attempts
     */
    public static function xssAttempt(): static
    {
        return new static(
            message: 'Cross-site scripting attempt detected',
            errorCode: 'XSS_ATTEMPT',
            securityLevel: 'critical'
        );
    }

    /**
     * Create a new instance for SQL injection attempts
     */
    public static function sqlInjectionAttempt(): static
    {
        return new static(
            message: 'SQL injection attempt detected',
            errorCode: 'SQL_INJECTION_ATTEMPT',
            securityLevel: 'critical'
        );
    }

    /**
     * Create a new instance for unauthorized access attempts
     */
    public static function unauthorizedAccess(string $resource = 'resource'): static
    {
        return new static(
            message: "Unauthorized access attempt to {$resource}",
            errorCode: 'UNAUTHORIZED_ACCESS',
            securityLevel: 'high'
        );
    }

    /**
     * Create a new instance for session violations
     */
    public static function sessionViolation(string $reason = ''): static
    {
        return new static(
            message: "Session security violation. {$reason}",
            errorCode: 'SESSION_VIOLATION',
            securityLevel: 'high',
            code: 401
        );
    }
}
