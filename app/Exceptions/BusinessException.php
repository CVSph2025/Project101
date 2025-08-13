<?php

namespace App\Exceptions;

use Exception;

class BusinessException extends Exception
{
    protected $errorCode;
    protected $errors;
    protected $statusCode;

    public function __construct(
        string $message = 'A business logic error occurred',
        string $errorCode = 'BUSINESS_ERROR',
        array $errors = [],
        int $statusCode = 400,
        int $code = 0,
        ?Exception $previous = null
    ) {
        $this->errorCode = $errorCode;
        $this->errors = $errors;
        $this->statusCode = $statusCode;
        
        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Create a new instance for booking conflicts
     */
    public static function bookingConflict(string $details = ''): static
    {
        return new static(
            message: "Booking conflict detected. {$details}",
            errorCode: 'BOOKING_CONFLICT',
            statusCode: 409
        );
    }

    /**
     * Create a new instance for payment failures
     */
    public static function paymentFailed(string $reason = '', array $errors = []): static
    {
        return new static(
            message: "Payment processing failed. {$reason}",
            errorCode: 'PAYMENT_FAILED',
            errors: $errors,
            statusCode: 402
        );
    }

    /**
     * Create a new instance for property unavailability
     */
    public static function propertyUnavailable(string $propertyId = ''): static
    {
        return new static(
            message: "Property {$propertyId} is not available for the selected dates",
            errorCode: 'PROPERTY_UNAVAILABLE',
            statusCode: 409
        );
    }

    /**
     * Create a new instance for insufficient permissions
     */
    public static function insufficientPermissions(string $action = 'action'): static
    {
        return new static(
            message: "Insufficient permissions to perform {$action}",
            errorCode: 'INSUFFICIENT_PERMISSIONS',
            statusCode: 403
        );
    }

    /**
     * Create a new instance for quota exceeded
     */
    public static function quotaExceeded(string $resource = 'resource', int $limit = 0): static
    {
        return new static(
            message: "Quota exceeded for {$resource}. Limit: {$limit}",
            errorCode: 'QUOTA_EXCEEDED',
            statusCode: 429
        );
    }

    /**
     * Create a new instance for invalid state transitions
     */
    public static function invalidStateTransition(string $from = '', string $to = ''): static
    {
        return new static(
            message: "Invalid state transition from '{$from}' to '{$to}'",
            errorCode: 'INVALID_STATE_TRANSITION',
            statusCode: 400
        );
    }

    /**
     * Create a new instance for duplicate entries
     */
    public static function duplicateEntry(string $field = 'field'): static
    {
        return new static(
            message: "Duplicate entry detected for {$field}",
            errorCode: 'DUPLICATE_ENTRY',
            statusCode: 409
        );
    }

    /**
     * Create a new instance for configuration errors
     */
    public static function configurationError(string $service = 'service'): static
    {
        return new static(
            message: "Configuration error for {$service}",
            errorCode: 'CONFIGURATION_ERROR',
            statusCode: 500
        );
    }
}
