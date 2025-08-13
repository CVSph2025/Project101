<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'security' => \App\Http\Middleware\SecurityMiddleware::class,
            'request.id' => \App\Http\Middleware\RequestIdMiddleware::class,
            'input.validation' => \App\Http\Middleware\EnhancedInputValidationMiddleware::class,
            'error.monitoring' => \App\Http\Middleware\ErrorMonitoringMiddleware::class,
        ]);
        
        // Add global middleware stack in proper order
        $middleware->append(\App\Http\Middleware\RequestIdMiddleware::class);
        $middleware->append(\App\Http\Middleware\ErrorMonitoringMiddleware::class);
        $middleware->append(\App\Http\Middleware\EnhancedInputValidationMiddleware::class);
        $middleware->append(\App\Http\Middleware\SecurityMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
