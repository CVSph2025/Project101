<?php

namespace App\Exceptions;

use Exception;

class PaymentException extends Exception
{
    protected $paymentGateway;
    protected $gatewayErrorCode;
    protected $retryable;

    public function __construct(
        string $message,
        string $paymentGateway = 'stripe',
        string $gatewayErrorCode = null,
        bool $retryable = false,
        int $code = 402,
        ?Exception $previous = null
    ) {
        $this->paymentGateway = $paymentGateway;
        $this->gatewayErrorCode = $gatewayErrorCode;
        $this->retryable = $retryable;
        
        parent::__construct($message, $code, $previous);
    }

    public function getPaymentGateway(): string
    {
        return $this->paymentGateway;
    }

    public function getGatewayErrorCode(): ?string
    {
        return $this->gatewayErrorCode;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
