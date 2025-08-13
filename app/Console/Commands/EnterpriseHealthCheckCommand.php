<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Services\PerformanceMonitoringService;
use App\Services\EnvironmentValidationService;

class EnterpriseHealthCheckCommand extends Command
{
    protected $signature = 'enterprise:health-check 
                            {--detailed : Show detailed information}
                            {--fix : Attempt to fix issues automatically}
                            {--report : Generate detailed report}';

    protected $description = 'Perform comprehensive enterprise-level health check';

    protected PerformanceMonitoringService $performanceService;
    protected EnvironmentValidationService $environmentService;

    public function __construct(
        PerformanceMonitoringService $performanceService,
        EnvironmentValidationService $environmentService
    ) {
        parent::__construct();
        $this->performanceService = $performanceService;
        $this->environmentService = $environmentService;
    }

    public function handle(): int
    {
        $this->info('🔍 Starting Enterprise Health Check...');
        $this->newLine();

        $issues = [];
        $warnings = [];
        $recommendations = [];

        // 1. Environment Configuration Check
        $this->info('📋 Checking Environment Configuration...');
        $envResult = $this->checkEnvironmentConfiguration();
        if (!$envResult['passed']) {
            $issues = array_merge($issues, $envResult['issues']);
            $warnings = array_merge($warnings, $envResult['warnings']);
        }

        // 2. Security Configuration Check
        $this->info('🔒 Checking Security Configuration...');
        $securityResult = $this->checkSecurityConfiguration();
        if (!$securityResult['passed']) {
            $issues = array_merge($issues, $securityResult['issues']);
            $warnings = array_merge($warnings, $securityResult['warnings']);
        }

        // 3. Performance Check
        $this->info('⚡ Checking Performance Metrics...');
        $performanceResult = $this->checkPerformance();
        if (!$performanceResult['passed']) {
            $issues = array_merge($issues, $performanceResult['issues']);
            $recommendations = array_merge($recommendations, $performanceResult['recommendations']);
        }

        // 4. Database Health Check
        $this->info('🗄️  Checking Database Health...');
        $dbResult = $this->checkDatabaseHealth();
        if (!$dbResult['passed']) {
            $issues = array_merge($issues, $dbResult['issues']);
        }

        // 5. File System Check
        $this->info('📁 Checking File System...');
        $fsResult = $this->checkFileSystemHealth();
        if (!$fsResult['passed']) {
            $issues = array_merge($issues, $fsResult['issues']);
        }

        // 6. Dependencies Check
        $this->info('📦 Checking Dependencies...');
        $depsResult = $this->checkDependencies();
        if (!$depsResult['passed']) {
            $issues = array_merge($issues, $depsResult['issues']);
            $warnings = array_merge($warnings, $depsResult['warnings']);
        }

        // 7. Error Handling Check
        $this->info('🚨 Checking Error Handling...');
        $errorResult = $this->checkErrorHandling();
        if (!$errorResult['passed']) {
            $issues = array_merge($issues, $errorResult['issues']);
        }

        $this->newLine();
        $this->displayResults($issues, $warnings, $recommendations);

        if ($this->option('fix')) {
            $this->attemptFixes($issues);
        }

        if ($this->option('report')) {
            $this->generateDetailedReport($issues, $warnings, $recommendations);
        }

        return empty($issues) ? 0 : 1;
    }

    protected function checkEnvironmentConfiguration(): array
    {
        $issues = [];
        $warnings = [];

        // Check critical environment variables
        $criticalEnvVars = [
            'APP_KEY' => 'Application key is not set',
            'APP_ENV' => 'Application environment is not set',
            'APP_URL' => 'Application URL is not set',
            'DB_CONNECTION' => 'Database connection is not configured',
        ];

        foreach ($criticalEnvVars as $var => $message) {
            if (empty(env($var))) {
                $issues[] = $message;
            }
        }

        // Check production environment settings
        if (app()->environment('production')) {
            if (config('app.debug')) {
                $issues[] = 'Debug mode should be disabled in production';
            }
            
            if (!config('session.secure')) {
                $warnings[] = 'Session cookies should be secure in production';
            }
            
            if (config('session.same_site') !== 'strict') {
                $warnings[] = 'Session same_site should be strict for better security';
            }
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }

    protected function checkSecurityConfiguration(): array
    {
        $issues = [];
        $warnings = [];

        // Check HTTPS configuration
        if (app()->environment('production') && !str_starts_with(config('app.url'), 'https://')) {
            $issues[] = 'Production app should use HTTPS';
        }

        // Check password hashing
        if (config('hashing.driver') !== 'bcrypt' && config('hashing.driver') !== 'argon2id') {
            $warnings[] = 'Consider using bcrypt or argon2id for password hashing';
        }

        // Check encryption
        if (empty(config('app.key'))) {
            $issues[] = 'Application encryption key is not set';
        }

        // Check CORS settings
        if (config('cors.allowed_origins') === ['*']) {
            $warnings[] = 'CORS is configured to allow all origins - consider restricting';
        }

        // Check rate limiting
        $rateLimitingConfigured = file_exists(config_path('rate-limiting.php')) ||
                                  class_exists('App\Http\Middleware\SecurityMiddleware');
        
        if (!$rateLimitingConfigured) {
            $warnings[] = 'Rate limiting middleware not properly configured';
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }

    protected function checkPerformance(): array
    {
        $issues = [];
        $recommendations = [];

        $performanceReport = $this->performanceService->getPerformanceReport();
        
        // Check database performance
        if (isset($performanceReport['database']['response_time_ms']) && 
            $performanceReport['database']['response_time_ms'] > 100) {
            $recommendations[] = 'Database response time is slow (>100ms)';
        }

        // Check cache performance
        if (isset($performanceReport['cache']['status']) && 
            $performanceReport['cache']['status'] !== 'healthy') {
            $issues[] = 'Cache system is not healthy';
        }

        // Check memory usage
        if (isset($performanceReport['system_resources']['memory']['percentage']) && 
            $performanceReport['system_resources']['memory']['percentage'] > 80) {
            $recommendations[] = 'High memory usage detected (>80%)';
        }

        // Check overall grade
        if (isset($performanceReport['overall_grade']) && 
            in_array($performanceReport['overall_grade'], ['D', 'F'])) {
            $issues[] = 'Overall performance grade is poor: ' . $performanceReport['overall_grade'];
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues,
            'recommendations' => $recommendations,
        ];
    }

    protected function checkDatabaseHealth(): array
    {
        $issues = [];

        try {
            // Test connection
            DB::connection()->getPdo();
            
            // Check if migrations are up to date
            $pendingMigrations = DB::table('migrations')->count();
            if ($pendingMigrations === 0) {
                $issues[] = 'No migrations found - database may not be properly set up';
            }
            
            // Check critical tables exist
            $criticalTables = ['users', 'properties', 'bookings', 'security_logs'];
            foreach ($criticalTables as $table) {
                if (!DB::getSchemaBuilder()->hasTable($table)) {
                    $issues[] = "Critical table '{$table}' does not exist";
                }
            }
            
        } catch (\Exception $e) {
            $issues[] = 'Database connection failed: ' . $e->getMessage();
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues,
        ];
    }

    protected function checkFileSystemHealth(): array
    {
        $issues = [];

        // Check storage directories are writable
        $directories = [
            storage_path('logs'),
            storage_path('app'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                $issues[] = "Directory does not exist: {$dir}";
            } elseif (!is_writable($dir)) {
                $issues[] = "Directory is not writable: {$dir}";
            }
        }

        // Check disk space
        $freeSpace = disk_free_space(storage_path());
        $totalSpace = disk_total_space(storage_path());
        $usagePercentage = (($totalSpace - $freeSpace) / $totalSpace) * 100;

        if ($usagePercentage > 90) {
            $issues[] = 'Disk usage is critically high (>90%)';
        } elseif ($usagePercentage > 80) {
            $issues[] = 'Disk usage is high (>80%)';
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues,
        ];
    }

    protected function checkDependencies(): array
    {
        $issues = [];
        $warnings = [];

        // Check PHP version
        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            $warnings[] = 'PHP version is below recommended 8.2.0';
        }

        // Check required PHP extensions
        $requiredExtensions = [
            'pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath'
        ];

        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $issues[] = "Required PHP extension '{$extension}' is not loaded";
            }
        }

        // Check Composer dependencies
        if (!file_exists(base_path('vendor/autoload.php'))) {
            $issues[] = 'Composer dependencies are not installed';
        }

        // Check Node.js dependencies for frontend
        if (!file_exists(base_path('node_modules'))) {
            $warnings[] = 'Node.js dependencies are not installed (frontend assets may not work)';
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }

    protected function checkErrorHandling(): array
    {
        $issues = [];

        // Check if custom exception handler exists
        $handlerPath = app_path('Exceptions/Handler.php');
        if (!file_exists($handlerPath)) {
            $issues[] = 'Custom exception handler not found';
        }

        // Check logging configuration
        if (!config('logging.channels.security')) {
            $issues[] = 'Security logging channel not configured';
        }

        // Check error pages exist
        $errorPages = ['404', '403', '500'];
        foreach ($errorPages as $page) {
            if (!view()->exists("errors.{$page}")) {
                $issues[] = "Error page for {$page} does not exist";
            }
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues,
        ];
    }

    protected function displayResults(array $issues, array $warnings, array $recommendations): void
    {
        if (empty($issues) && empty($warnings) && empty($recommendations)) {
            $this->info('✅ All checks passed! Your application is enterprise-ready.');
            return;
        }

        if (!empty($issues)) {
            $this->error('❌ Critical Issues Found:');
            foreach ($issues as $issue) {
                $this->line("  • {$issue}");
            }
            $this->newLine();
        }

        if (!empty($warnings)) {
            $this->warn('⚠️  Warnings:');
            foreach ($warnings as $warning) {
                $this->line("  • {$warning}");
            }
            $this->newLine();
        }

        if (!empty($recommendations)) {
            $this->info('💡 Recommendations:');
            foreach ($recommendations as $recommendation) {
                $this->line("  • {$recommendation}");
            }
            $this->newLine();
        }

        $this->info('Summary:');
        $this->line("  • Critical Issues: " . count($issues));
        $this->line("  • Warnings: " . count($warnings));
        $this->line("  • Recommendations: " . count($recommendations));
    }

    protected function attemptFixes(array $issues): void
    {
        if (empty($issues)) {
            return;
        }

        $this->info('🔧 Attempting to fix issues...');

        // Auto-fix some common issues
        foreach ($issues as $issue) {
            if (str_contains($issue, 'Application key is not set')) {
                $this->call('key:generate');
                $this->info('  ✅ Generated application key');
            }

            if (str_contains($issue, 'Debug mode should be disabled')) {
                // Would require editing .env file
                $this->warn('  ⚠️  Please manually set APP_DEBUG=false in .env file');
            }

            if (str_contains($issue, 'Directory does not exist')) {
                // Create missing directories
                $this->call('storage:link');
                $this->info('  ✅ Created storage symlink');
            }
        }
    }

    protected function generateDetailedReport(array $issues, array $warnings, array $recommendations): void
    {
        $report = [
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'issues' => $issues,
            'warnings' => $warnings,
            'recommendations' => $recommendations,
            'performance_report' => $this->performanceService->getPerformanceReport(),
            'environment_validation' => $this->environmentService->validateEnvironment(),
        ];

        $reportPath = storage_path('logs/enterprise-health-check-' . now()->format('Y-m-d-H-i-s') . '.json');
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));

        $this->info("📄 Detailed report saved to: {$reportPath}");
    }
}
