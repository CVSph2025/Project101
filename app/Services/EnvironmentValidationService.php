<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class EnvironmentValidationService
{
    /**
     * Validate all required environment variables for the application
     */
    public static function validateEnvironment(): array
    {
        $errors = [];
        $warnings = [];

        // Core Application Settings
        if (empty(config('app.key'))) {
            $errors[] = 'APP_KEY is required but not set. Run: php artisan key:generate';
        }

        if (empty(config('app.url'))) {
            $warnings[] = 'APP_URL should be set for proper URL generation';
        }

        // Database Configuration
        if (empty(config('database.default'))) {
            $errors[] = 'Database connection not configured';
        }

        $dbConnection = config('database.connections.' . config('database.default'));
        if (empty($dbConnection)) {
            $errors[] = 'Database connection configuration is invalid';
        }

        // Payment System (Stripe)
        $stripeSecret = config('services.stripe.secret');
        $stripeKey = config('services.stripe.key');
        
        if (empty($stripeSecret) || empty($stripeKey)) {
            if (app()->environment('production')) {
                $errors[] = 'Stripe configuration (STRIPE_SECRET, STRIPE_KEY) is required in production';
            } else {
                $warnings[] = 'Stripe not configured - payment features will be disabled';
            }
        }

        // Mail Configuration
        if (empty(config('mail.mailers.smtp.host')) && config('mail.default') === 'smtp') {
            $warnings[] = 'SMTP mail configuration incomplete - email features may not work';
        }

        // Session and Cache
        if (config('session.driver') === 'database' && !self::tableExists('sessions')) {
            $warnings[] = 'Session table not found - run: php artisan session:table && php artisan migrate';
        }

        if (config('cache.default') === 'database' && !self::tableExists('cache')) {
            $warnings[] = 'Cache table not found - run: php artisan cache:table && php artisan migrate';
        }

        // Queue Configuration
        if (config('queue.default') === 'database' && !self::tableExists('jobs')) {
            $warnings[] = 'Queue jobs table not found - ensure migrations are run';
        }

        // Social Authentication
        $socialProviders = ['facebook', 'google'];
        foreach ($socialProviders as $provider) {
            $clientId = config("services.{$provider}.client_id");
            $clientSecret = config("services.{$provider}.client_secret");
            
            if (empty($clientId) || empty($clientSecret)) {
                $warnings[] = "Social login for {$provider} not configured - {$provider} login will be disabled";
            }
        }

        // File Storage
        if (config('filesystems.default') === 's3') {
            $awsConfig = [
                'AWS_ACCESS_KEY_ID' => config('filesystems.disks.s3.key'),
                'AWS_SECRET_ACCESS_KEY' => config('filesystems.disks.s3.secret'),
                'AWS_DEFAULT_REGION' => config('filesystems.disks.s3.region'),
                'AWS_BUCKET' => config('filesystems.disks.s3.bucket'),
            ];

            foreach ($awsConfig as $key => $value) {
                if (empty($value)) {
                    $errors[] = "{$key} is required when using S3 storage";
                }
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'is_valid' => empty($errors)
        ];
    }

    /**
     * Check if a database table exists
     */
    private static function tableExists(string $table): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable($table);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get configuration status for display
     */
    public static function getConfigurationStatus(): array
    {
        $validation = self::validateEnvironment();
        
        return [
            'app_key' => !empty(config('app.key')) ? 'configured' : 'missing',
            'database' => !empty(config('database.default')) ? 'configured' : 'missing',
            'stripe' => (!empty(config('services.stripe.secret')) && !empty(config('services.stripe.key'))) ? 'configured' : 'missing',
            'mail' => !empty(config('mail.mailers.smtp.host')) ? 'configured' : 'missing',
            'cache' => config('cache.default'),
            'session' => config('session.driver'),
            'queue' => config('queue.default'),
            'storage' => config('filesystems.default'),
            'environment' => config('app.env'),
            'debug' => config('app.debug'),
            'validation' => $validation
        ];
    }

    /**
     * Log environment validation results
     */
    public static function logValidationResults(): void
    {
        $validation = self::validateEnvironment();
        
        if (!empty($validation['errors'])) {
            Log::error('Environment validation failed:', $validation['errors']);
        }
        
        if (!empty($validation['warnings'])) {
            Log::warning('Environment validation warnings:', $validation['warnings']);
        }
        
        if ($validation['is_valid']) {
            Log::info('Environment validation passed successfully');
        }
    }

    /**
     * Create a health check response
     */
    public static function createHealthCheckResponse(): array
    {
        $status = self::getConfigurationStatus();
        $validation = $status['validation'];
        
        return [
            'status' => $validation['is_valid'] ? 'healthy' : 'degraded',
            'timestamp' => now()->toISOString(),
            'environment' => $status['environment'],
            'debug_mode' => $status['debug'],
            'services' => [
                'database' => $status['database'] === 'configured' ? 'up' : 'down',
                'cache' => $status['cache'],
                'session' => $status['session'],
                'queue' => $status['queue'],
                'storage' => $status['storage'],
                'stripe' => $status['stripe'] === 'configured' ? 'up' : 'down',
                'mail' => $status['mail'] === 'configured' ? 'up' : 'down',
            ],
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings']
        ];
    }
}
