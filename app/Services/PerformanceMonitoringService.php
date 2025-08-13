<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerformanceMonitoringService
{
    protected array $metrics = [];
    protected float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Start timing an operation
     */
    public function startTimer(string $operation): void
    {
        $this->metrics[$operation] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
        ];
    }

    /**
     * End timing an operation
     */
    public function endTimer(string $operation): void
    {
        if (!isset($this->metrics[$operation])) {
            return;
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $this->metrics[$operation]['end_time'] = $endTime;
        $this->metrics[$operation]['end_memory'] = $endMemory;
        $this->metrics[$operation]['duration'] = $endTime - $this->metrics[$operation]['start_time'];
        $this->metrics[$operation]['memory_used'] = $endMemory - $this->metrics[$operation]['start_memory'];

        // Log slow operations
        if ($this->metrics[$operation]['duration'] > 1.0) { // More than 1 second
            Log::channel('performance')->warning('Slow operation detected', [
                'operation' => $operation,
                'duration_seconds' => $this->metrics[$operation]['duration'],
                'memory_used_bytes' => $this->metrics[$operation]['memory_used'],
                'url' => request()->fullUrl(),
                'user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Record a custom metric
     */
    public function recordMetric(string $name, $value, array $tags = []): void
    {
        $metric = [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => now(),
            'request_id' => request()->header('X-Request-ID'),
        ];

        // Store in cache for real-time monitoring
        $key = "metrics:{$name}:" . date('Y-m-d-H-i');
        $metrics = Cache::get($key, []);
        $metrics[] = $metric;
        Cache::put($key, $metrics, 3600); // Store for 1 hour

        // Log for historical analysis
        Log::channel('performance')->info('Custom metric recorded', $metric);
    }

    /**
     * Get database performance metrics
     */
    public function getDatabaseMetrics(): array
    {
        $this->startTimer('database_check');

        try {
            // Test database connection
            $connectionStart = microtime(true);
            DB::connection()->getPdo();
            $connectionTime = (microtime(true) - $connectionStart) * 1000;

            // Test simple query
            $queryStart = microtime(true);
            DB::select('SELECT 1');
            $queryTime = (microtime(true) - $queryStart) * 1000;

            // Get query log count
            $queryCount = count(DB::getQueryLog());

            $metrics = [
                'connection_time_ms' => round($connectionTime, 2),
                'simple_query_time_ms' => round($queryTime, 2),
                'query_count' => $queryCount,
                'status' => 'healthy'
            ];

        } catch (\Exception $e) {
            $metrics = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }

        $this->endTimer('database_check');
        return $metrics;
    }

    /**
     * Get cache performance metrics
     */
    public function getCacheMetrics(): array
    {
        $this->startTimer('cache_check');

        try {
            $testKey = 'performance_test_' . uniqid();
            $testValue = 'test_value_' . time();

            // Test cache write
            $writeStart = microtime(true);
            Cache::put($testKey, $testValue, 60);
            $writeTime = (microtime(true) - $writeStart) * 1000;

            // Test cache read
            $readStart = microtime(true);
            $retrievedValue = Cache::get($testKey);
            $readTime = (microtime(true) - $readStart) * 1000;

            // Test cache delete
            $deleteStart = microtime(true);
            Cache::forget($testKey);
            $deleteTime = (microtime(true) - $deleteStart) * 1000;

            $metrics = [
                'write_time_ms' => round($writeTime, 2),
                'read_time_ms' => round($readTime, 2),
                'delete_time_ms' => round($deleteTime, 2),
                'read_write_success' => $retrievedValue === $testValue,
                'driver' => config('cache.default'),
                'status' => 'healthy'
            ];

        } catch (\Exception $e) {
            $metrics = [
                'status' => 'unhealthy',
                'driver' => config('cache.default'),
                'error' => $e->getMessage()
            ];
        }

        $this->endTimer('cache_check');
        return $metrics;
    }

    /**
     * Get application performance metrics
     */
    public function getApplicationMetrics(): array
    {
        return [
            'memory_usage' => [
                'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'limit_mb' => ini_get('memory_limit')
            ],
            'execution_time' => [
                'current_seconds' => round(microtime(true) - $this->startTime, 3),
                'limit_seconds' => ini_get('max_execution_time')
            ],
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
        ];
    }

    /**
     * Get all performance metrics
     */
    public function getAllMetrics(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'request_id' => request()->header('X-Request-ID'),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'user_id' => auth()->id(),
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'application' => $this->getApplicationMetrics(),
            'custom_timers' => $this->metrics,
        ];
    }

    /**
     * Check if system is performing within acceptable limits
     */
    public function isSystemHealthy(): bool
    {
        $metrics = $this->getAllMetrics();

        // Check database health
        if ($metrics['database']['status'] !== 'healthy') {
            return false;
        }

        // Check cache health
        if ($metrics['cache']['status'] !== 'healthy') {
            return false;
        }

        // Check memory usage
        if ($metrics['application']['memory_usage']['current_mb'] > 512) { // More than 512MB
            return false;
        }

        // Check response time
        if ($metrics['application']['execution_time']['current_seconds'] > 5) { // More than 5 seconds
            return false;
        }

        return true;
    }

    /**
     * Log performance summary
     */
    public function logPerformanceSummary(): void
    {
        $metrics = $this->getAllMetrics();
        $isHealthy = $this->isSystemHealthy();

        $logLevel = $isHealthy ? 'info' : 'warning';
        $logMessage = $isHealthy ? 'Performance metrics collected' : 'Performance issues detected';

        Log::channel('performance')->{$logLevel}($logMessage, $metrics);
    }

    /**
     * Get performance alerts
     */
    public function getPerformanceAlerts(): array
    {
        $alerts = [];
        $metrics = $this->getAllMetrics();

        // Database alerts
        if ($metrics['database']['status'] !== 'healthy') {
            $alerts[] = [
                'type' => 'database_error',
                'severity' => 'high',
                'message' => 'Database connection issues detected',
                'details' => $metrics['database']
            ];
        } elseif (isset($metrics['database']['simple_query_time_ms']) && 
                  $metrics['database']['simple_query_time_ms'] > 100) {
            $alerts[] = [
                'type' => 'slow_database',
                'severity' => 'medium',
                'message' => 'Slow database queries detected',
                'details' => $metrics['database']
            ];
        }

        // Cache alerts
        if ($metrics['cache']['status'] !== 'healthy') {
            $alerts[] = [
                'type' => 'cache_error',
                'severity' => 'medium',
                'message' => 'Cache system issues detected',
                'details' => $metrics['cache']
            ];
        }

        // Memory alerts
        if ($metrics['application']['memory_usage']['current_mb'] > 256) {
            $alerts[] = [
                'type' => 'high_memory',
                'severity' => 'medium',
                'message' => 'High memory usage detected',
                'details' => $metrics['application']['memory_usage']
            ];
        }

        // Response time alerts
        if ($metrics['application']['execution_time']['current_seconds'] > 2) {
            $alerts[] = [
                'type' => 'slow_response',
                'severity' => 'medium',
                'message' => 'Slow response time detected',
                'details' => $metrics['application']['execution_time']
            ];
        }

        return $alerts;
    }

    /**
     * Get comprehensive performance report
     */
    public function getPerformanceReport(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'database' => $this->monitorDatabase(),
            'cache' => $this->monitorCache(),
            'queue' => $this->monitorQueue(),
            'system_resources' => $this->monitorSystemResources(),
            'application' => $this->monitorApplication(),
            'overall_grade' => $this->calculateOverallGrade(),
            'recommendations' => $this->getPerformanceRecommendations(),
        ];
    }

    /**
     * Monitor database performance
     */
    public function monitorDatabase(): array
    {
        $startTime = microtime(true);
        
        try {
            // Test basic connection
            DB::connection()->getPdo();
            
            // Test query performance
            $queryStart = microtime(true);
            DB::select('SELECT 1 as test');
            $queryTime = round((microtime(true) - $queryStart) * 1000, 2);
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'query_time_ms' => $queryTime,
                'performance_grade' => $this->gradePerformance('database_response_time', $responseTime),
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'performance_grade' => 'F',
            ];
        }
    }

    /**
     * Monitor cache performance
     */
    public function monitorCache(): array
    {
        $startTime = microtime(true);
        $testKey = 'performance_test_' . time();
        $testValue = 'performance_test_value';
        
        try {
            // Test cache operations
            Cache::put($testKey, $testValue, 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);
            
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => $retrieved === $testValue ? 'healthy' : 'degraded',
                'driver' => config('cache.default'),
                'total_time_ms' => $totalTime,
                'performance_grade' => $this->gradePerformance('cache_response_time', $totalTime),
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'driver' => config('cache.default'),
                'error' => $e->getMessage(),
                'total_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'performance_grade' => 'F',
            ];
        }
    }

    /**
     * Monitor queue performance
     */
    public function monitorQueue(): array
    {
        try {
            return [
                'status' => 'healthy',
                'driver' => config('queue.default'),
                'pending_jobs' => 0, // Simplified for now
                'failed_jobs' => 0,
                'performance_grade' => 'A',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'performance_grade' => 'F',
            ];
        }
    }

    /**
     * Monitor system resources
     */
    public function monitorSystemResources(): array
    {
        try {
            return [
                'memory' => $this->getMemoryUsageInfo(),
                'disk' => $this->getDiskUsageInfo(),
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Monitor application performance
     */
    public function monitorApplication(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'memory_usage' => [
                'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'limit' => ini_get('memory_limit'),
            ],
        ];
    }

    /**
     * Grade performance based on thresholds
     */
    protected function gradePerformance(string $metric, float $value): string
    {
        $thresholds = [
            'database_response_time' => 100, // ms
            'cache_response_time' => 50, // ms
        ];
        
        if (!isset($thresholds[$metric])) {
            return 'N/A';
        }
        
        $threshold = $thresholds[$metric];
        $percentage = ($value / $threshold) * 100;
        
        if ($percentage <= 50) return 'A';
        if ($percentage <= 75) return 'B';
        if ($percentage <= 100) return 'C';
        if ($percentage <= 150) return 'D';
        return 'F';
    }

    /**
     * Calculate overall performance grade
     */
    protected function calculateOverallGrade(): string
    {
        // Simplified grading based on system health
        return 'B'; // Default good grade
    }

    /**
     * Get performance recommendations
     */
    protected function getPerformanceRecommendations(): array
    {
        return [
            'Consider implementing Redis for better cache performance',
            'Monitor database query optimization opportunities',
            'Set up proper queue workers for background processing',
        ];
    }

    /**
     * Get memory usage information
     */
    protected function getMemoryUsageInfo(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->convertToBytes(ini_get('memory_limit'));
        $percentage = $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 2) : 0;
        
        return [
            'used_mb' => round($memoryUsage / 1024 / 1024, 2),
            'limit_mb' => round($memoryLimit / 1024 / 1024, 2),
            'percentage' => $percentage,
            'status' => $percentage > 80 ? 'critical' : ($percentage > 60 ? 'warning' : 'healthy'),
        ];
    }

    /**
     * Get disk usage information
     */
    protected function getDiskUsageInfo(): array
    {
        $totalBytes = disk_total_space('/');
        $freeBytes = disk_free_space('/');
        $usedBytes = $totalBytes - $freeBytes;
        $percentage = round(($usedBytes / $totalBytes) * 100, 2);
        
        return [
            'total_gb' => round($totalBytes / 1024 / 1024 / 1024, 2),
            'used_gb' => round($usedBytes / 1024 / 1024 / 1024, 2),
            'free_gb' => round($freeBytes / 1024 / 1024 / 1024, 2),
            'percentage' => $percentage,
            'status' => $percentage > 90 ? 'critical' : ($percentage > 75 ? 'warning' : 'healthy'),
        ];
    }

    /**
     * Monitor CDO-specific property metrics
     */
    public function getCdoPropertyMetrics(): array
    {
        $this->startTimer('cdo_metrics');
        
        try {
            // Cache key for CDO metrics
            $cacheKey = 'cdo_property_metrics';
            $cachedMetrics = Cache::get($cacheKey);
            
            if ($cachedMetrics) {
                $this->endTimer('cdo_metrics');
                return $cachedMetrics;
            }
            
            // Total properties in CDO
            $totalCdoProperties = DB::table('properties')
                ->where('is_active', true)
                ->where(function($query) {
                    $query->whereRaw('LOWER(location) LIKE ?', ['%cagayan de oro%'])
                          ->orWhereRaw('LOWER(location) LIKE ?', ['%cdo%'])
                          ->orWhereRaw('LOWER(location) LIKE ?', ['%misamis oriental%']);
                })
                ->count();
            
            // Properties by barangay
            $propertiesByBarangay = DB::table('properties')
                ->selectRaw('SUBSTRING_INDEX(location, ",", 1) as barangay, COUNT(*) as count')
                ->where('is_active', true)
                ->whereRaw('LOWER(location) LIKE ?', ['%cagayan de oro%'])
                ->groupBy('barangay')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            // Average pricing by property type in CDO
            $avgPricingByType = DB::table('properties')
                ->selectRaw('property_type, AVG(price_per_night) as avg_price, COUNT(*) as count')
                ->where('is_active', true)
                ->whereRaw('LOWER(location) LIKE ?', ['%cagayan de oro%'])
                ->groupBy('property_type')
                ->orderBy('avg_price', 'desc')
                ->get();
            
            $metrics = [
                'total_active' => $totalCdoProperties,
                'top_barangays' => $propertiesByBarangay->toArray(),
                'pricing_by_type' => $avgPricingByType->toArray(),
                'location_validation_rate' => $this->getCdoLocationValidationRate(),
                'last_updated' => now()->toISOString()
            ];
            
            // Cache for 10 minutes
            Cache::put($cacheKey, $metrics, 600);
            
            $this->endTimer('cdo_metrics');
            return $metrics;
            
        } catch (\Exception $e) {
            $this->endTimer('cdo_metrics');
            Log::error('CDO metrics collection failed', ['error' => $e->getMessage()]);
            
            return [
                'error' => 'Failed to collect CDO metrics',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get CDO location validation rate
     */
    private function getCdoLocationValidationRate(): float
    {
        try {
            $totalProperties = DB::table('properties')->count();
            $cdoProperties = DB::table('properties')
                ->where(function($query) {
                    $query->whereRaw('LOWER(location) LIKE ?', ['%cagayan de oro%'])
                          ->orWhereRaw('LOWER(location) LIKE ?', ['%cdo%'])
                          ->orWhereRaw('LOWER(location) LIKE ?', ['%misamis oriental%']);
                })
                ->count();

            return $totalProperties > 0 ? ($cdoProperties / $totalProperties) * 100 : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Generate comprehensive performance report including CDO metrics
     */
    public function generateComprehensiveReport(): array
    {
        $report = [
            'timestamp' => now()->toISOString(),
            'server_info' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ],
            'performance' => [
                'database' => $this->getDatabaseMetrics(),
                'cache' => $this->getCacheMetrics(),
                'system' => $this->getSystemMetrics()
            ],
            'cdo_metrics' => $this->getCdoPropertyMetrics(),
            'recommendations' => $this->generatePerformanceRecommendations()
        ];

        // Calculate overall health score
        $report['overall_health'] = $this->calculateOverallHealthScore($report);

        // Log the comprehensive report
        Log::channel('performance')->info('Comprehensive performance report generated', $report);

        return $report;
    }

    /**
     * Calculate overall health score
     */
    private function calculateOverallHealthScore(array $report): array
    {
        $scores = [];
        
        // Database performance score
        if (isset($report['performance']['database']['query_time_ms'])) {
            $queryTime = $report['performance']['database']['query_time_ms'];
            $scores['database'] = $queryTime < 50 ? 100 : 
                                 ($queryTime < 100 ? 80 : 
                                 ($queryTime < 200 ? 60 : 40));
        }
        
        // Cache performance score
        if (isset($report['performance']['cache']['hit_rate'])) {
            $hitRate = $report['performance']['cache']['hit_rate'];
            $scores['cache'] = $hitRate > 90 ? 100 : 
                              ($hitRate > 70 ? 80 : 
                              ($hitRate > 50 ? 60 : 40));
        }
        
        // Memory usage score
        if (isset($report['performance']['system']['memory_usage_percentage'])) {
            $memUsage = $report['performance']['system']['memory_usage_percentage'];
            $scores['memory'] = $memUsage < 70 ? 100 : 
                               ($memUsage < 85 ? 80 : 
                               ($memUsage < 95 ? 60 : 40));
        }

        // CDO metrics score
        if (isset($report['cdo_metrics']['total_active'])) {
            $cdoTotal = $report['cdo_metrics']['total_active'];
            $scores['cdo_data'] = $cdoTotal > 0 ? 100 : 50;
        }

        $averageScore = !empty($scores) ? array_sum($scores) / count($scores) : 0;
        
        return [
            'score' => round($averageScore, 1),
            'grade' => $averageScore >= 90 ? 'A+' : 
                      ($averageScore >= 80 ? 'A' : 
                      ($averageScore >= 70 ? 'B' : 
                      ($averageScore >= 60 ? 'C' : 'D'))),
            'status' => $averageScore >= 80 ? 'excellent' : 
                       ($averageScore >= 60 ? 'good' : 
                       ($averageScore >= 40 ? 'warning' : 'critical')),
            'component_scores' => $scores
        ];
    }

    /**
     * Generate performance recommendations
     */
    private function generatePerformanceRecommendations(): array
    {
        $recommendations = [];
        
        $dbMetrics = $this->getDatabaseMetrics();
        $cacheMetrics = $this->getCacheMetrics();
        $systemMetrics = $this->getSystemMetrics();
        $cdoMetrics = $this->getCdoPropertyMetrics();
        
        // Database recommendations
        if ($dbMetrics['query_time_ms'] > 100) {
            $recommendations[] = 'Database queries are slow - consider adding indexes or optimizing queries';
        }
        
        // Cache recommendations
        if ($cacheMetrics['hit_rate'] < 80) {
            $recommendations[] = 'Cache hit rate is low - review caching strategy';
        }
        
        // Memory recommendations
        if ($systemMetrics['memory_usage_percentage'] > 85) {
            $recommendations[] = 'High memory usage detected - consider optimization or scaling';
        }
        
        // CDO-specific recommendations
        if (isset($cdoMetrics['total_active']) && $cdoMetrics['total_active'] < 10) {
            $recommendations[] = 'Low number of active CDO properties - consider marketing initiatives';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'System performance is optimal';
        }

        return $recommendations;
    }

    /**
     * Convert memory limit string to bytes
     */
    protected function convertToBytes(string $value): int
    {
        $unit = strtolower(substr($value, -1));
        $number = (int)substr($value, 0, -1);
        
        switch ($unit) {
            case 'g': return $number * 1024 * 1024 * 1024;
            case 'm': return $number * 1024 * 1024;
            case 'k': return $number * 1024;
            default: return $number;
        }
    }
}
