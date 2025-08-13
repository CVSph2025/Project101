<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ApiResponse
{
    /**
     * Create a successful API response
     */
    public static function success(
        mixed $data = null,
        string $message = 'Operation completed successfully',
        int $statusCode = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        $response['timestamp'] = now()->toISOString();
        $response['request_id'] = request()->header('X-Request-ID', uniqid());

        return response()->json($response, $statusCode);
    }

    /**
     * Create an error API response
     */
    public static function error(
        string $message = 'An error occurred',
        mixed $errors = null,
        int $statusCode = 400,
        string $errorCode = 'ERROR',
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        $response['timestamp'] = now()->toISOString();
        $response['request_id'] = request()->header('X-Request-ID', uniqid());

        // Add debug information in non-production environments
        if (!app()->environment('production')) {
            $response['debug'] = [
                'file' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['file'] ?? null,
                'line' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['line'] ?? null,
            ];
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Create a paginated success response
     */
    public static function paginated(
        mixed $data,
        string $message = 'Data retrieved successfully',
        array $paginationMeta = []
    ): JsonResponse {
        $meta = array_merge([
            'pagination' => $paginationMeta
        ], [
            'total_count' => $paginationMeta['total'] ?? 0,
            'per_page' => $paginationMeta['per_page'] ?? 15,
            'current_page' => $paginationMeta['current_page'] ?? 1,
            'last_page' => $paginationMeta['last_page'] ?? 1,
        ]);

        return self::success($data, $message, 200, $meta);
    }

    /**
     * Create a created resource response
     */
    public static function created(
        mixed $data = null,
        string $message = 'Resource created successfully'
    ): JsonResponse {
        return self::success($data, $message, 201);
    }

    /**
     * Create a no content response
     */
    public static function noContent(
        string $message = 'Operation completed successfully'
    ): JsonResponse {
        return self::success(null, $message, 204);
    }

    /**
     * Create a validation error response
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return self::error(
            message: $message,
            errors: $errors,
            statusCode: 422,
            errorCode: 'VALIDATION_ERROR'
        );
    }

    /**
     * Create an unauthorized response
     */
    public static function unauthorized(
        string $message = 'Authentication required'
    ): JsonResponse {
        return self::error(
            message: $message,
            statusCode: 401,
            errorCode: 'UNAUTHORIZED'
        );
    }

    /**
     * Create a forbidden response
     */
    public static function forbidden(
        string $message = 'Access denied'
    ): JsonResponse {
        return self::error(
            message: $message,
            statusCode: 403,
            errorCode: 'FORBIDDEN'
        );
    }

    /**
     * Create a not found response
     */
    public static function notFound(
        string $message = 'Resource not found'
    ): JsonResponse {
        return self::error(
            message: $message,
            statusCode: 404,
            errorCode: 'NOT_FOUND'
        );
    }

    /**
     * Create a rate limit exceeded response
     */
    public static function rateLimitExceeded(
        string $message = 'Rate limit exceeded',
        int $retryAfter = 60
    ): JsonResponse {
        return self::error(
            message: $message,
            statusCode: 429,
            errorCode: 'RATE_LIMIT_EXCEEDED',
            meta: ['retry_after' => $retryAfter]
        );
    }

    /**
     * Create an internal server error response
     */
    public static function serverError(
        string $message = 'Internal server error'
    ): JsonResponse {
        return self::error(
            message: $message,
            statusCode: 500,
            errorCode: 'INTERNAL_SERVER_ERROR'
        );
    }

    /**
     * Create a service unavailable response
     */
    public static function serviceUnavailable(
        string $message = 'Service temporarily unavailable'
    ): JsonResponse {
        return self::error(
            message: $message,
            statusCode: 503,
            errorCode: 'SERVICE_UNAVAILABLE'
        );
    }

    /**
     * Transform Laravel validation errors to a consistent format
     */
    public static function formatValidationErrors(array $errors): array
    {
        $formatted = [];
        
        foreach ($errors as $field => $messages) {
            $formatted[] = [
                'field' => $field,
                'messages' => is_array($messages) ? $messages : [$messages],
                'code' => 'VALIDATION_ERROR'
            ];
        }

        return $formatted;
    }
}
