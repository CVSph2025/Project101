<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        SecurityException::class => 'critical',
        BusinessException::class => 'warning',
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        ValidationException::class,
        AuthenticationException::class,
        AccessDeniedHttpException::class,
        NotFoundHttpException::class,
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'token',
        'secret',
        'api_key',
        'stripe_key',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            if ($this->shouldReportToExternalService($e)) {
                $this->reportToExternalService($e);
            }
        });

        $this->renderable(function (Throwable $e, Request $request) {
            return $this->handleApiExceptions($e, $request);
        });
    }

    /**
     * Handle API exceptions with standardized responses
     */
    protected function handleApiExceptions(Throwable $e, Request $request): ?Response
    {
        // Only handle API requests
        if (!$request->expectsJson() && !$request->is('api/*')) {
            return null;
        }

        // Handle specific exception types
        return match (true) {
            $e instanceof ValidationException => $this->handleValidationException($e),
            $e instanceof ModelNotFoundException => $this->handleModelNotFoundException($e),
            $e instanceof AuthenticationException => $this->handleAuthenticationException($e),
            $e instanceof AccessDeniedHttpException => $this->handleAccessDeniedException($e),
            $e instanceof NotFoundHttpException => $this->handleNotFoundException($e),
            $e instanceof SecurityException => $this->handleSecurityException($e),
            $e instanceof BusinessException => $this->handleBusinessException($e),
            $e instanceof HttpException => $this->handleHttpException($e),
            default => $this->handleGenericException($e, $request)
        };
    }

    /**
     * Handle validation exceptions
     */
    protected function handleValidationException(ValidationException $e): Response
    {
        return ApiResponse::error(
            message: 'Validation failed',
            errors: $e->errors(),
            statusCode: 422,
            errorCode: 'VALIDATION_ERROR'
        );
    }

    /**
     * Handle model not found exceptions
     */
    protected function handleModelNotFoundException(ModelNotFoundException $e): Response
    {
        $model = class_basename($e->getModel());
        
        return ApiResponse::error(
            message: "The requested {$model} was not found",
            statusCode: 404,
            errorCode: 'RESOURCE_NOT_FOUND'
        );
    }

    /**
     * Handle authentication exceptions
     */
    protected function handleAuthenticationException(AuthenticationException $e): Response
    {
        return ApiResponse::error(
            message: 'Authentication required',
            statusCode: 401,
            errorCode: 'AUTHENTICATION_REQUIRED'
        );
    }

    /**
     * Handle access denied exceptions
     */
    protected function handleAccessDeniedException(AccessDeniedHttpException $e): Response
    {
        return ApiResponse::error(
            message: 'Access denied. Insufficient permissions.',
            statusCode: 403,
            errorCode: 'ACCESS_DENIED'
        );
    }

    /**
     * Handle not found exceptions
     */
    protected function handleNotFoundException(NotFoundHttpException $e): Response
    {
        return ApiResponse::error(
            message: 'The requested resource was not found',
            statusCode: 404,
            errorCode: 'RESOURCE_NOT_FOUND'
        );
    }

    /**
     * Handle security exceptions
     */
    protected function handleSecurityException(SecurityException $e): Response
    {
        // Log security incidents
        Log::channel('security')->critical('Security exception occurred', [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => auth()->id(),
            'url' => request()->fullUrl(),
        ]);

        return ApiResponse::error(
            message: 'Security violation detected',
            statusCode: 403,
            errorCode: 'SECURITY_VIOLATION'
        );
    }

    /**
     * Handle business logic exceptions
     */
    protected function handleBusinessException(BusinessException $e): Response
    {
        return ApiResponse::error(
            message: $e->getMessage(),
            statusCode: $e->getStatusCode(),
            errorCode: $e->getErrorCode(),
            errors: $e->getErrors()
        );
    }

    /**
     * Handle HTTP exceptions
     */
    protected function handleHttpException(HttpException $e): Response
    {
        return ApiResponse::error(
            message: $e->getMessage() ?: 'An HTTP error occurred',
            statusCode: $e->getStatusCode(),
            errorCode: 'HTTP_ERROR'
        );
    }

    /**
     * Handle generic exceptions
     */
    protected function handleGenericException(Throwable $e, Request $request): Response
    {
        // Log the error for debugging
        Log::error('Unhandled exception occurred', [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'request_data' => $request->except($this->dontFlash),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
        ]);

        // Don't expose internal errors in production
        $message = app()->environment('production') 
            ? 'An unexpected error occurred. Please try again later.'
            : $e->getMessage();

        return ApiResponse::error(
            message: $message,
            statusCode: 500,
            errorCode: 'INTERNAL_SERVER_ERROR'
        );
    }

    /**
     * Determine if the exception should be reported to external service
     */
    protected function shouldReportToExternalService(Throwable $e): bool
    {
        // Don't report common exceptions
        if ($e instanceof ValidationException || 
            $e instanceof AuthenticationException ||
            $e instanceof AccessDeniedHttpException ||
            $e instanceof NotFoundHttpException) {
            return false;
        }

        // Only report in production
        return app()->environment('production');
    }

    /**
     * Report exception to external monitoring service
     */
    protected function reportToExternalService(Throwable $e): void
    {
        // Here you would integrate with services like:
        // - Sentry
        // - Bugsnag
        // - Rollbar
        // - New Relic
        
        // Example for Sentry:
        // app('sentry')->captureException($e);
        
        Log::info('Exception reported to external service', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    /**
     * Get the default context variables for logging.
     */
    protected function context(): array
    {
        return array_filter([
            'userId' => auth()->id(),
            'ip' => request()->ip(),
            'userAgent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ]);
    }
}
