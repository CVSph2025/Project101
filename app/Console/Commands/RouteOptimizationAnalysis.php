<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

class RouteOptimizationAnalysis extends Command
{
    protected $signature = 'route:analyze {--fix : Automatically fix route duplicates}';
    protected $description = 'Analyze route structure and identify optimization opportunities';

    public function handle()
    {
        $this->info('🔍 Starting Route Optimization Analysis...');
        $this->newLine();

        $analysis = $this->analyzeRoutes();
        
        $this->displayAnalysis($analysis);
        
        if ($this->option('fix')) {
            $this->fixRouteIssues($analysis);
        }

        return 0;
    }

    private function analyzeRoutes(): array
    {
        $routes = collect(Route::getRoutes())->map(function ($route) {
            return [
                'method' => implode('|', $route->methods()),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'middleware' => $route->gatherMiddleware(),
            ];
        });

        $analysis = [
            'total_routes' => $routes->count(),
            'duplicates' => $this->findDuplicateRoutes($routes),
            'ungrouped_routes' => $this->findUngroupedRoutes($routes),
            'missing_names' => $this->findRoutesWithoutNames($routes),
            'performance_issues' => $this->findPerformanceIssues($routes),
            'cdo_specific_routes' => $this->findCdoSpecificRoutes($routes),
            'recommendations' => []
        ];

        $analysis['recommendations'] = $this->generateRecommendations($analysis);

        return $analysis;
    }

    private function findDuplicateRoutes($routes): array
    {
        $duplicates = [];
        $seen = [];

        foreach ($routes as $route) {
            $key = $route['method'] . ':' . $route['uri'];
            
            if (isset($seen[$key])) {
                $duplicates[] = [
                    'route' => $route,
                    'conflicts_with' => $seen[$key]
                ];
            } else {
                $seen[$key] = $route;
            }
        }

        return $duplicates;
    }

    private function findUngroupedRoutes($routes): array
    {
        // Routes that should be grouped but aren't
        $ungrouped = [];
        
        $adminRoutes = $routes->filter(fn($r) => str_starts_with($r['uri'], 'admin/'));
        $apiRoutes = $routes->filter(fn($r) => str_starts_with($r['uri'], 'api/'));
        $authRoutes = $routes->filter(fn($r) => str_contains($r['uri'], 'auth') || str_contains($r['uri'], 'login') || str_contains($r['uri'], 'register'));

        if ($adminRoutes->count() > 0) {
            $ungrouped['admin'] = $adminRoutes->toArray();
        }
        
        if ($apiRoutes->count() > 0) {
            $ungrouped['api'] = $apiRoutes->toArray();
        }
        
        if ($authRoutes->count() > 0) {
            $ungrouped['auth'] = $authRoutes->toArray();
        }

        return $ungrouped;
    }

    private function findRoutesWithoutNames($routes): array
    {
        return $routes->filter(fn($r) => empty($r['name']))->toArray();
    }

    private function findPerformanceIssues($routes): array
    {
        $issues = [];
        
        // Routes with too many middleware
        $heavyMiddleware = $routes->filter(fn($r) => count($r['middleware']) > 5);
        if ($heavyMiddleware->count() > 0) {
            $issues['heavy_middleware'] = $heavyMiddleware->toArray();
        }
        
        // Routes that might benefit from caching
        $cacheable = $routes->filter(fn($r) => 
            str_contains($r['method'], 'GET') && 
            !str_contains($r['uri'], '{') &&
            !in_array('cache.headers', $r['middleware'])
        );
        if ($cacheable->count() > 0) {
            $issues['cacheable_routes'] = $cacheable->toArray();
        }

        return $issues;
    }

    private function findCdoSpecificRoutes($routes): array
    {
        return $routes->filter(fn($r) => 
            str_contains(strtolower($r['uri']), 'cdo') ||
            str_contains(strtolower($r['uri']), 'cagayan') ||
            str_contains(strtolower($r['action']), 'cdo') ||
            str_contains(strtolower($r['action']), 'property')
        )->toArray();
    }

    private function generateRecommendations(array $analysis): array
    {
        $recommendations = [];

        if (count($analysis['duplicates']) > 0) {
            $recommendations[] = [
                'type' => 'critical',
                'title' => 'Route Duplicates Found',
                'description' => 'Found ' . count($analysis['duplicates']) . ' duplicate routes that need resolution',
                'action' => 'Review and consolidate duplicate routes'
            ];
        }

        if (count($analysis['missing_names']) > 0) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Missing Route Names',
                'description' => 'Found ' . count($analysis['missing_names']) . ' routes without names',
                'action' => 'Add meaningful names to all routes for better maintenance'
            ];
        }

        if (isset($analysis['ungrouped_routes']['admin'])) {
            $recommendations[] = [
                'type' => 'optimization',
                'title' => 'Admin Routes Not Grouped',
                'description' => 'Admin routes should be grouped with middleware',
                'action' => 'Group admin routes with appropriate middleware and prefixes'
            ];
        }

        if (count($analysis['cdo_specific_routes']) > 0) {
            $recommendations[] = [
                'type' => 'enhancement',
                'title' => 'CDO-Specific Route Optimization',
                'description' => 'Found ' . count($analysis['cdo_specific_routes']) . ' CDO-related routes',
                'action' => 'Consider adding CDO-specific middleware for location validation'
            ];
        }

        return $recommendations;
    }

    private function displayAnalysis(array $analysis): void
    {
        $this->info('📊 Route Analysis Results');
        $this->info('========================');
        $this->newLine();

        $this->line("📋 <fg=cyan>Total Routes:</> {$analysis['total_routes']}");
        $this->line("🔄 <fg=yellow>Duplicates:</> " . count($analysis['duplicates']));
        $this->line("👥 <fg=blue>Ungrouped Routes:</> " . array_sum(array_map('count', $analysis['ungrouped_routes'])));
        $this->line("🏷️  <fg=magenta>Missing Names:</> " . count($analysis['missing_names']));
        $this->line("🏢 <fg=green>CDO-Specific Routes:</> " . count($analysis['cdo_specific_routes']));
        $this->newLine();

        if (!empty($analysis['duplicates'])) {
            $this->error('⚠️  Route Duplicates Found:');
            foreach ($analysis['duplicates'] as $duplicate) {
                $route = $duplicate['route'];
                $this->line("   • {$route['method']} {$route['uri']} -> {$route['action']}");
            }
            $this->newLine();
        }

        $this->info('💡 Recommendations:');
        foreach ($analysis['recommendations'] as $rec) {
            $emoji = match($rec['type']) {
                'critical' => '🚨',
                'warning' => '⚠️',
                'optimization' => '⚡',
                'enhancement' => '✨',
                default => '💡'
            };
            
            $this->line("   {$emoji} <fg=yellow>{$rec['title']}</>");
            $this->line("      {$rec['description']}");
            $this->line("      Action: {$rec['action']}");
            $this->newLine();
        }
    }

    private function fixRouteIssues(array $analysis): void
    {
        $this->info('🔧 Starting Route Optimization...');
        
        if (!empty($analysis['duplicates'])) {
            $this->warn('Cannot automatically fix route duplicates - manual review required');
        }

        // Generate optimized route suggestions
        $this->generateOptimizedRouteFile($analysis);
        
        $this->info('✅ Route optimization analysis complete!');
        $this->line('📁 Check the generated route-optimization-suggestions.php file for recommendations');
    }

    private function generateOptimizedRouteFile(array $analysis): void
    {
        $suggestions = $this->generateRouteSuggestions($analysis);
        
        $filePath = storage_path('app/route-optimization-suggestions.php');
        File::put($filePath, $suggestions);
        
        $this->info("💾 Route optimization suggestions saved to: {$filePath}");
    }

    private function generateRouteSuggestions(array $analysis): string
    {
        return <<<'PHP'
<?php

/*
|--------------------------------------------------------------------------
| Route Optimization Suggestions
|--------------------------------------------------------------------------
| Generated by Route Optimization Analysis
| This file contains suggestions for improving route organization
|
*/

// SUGGESTED ROUTE GROUPING STRUCTURE

// 1. Authentication Routes (Already handled by Laravel Breeze)
require __DIR__.'/auth.php';

// 2. Public Routes
Route::get('/', function () {
    return view('homepage');
})->name('welcome');

Route::get('/privacy-policy', function () {
    return view('legal.privacy');
})->name('privacy-policy');

Route::get('/terms-of-service', function () {
    return view('legal.terms');
})->name('terms-of-service');

// 3. Health Check Routes
Route::prefix('health')->name('health.')->group(function () {
    Route::get('/', [HealthCheckController::class, 'simple'])->name('check');
    Route::get('/detailed', [HealthCheckController::class, 'index'])->name('detailed');
    Route::get('/database', [HealthCheckController::class, 'database'])->name('database');
    Route::get('/environment', [HealthCheckController::class, 'environment'])->name('environment');
});

// 4. Debug Routes (Development Only)
if (app()->environment(['local', 'development'])) {
    Route::prefix('debug')->name('debug.')->group(function () {
        Route::get('/db', function () { /* DB debug */ })->name('db');
        Route::get('/env', function () { /* Env debug */ })->name('env');
        Route::get('/deployment', function () { /* Deployment debug */ })->name('deployment');
        Route::get('/health', function () { /* Health debug */ })->name('health');
        Route::get('/clear-cache', function () { /* Cache clear */ })->name('clear-cache');
    });
}

// 5. Authenticated Routes
Route::middleware(['auth', 'verified', 'error.monitoring'])->group(function () {
    
    // Dashboard Routes
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Profile Management
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
    });

    // CDO Property Management (Location-Specific)
    Route::prefix('properties')->name('properties.')->middleware(['cdo.location.validate'])->group(function () {
        Route::get('/', [PropertyController::class, 'index'])->name('index');
        Route::get('/create', [PropertyController::class, 'create'])->name('create')->middleware('role:landlord');
        Route::post('/', [PropertyController::class, 'store'])->name('store')->middleware('role:landlord');
        Route::get('/{property}', [PropertyController::class, 'show'])->name('show');
        Route::get('/{property}/edit', [PropertyController::class, 'edit'])->name('edit')->middleware('role:landlord');
        Route::patch('/{property}', [PropertyController::class, 'update'])->name('update')->middleware('role:landlord');
        Route::delete('/{property}', [PropertyController::class, 'destroy'])->name('destroy')->middleware('role:landlord');
        
        // Property Management Actions
        Route::patch('/{property}/toggle-status', [PropertyController::class, 'toggleStatus'])->name('toggle-status')->middleware('role:landlord');
        Route::post('/{property}/upload-images', [PropertyController::class, 'uploadImages'])->name('upload-images')->middleware('role:landlord');
        Route::delete('/{property}/images/{image}', [PropertyController::class, 'deleteImage'])->name('delete-image')->middleware('role:landlord');
        Route::patch('/{property}/images/{image}/set-primary', [PropertyController::class, 'setPrimaryImage'])->name('set-primary-image')->middleware('role:landlord');
        
        // Booking Related
        Route::get('/{property}/book', [BookingController::class, 'create'])->name('book')->middleware('role:renter');
    });

    // Landlord-specific routes
    Route::middleware(['role:landlord'])->prefix('owner')->name('owner.')->group(function () {
        Route::get('/dashboard', function () { return view('owner.dashboard'); })->name('dashboard');
        Route::get('/properties', [PropertyController::class, 'ownerProperties'])->name('properties');
    });

    // Renter-specific routes
    Route::middleware(['role:renter'])->prefix('renter')->name('renter.')->group(function () {
        Route::get('/dashboard', function () { return view('renter.dashboard'); })->name('dashboard');
        Route::get('/bookings', [BookingController::class, 'userBookings'])->name('bookings');
    });

    // Booking Management
    Route::resource('bookings', BookingController::class)->except(['create']);
    Route::patch('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

    // AI Recommendations (CDO-Focused)
    Route::prefix('ai-recommendations')->name('ai.')->group(function () {
        Route::get('/personalized', [AIRecommendationController::class, 'getPersonalizedRecommendations'])->name('personalized');
        Route::get('/similar/{property}', [AIRecommendationController::class, 'getSimilarProperties'])->name('similar');
        Route::get('/trending', [AIRecommendationController::class, 'getTrendingProperties'])->name('trending');
        Route::post('/preferences', [AIRecommendationController::class, 'updatePreferences'])->name('preferences');
        Route::get('/cdo-insights', [AIRecommendationController::class, 'getCdoSpecificInsights'])->name('cdo-insights');
    });
});

// 6. Admin Routes
Route::middleware(['auth', 'role:admin', 'error.monitoring'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');
    
    // User Management
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserRoleController::class, 'index'])->name('index');
        Route::patch('/{user}/role', [UserRoleController::class, 'updateRole'])->name('update-role');
        Route::delete('/{user}/role/{role}', [UserRoleController::class, 'removeRole'])->name('remove-role');
    });
    
    // Transaction Management
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [TransactionController::class, 'adminIndex'])->name('index');
        Route::patch('/{transaction}/approve', [TransactionController::class, 'approve'])->name('approve');
        Route::patch('/{transaction}/reject', [TransactionController::class, 'reject'])->name('reject');
    });
    
    // Enterprise Monitoring
    Route::prefix('monitoring')->name('monitoring.')->group(function () {
        Route::get('/dashboard', [EnterpriseMonitoringController::class, 'dashboard'])->name('dashboard');
        Route::get('/performance', [EnterpriseMonitoringController::class, 'getPerformanceAnalytics'])->name('performance');
        Route::get('/cdo-metrics', [EnterpriseMonitoringController::class, 'getCdoSpecificMetrics'])->name('cdo-metrics');
        Route::post('/generate-report', [EnterpriseMonitoringController::class, 'generateSystemReport'])->name('report');
    });
});

/*
|--------------------------------------------------------------------------
| Optimization Notes
|--------------------------------------------------------------------------
| 1. All routes are properly grouped with middleware
| 2. CDO-specific validation added where needed
| 3. Role-based access control implemented
| 4. Error monitoring enabled for all authenticated routes
| 5. Clear naming conventions used throughout
| 6. Debug routes only available in development
| 7. Performance monitoring integrated
|
*/
PHP;
    }
}
