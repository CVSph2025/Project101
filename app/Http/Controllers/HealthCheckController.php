<?php

namespace App\Http\Controllers;

use App\Services\EnvironmentValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class HealthCheckController extends Controller
{
    /**
     * Comprehensive health check endpoint
     */
    public function index()
    {
        $checks = [
            'app' => $this->checkApplication(),
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'environment' => $this->checkEnvironment(),
        ];

        $overallStatus = $this->determineOverallStatus($checks);

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env')
        ], $overallStatus === 'healthy' ? 200 : 503);
    }

    /**
     * Simple health check for load balancers
     */
    public function simple()
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Database connectivity check
     */
    public function database()
    {
        try {
            DB::connection()->getPdo();
            $tableCount = DB::select("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ?", [config('database.connections.mysql.database')])[0]->count ?? 0;
            
            return response()->json([
                'status' => 'healthy',
                'connection' => config('database.default'),
                'tables' => $tableCount,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => 'Database connection failed',
                'message' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 503);
        }
    }

    /**
     * Environment configuration check
     */
    public function environment()
    {
        return response()->json(
            EnvironmentValidationService::createHealthCheckResponse()
        );
    }

    private function checkApplication(): array
    {
        return [
            'status' => 'healthy',
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'app_key_set' => !empty(config('app.key')),
            'debug_mode' => config('app.debug'),
            'timezone' => config('app.timezone'),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $latency = round((microtime(true) - $start) * 1000, 2);

            // Test a simple query
            $result = DB::select('SELECT 1 as test');
            
            return [
                'status' => 'healthy',
                'connection' => config('database.default'),
                'latency_ms' => $latency,
                'query_test' => !empty($result)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => 'Database connection failed',
                'message' => $e->getMessage()
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test_value';
            
            // Test cache write
            Cache::put($testKey, $testValue, 60);
            
            // Test cache read
            $retrieved = Cache::get($testKey);
            
            // Cleanup
            Cache::forget($testKey);
            
            return [
                'status' => $retrieved === $testValue ? 'healthy' : 'unhealthy',
                'driver' => config('cache.default'),
                'read_write_test' => $retrieved === $testValue
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'driver' => config('cache.default'),
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $testFile = 'health_check_' . time() . '.txt';
            $testContent = 'Health check test content';
            
            // Test storage write
            Storage::put($testFile, $testContent);
            
            // Test storage read
            $retrieved = Storage::get($testFile);
            
            // Test storage delete
            Storage::delete($testFile);
            
            return [
                'status' => $retrieved === $testContent ? 'healthy' : 'unhealthy',
                'driver' => config('filesystems.default'),
                'read_write_test' => $retrieved === $testContent
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'driver' => config('filesystems.default'),
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkEnvironment(): array
    {
        $validation = EnvironmentValidationService::validateEnvironment();
        
        return [
            'status' => $validation['is_valid'] ? 'healthy' : 'degraded',
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
            'critical_config_missing' => !empty($validation['errors'])
        ];
    }

    private function determineOverallStatus(array $checks): string
    {
        foreach ($checks as $check) {
            if ($check['status'] === 'unhealthy') {
                return 'unhealthy';
            }
        }

        foreach ($checks as $check) {
            if ($check['status'] === 'degraded') {
                return 'degraded';
            }
        }

        return 'healthy';
    }
}
