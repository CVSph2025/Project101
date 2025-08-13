<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AIRecommendationController;
use App\Http\Controllers\EnterpriseMonitoringController;
use App\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// Health check endpoints - Enhanced version
Route::get('/health', [HealthCheckController::class, 'simple'])->name('health-check');
Route::get('/health/detailed', [HealthCheckController::class, 'index'])->name('health-detailed');
Route::get('/health/database', [HealthCheckController::class, 'database'])->name('health-database');
Route::get('/health/environment', [HealthCheckController::class, 'environment'])->name('health-environment');

// Legacy health check endpoint for backwards compatibility
Route::get('/health-legacy', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'environment' => app()->environment(),
        'commit' => '581c500+',
        'homepage_route' => 'active',
    ]);
})->name('health-check-legacy');

// Debug routes for troubleshooting deployment
Route::get('/debug/db', function () {
    try {
        DB::connection()->getPdo();
        $userCount = DB::table('users')->count();
        return response()->json([
            'status' => 'success',
            'message' => 'Database connected successfully!',
            'user_count' => $userCount,
            'connection' => env('DB_CONNECTION'),
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Database connection failed: ' . $e->getMessage(),
            'connection' => env('DB_CONNECTION'),
        ]);
    }
});

Route::get('/debug/env', function () {
    return response()->json([
        'APP_ENV' => env('APP_ENV'),
        'APP_DEBUG' => env('APP_DEBUG'),
        'APP_URL' => env('APP_URL'),
        'DB_CONNECTION' => env('DB_CONNECTION'),
        'CACHE_DRIVER' => env('CACHE_DRIVER'),
        'SESSION_DRIVER' => env('SESSION_DRIVER'),
        'PHP_VERSION' => phpversion(),
        'LARAVEL_VERSION' => app()->version(),
        'MEMORY_LIMIT' => ini_get('memory_limit'),
        'MAX_EXECUTION_TIME' => ini_get('max_execution_time'),
    ]);
});

Route::get('/debug/deployment', function () {
    $views = [];
    $viewPath = resource_path('views');
    if (file_exists($viewPath . '/homepage.blade.php')) {
        $views['homepage'] = 'exists';
    }
    if (file_exists($viewPath . '/welcome-simple.blade.php')) {
        $views['welcome-simple'] = 'exists';
    }
    
    return response()->json([
        'deployment_time' => now()->toISOString(),
        'commit_expected' => '15ba1ff',
        'views_available' => $views,
        'current_route' => 'homepage',
        'route_cache_cleared' => 'needed',
    ]);
});

Route::get('/debug/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now(),
        'app_name' => config('app.name'),
        'environment' => app()->environment(),
    ]);
});

Route::get('/debug/clear-cache', function () {
    try {
        // Clear various caches
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('config:clear');
        
        return response()->json([
            'status' => 'success',
            'message' => 'All caches cleared successfully',
            'cleared' => ['routes', 'views', 'config'],
            'timestamp' => now()->toISOString(),
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Cache clear failed: ' . $e->getMessage(),
        ], 500);
    }
});

Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy-policy');

Route::get('/terms-of-service', function () {
    return view('terms-of-service');
})->name('terms-of-service');

// Social Authentication Routes
Route::get('/auth/{provider}', [App\Http\Controllers\Auth\SocialAuthController::class, 'redirectToProvider'])
    ->name('auth.social.redirect')
    ->where('provider', 'facebook|google');

Route::get('/auth/{provider}/callback', [App\Http\Controllers\Auth\SocialAuthController::class, 'handleProviderCallback'])
    ->name('auth.social.callback')
    ->where('provider', 'facebook|google');

Route::get('/dashboard', function () {
    $user = Auth::user();
    
    // Check if user has any roles assigned, if not redirect to profile to set up
    /** @var \App\Models\User $user */
    if (!$user->roles()->exists()) {
        return redirect()->route('profile.edit')->with('warning', 'Please complete your profile setup.');
    }
    
    if ($user->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    } elseif ($user->hasRole('landlord')) {
        return redirect()->route('owner.dashboard');
    } elseif ($user->hasRole('renter')) {
        return redirect()->route('renter.dashboard');
    }
    
    // Fallback to default dashboard
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Property routes
    Route::resource('properties', PropertyController::class);
    Route::patch('/properties/{property}/toggle-status', [PropertyController::class, 'toggleStatus'])->name('properties.toggle-status');
    Route::post('/properties/{property}/upload-images', [PropertyController::class, 'uploadImages'])->name('properties.upload-images');
    Route::delete('/properties/{property}/images/{image}', [PropertyController::class, 'deleteImage'])->name('properties.delete-image');
    Route::patch('/properties/{property}/images/{image}/set-primary', [PropertyController::class, 'setPrimaryImage'])->name('properties.set-primary-image');
    Route::get('/my-properties', [PropertyController::class, 'ownerProperties'])->name('properties.owner');
    
    // Booking routes
    Route::get('/properties/{property}/book', [BookingController::class, 'create'])->name('bookings.create');
    Route::resource('bookings', BookingController::class)->except(['create']);
    Route::patch('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    
    // Transaction routes
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    
    // AI Recommendations
    Route::prefix('ai-recommendations')->group(function () {
        Route::get('personalized', [AIRecommendationController::class, 'getPersonalizedRecommendations']);
        Route::get('similar/{property}', [AIRecommendationController::class, 'getSimilarProperties']);
        Route::get('trending', [AIRecommendationController::class, 'getTrendingProperties']);
        Route::get('saved-searches', [AIRecommendationController::class, 'getRecommendationsFromSavedSearches']);
        Route::get('price-prediction/{property}', [AIRecommendationController::class, 'getPricePrediction']);
        Route::get('pricing-calendar/{property}', [AIRecommendationController::class, 'getOptimalPricingCalendar']);
        Route::post('preferences', [AIRecommendationController::class, 'updatePreferences']);
        Route::get('insights', [AIRecommendationController::class, 'getRecommendationInsights']);
        Route::post('feedback', [AIRecommendationController::class, 'provideFeedback']);
        Route::get('search-suggestions', [AIRecommendationController::class, 'getSearchSuggestions']);
    });
    
    // Enterprise Monitoring (Admin only)
    Route::middleware(['role:admin'])->prefix('enterprise/monitoring')->group(function () {
        Route::get('dashboard', [EnterpriseMonitoringController::class, 'dashboard']);
        Route::get('metrics', [EnterpriseMonitoringController::class, 'getSystemMetrics']);
        Route::get('security', [EnterpriseMonitoringController::class, 'getSecurityMonitoring']);
        Route::get('performance', [EnterpriseMonitoringController::class, 'getPerformanceAnalytics']);
        Route::get('business', [EnterpriseMonitoringController::class, 'getBusinessIntelligence']);
        Route::post('report', [EnterpriseMonitoringController::class, 'generateSystemReport']);
        Route::get('alerts', [EnterpriseMonitoringController::class, 'getAlertConfigurations']);
        Route::post('alerts', [EnterpriseMonitoringController::class, 'updateAlertConfigurations']);
    });
});

// Admin routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');
    Route::get('/users', [UserRoleController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/role', [UserRoleController::class, 'updateRole'])->name('users.update-role');
    Route::delete('/users/{user}/role/{role}', [UserRoleController::class, 'removeRole'])->name('users.remove-role');
    
    // Admin transaction management
    Route::get('/transactions', [TransactionController::class, 'adminIndex'])->name('transactions.index');
    Route::patch('/transactions/{transaction}/approve', [TransactionController::class, 'approve'])->name('transactions.approve');
    Route::patch('/transactions/{transaction}/reject', [TransactionController::class, 'reject'])->name('transactions.reject');
});

// Owner/Landlord routes
Route::middleware(['auth', 'role:admin|landlord'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('/dashboard', function () {
        $user = Auth::user();
        
        // Get landlord's properties and statistics
        /** @var \App\Models\User $user */
        $properties = $user->properties()->get();
        $bookings = \App\Models\Booking::whereIn('property_id', $properties->pluck('id'))->get();
        
        $stats = [
            'activeListings' => $properties->count(),
            'monthlyIncome' => $bookings->where('created_at', '>=', now()->startOfMonth())->where('status', 'confirmed')->sum('total_price') ?: 0,
            'bookedProperties' => $bookings->where('status', 'confirmed')->count(),
            'pendingRequests' => $bookings->where('status', 'pending')->count(),
        ];
        
        // Format monthly income
        if ($stats['monthlyIncome'] > 0) {
            $stats['monthlyIncome'] = '₱' . number_format($stats['monthlyIncome']);
        } else {
            $stats['monthlyIncome'] = '₱0';
        }
        
        // Get recent properties for dashboard display
        $recentProperties = $properties->take(3);
        
        return view('owner.dashboard', compact('stats', 'recentProperties'));
    })->name('dashboard');
    Route::get('/properties', [PropertyController::class, 'ownerProperties'])->name('properties');
});

// Host Dashboard routes (Advanced Analytics)
Route::middleware(['auth', 'role:admin|landlord'])->prefix('host')->name('host.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\HostDashboardController::class, 'index'])->name('dashboard');
    Route::get('/analytics', [App\Http\Controllers\HostDashboardController::class, 'getAnalytics'])->name('analytics');
    Route::get('/export', [App\Http\Controllers\HostDashboardController::class, 'exportAnalytics'])->name('dashboard.export');
});

// Renter routes
Route::middleware(['auth', 'role:renter'])->prefix('renter')->name('renter.')->group(function () {
    Route::get('/dashboard', function () {
        $featuredProperties = \App\Models\Property::latest()
            ->take(3)
            ->get();
        
        return view('renter.dashboard', compact('featuredProperties'));
    })->name('dashboard');
    Route::get('/bookings', [BookingController::class, 'userBookings'])->name('bookings');
});

// Payment routes
Route::middleware(['auth'])->group(function () {
    Route::post('/bookings/{booking}/payment-intent', [App\Http\Controllers\PaymentController::class, 'createPaymentIntent'])->name('payment.create-intent');
    Route::post('/payments/{payment}/confirm', [App\Http\Controllers\PaymentController::class, 'confirmPayment'])->name('payment.confirm');
    Route::get('/payments/{payment}', [App\Http\Controllers\PaymentController::class, 'show'])->name('payment.show');
    Route::post('/payments/{payment}/refund', [App\Http\Controllers\PaymentController::class, 'refund'])->name('payment.refund');
});

// Stripe webhook (no auth middleware)
Route::post('/stripe/webhook', [App\Http\Controllers\PaymentController::class, 'webhook'])->name('stripe.webhook');

// Review routes
Route::middleware(['auth'])->group(function () {
    Route::get('/properties/{property}/reviews', [App\Http\Controllers\ReviewController::class, 'index'])->name('reviews.index');
    Route::get('/bookings/{booking}/review/create', [App\Http\Controllers\ReviewController::class, 'create'])->name('reviews.create');
    Route::post('/bookings/{booking}/review', [App\Http\Controllers\ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}', [App\Http\Controllers\ReviewController::class, 'show'])->name('reviews.show');
    Route::get('/reviews/{review}/edit', [App\Http\Controllers\ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [App\Http\Controllers\ReviewController::class, 'update'])->name('reviews.update');
    Route::post('/reviews/{review}/response', [App\Http\Controllers\ReviewController::class, 'addResponse'])->name('reviews.add-response');
    Route::post('/reviews/{review}/toggle-featured', [App\Http\Controllers\ReviewController::class, 'toggleFeatured'])->name('reviews.toggle-featured');
    Route::get('/properties/{property}/reviews/stats', [App\Http\Controllers\ReviewController::class, 'stats'])->name('reviews.stats');
    Route::delete('/reviews/{review}', [App\Http\Controllers\ReviewController::class, 'destroy'])->name('reviews.destroy');
});

// Saved Search routes
Route::middleware(['auth'])->group(function () {
    Route::resource('saved-searches', App\Http\Controllers\SavedSearchController::class);
    Route::post('/saved-searches/{savedSearch}/toggle-active', [App\Http\Controllers\SavedSearchController::class, 'toggleActive'])->name('saved-searches.toggle-active');
    Route::get('/saved-searches/{savedSearch}/results', [App\Http\Controllers\SavedSearchController::class, 'results'])->name('saved-searches.results');
    Route::post('/saved-searches/{savedSearch}/test-alert', [App\Http\Controllers\SavedSearchController::class, 'sendTestAlert'])->name('saved-searches.test-alert');
    Route::post('/saved-searches/bulk-action', [App\Http\Controllers\SavedSearchController::class, 'bulkAction'])->name('saved-searches.bulk-action');
    Route::post('/save-search_from_url', [App\Http\Controllers\SavedSearchController::class, 'saveFromSearch'])->name('saved-searches.save-from-url');
});

// AI Recommendation Testing Routes (Development/Testing Only)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/recommendation-tests', function () {
        return view('admin.recommendation-tests');
    })->name('admin.recommendation-tests');
    Route::get('/test-recommendations', [App\Http\Controllers\RecommendationTestController::class, 'runTests'])->name('admin.test-recommendations');
});

require __DIR__.'/auth.php';
