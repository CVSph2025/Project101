<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DeepProjectAnalysis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:deep-project-analysis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform deep analysis of the entire project';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 DEEP PROJECT ANALYSIS STARTING...');
        $this->info('=====================================');
        
        $issues = [];
        $recommendations = [];
        
        // 1. Check Controllers
        $this->info("\n📁 ANALYZING CONTROLLERS...");
        $controllerIssues = $this->analyzeControllers();
        $issues = array_merge($issues, $controllerIssues);
        
        // 2. Check Models
        $this->info("\n📊 ANALYZING MODELS...");
        $modelIssues = $this->analyzeModels();
        $issues = array_merge($issues, $modelIssues);
        
        // 3. Check Views
        $this->info("\n🎨 ANALYZING VIEWS...");
        $viewIssues = $this->analyzeViews();
        $issues = array_merge($issues, $viewIssues);
        
        // 4. Check Routes
        $this->info("\n🛣️ ANALYZING ROUTES...");
        $routeIssues = $this->analyzeRoutes();
        $issues = array_merge($issues, $routeIssues);
        
        // 5. Check Database
        $this->info("\n🗄️ ANALYZING DATABASE...");
        $dbIssues = $this->analyzeDatabase();
        $issues = array_merge($issues, $dbIssues);
        
        // 6. Check Middleware
        $this->info("\n🛡️ ANALYZING MIDDLEWARE...");
        $middlewareIssues = $this->analyzeMiddleware();
        $issues = array_merge($issues, $middlewareIssues);
        
        // 7. Check Configuration
        $this->info("\n⚙️ ANALYZING CONFIGURATION...");
        $configIssues = $this->analyzeConfiguration();
        $issues = array_merge($issues, $configIssues);
        
        // 8. Generate recommendations
        $this->info("\n💡 GENERATING RECOMMENDATIONS...");
        $recommendations = $this->generateRecommendations();
        
        // Display results
        $this->displayResults($issues, $recommendations);
        
        return 0;
    }
    
    private function analyzeControllers(): array
    {
        $issues = [];
        $controllerPath = app_path('Http/Controllers');
        
        if (!File::exists($controllerPath)) {
            $issues[] = "❌ Controllers directory missing: {$controllerPath}";
            return $issues;
        }
        
        $controllers = File::allFiles($controllerPath);
        $this->info("Found " . count($controllers) . " controller files");
        
        foreach ($controllers as $controller) {
            $content = File::get($controller->getRealPath());
            $className = str_replace([app_path(), '/', '.php'], ['', '\\', ''], $controller->getRealPath());
            
            // Check for missing methods
            if (strpos($content, 'index') === false && strpos($content, 'ResourceController') === false) {
                $issues[] = "⚠️ Controller {$controller->getFilename()} might be missing index method";
            }
            
            // Check for proper namespace
            if (!preg_match('/namespace App\\\\Http\\\\Controllers/', $content)) {
                $issues[] = "❌ Controller {$controller->getFilename()} has incorrect namespace";
            }
        }
        
        return $issues;
    }
    
    private function analyzeModels(): array
    {
        $issues = [];
        $modelPath = app_path('Models');
        
        if (!File::exists($modelPath)) {
            $issues[] = "❌ Models directory missing: {$modelPath}";
            return $issues;
        }
        
        $models = File::files($modelPath);
        $this->info("Found " . count($models) . " model files");
        
        foreach ($models as $model) {
            $content = File::get($model->getRealPath());
            $modelName = str_replace('.php', '', $model->getFilename());
            
            // Check if table exists for model
            if ($modelName !== 'User') {
                $tableName = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($modelName));
                if (!Schema::hasTable($tableName)) {
                    $issues[] = "❌ Table '{$tableName}' missing for model {$modelName}";
                }
            }
            
            // Check for fillable property
            if (!preg_match('/protected \$fillable/', $content)) {
                $issues[] = "⚠️ Model {$modelName} missing \$fillable property";
            }
        }
        
        return $issues;
    }
    
    private function analyzeViews(): array
    {
        $issues = [];
        $viewPath = resource_path('views');
        
        if (!File::exists($viewPath)) {
            $issues[] = "❌ Views directory missing: {$viewPath}";
            return $issues;
        }
        
        $views = File::allFiles($viewPath);
        $this->info("Found " . count($views) . " view files");
        
        // Check for required views
        $requiredViews = [
            'homepage.blade.php',
            'dashboard.blade.php',
            'auth/login.blade.php',
            'auth/register.blade.php',
            'layouts/app.blade.php',
            'layouts/guest.blade.php'
        ];
        
        foreach ($requiredViews as $requiredView) {
            $viewFile = $viewPath . '/' . $requiredView;
            if (!File::exists($viewFile)) {
                $issues[] = "❌ Required view missing: {$requiredView}";
            }
        }
        
        return $issues;
    }
    
    private function analyzeRoutes(): array
    {
        $issues = [];
        
        try {
            $routes = Route::getRoutes();
            $this->info("Found " . count($routes) . " routes");
            
            // Check for duplicate routes
            $routeUris = [];
            foreach ($routes as $route) {
                $uri = $route->uri();
                if (isset($routeUris[$uri])) {
                    $issues[] = "⚠️ Duplicate route found: {$uri}";
                }
                $routeUris[$uri] = true;
            }
            
        } catch (\Exception $e) {
            $issues[] = "❌ Route analysis failed: " . $e->getMessage();
        }
        
        return $issues;
    }
    
    private function analyzeDatabase(): array
    {
        $issues = [];
        
        try {
            // Test database connection
            DB::connection()->getPdo();
            $this->info("✅ Database connection successful");
            
            // Check required tables
            $requiredTables = ['users', 'properties', 'bookings', 'roles', 'permissions'];
            
            foreach ($requiredTables as $table) {
                if (!Schema::hasTable($table)) {
                    $issues[] = "❌ Required table missing: {$table}";
                } else {
                    $count = DB::table($table)->count();
                    $this->info("Table {$table}: {$count} records");
                }
            }
            
        } catch (\Exception $e) {
            $issues[] = "❌ Database connection failed: " . $e->getMessage();
        }
        
        return $issues;
    }
    
    private function analyzeMiddleware(): array
    {
        $issues = [];
        $middlewarePath = app_path('Http/Middleware');
        
        if (!File::exists($middlewarePath)) {
            $issues[] = "❌ Middleware directory missing: {$middlewarePath}";
            return $issues;
        }
        
        $middlewares = File::files($middlewarePath);
        $this->info("Found " . count($middlewares) . " middleware files");
        
        // Check for required middleware
        $requiredMiddleware = [
            'SecurityMiddleware.php',
            'EnhancedInputValidationMiddleware.php'
        ];
        
        foreach ($requiredMiddleware as $middleware) {
            if (!File::exists($middlewarePath . '/' . $middleware)) {
                $issues[] = "❌ Required middleware missing: {$middleware}";
            }
        }
        
        return $issues;
    }
    
    private function analyzeConfiguration(): array
    {
        $issues = [];
        
        // Check environment variables
        $requiredEnvVars = [
            'APP_NAME', 'APP_ENV', 'APP_KEY', 'APP_DEBUG', 'APP_URL',
            'DB_CONNECTION', 'DB_DATABASE'
        ];
        
        foreach ($requiredEnvVars as $envVar) {
            if (!env($envVar)) {
                $issues[] = "⚠️ Environment variable missing or empty: {$envVar}";
            }
        }
        
        // Check config files
        $configPath = config_path();
        $requiredConfigs = ['app.php', 'database.php', 'auth.php'];
        
        foreach ($requiredConfigs as $config) {
            if (!File::exists($configPath . '/' . $config)) {
                $issues[] = "❌ Required config file missing: {$config}";
            }
        }
        
        return $issues;
    }
    
    private function generateRecommendations(): array
    {
        $recommendations = [
            "🚀 PERFORMANCE ENHANCEMENTS:",
            "• Implement Redis caching for frequently accessed data",
            "• Add database query optimization and indexing",
            "• Implement API rate limiting per user/IP",
            "• Add image optimization and CDN integration",
            "• Implement lazy loading for property images",
            "",
            "🔒 SECURITY ENHANCEMENTS:",
            "• Add CSRF protection to all forms",
            "• Implement two-factor authentication",
            "• Add API authentication with Laravel Sanctum",
            "• Implement content security policy headers",
            "• Add audit logging for admin actions",
            "",
            "📱 USER EXPERIENCE ENHANCEMENTS:",
            "• Add progressive web app (PWA) features",
            "• Implement real-time notifications",
            "• Add search filters and sorting options",
            "• Implement wishlist/favorites functionality",
            "• Add property comparison feature",
            "",
            "🏗️ CODE QUALITY ENHANCEMENTS:",
            "• Add comprehensive unit and feature tests",
            "• Implement API documentation with Swagger",
            "• Add code coverage reporting",
            "• Implement automated code quality checks",
            "• Add database seeders for testing data",
            "",
            "📊 MONITORING & ANALYTICS:",
            "• Add application performance monitoring",
            "• Implement error tracking with Sentry",
            "• Add user analytics and behavior tracking",
            "• Implement business intelligence dashboard",
            "• Add automated backup system"
        ];
        
        return $recommendations;
    }
    
    private function displayResults(array $issues, array $recommendations): void
    {
        $this->info("\n" . str_repeat("=", 50));
        $this->info("📋 ANALYSIS RESULTS");
        $this->info(str_repeat("=", 50));
        
        if (empty($issues)) {
            $this->info("✅ No critical issues found!");
        } else {
            $this->error("\n🚨 ISSUES FOUND:");
            foreach ($issues as $issue) {
                $this->line("  " . $issue);
            }
        }
        
        $this->info("\n💡 ENHANCEMENT RECOMMENDATIONS:");
        foreach ($recommendations as $recommendation) {
            $this->line("  " . $recommendation);
        }
        
        $this->info("\n" . str_repeat("=", 50));
        $this->info("Analysis completed successfully!");
        $this->info(str_repeat("=", 50));
    }
}
