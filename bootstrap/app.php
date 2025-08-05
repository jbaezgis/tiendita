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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role.redirect' => \App\Http\Middleware\RedirectBasedOnRole::class,
            'ensure.employee' => \App\Http\Middleware\EnsureEmployeeRole::class,
            'ensure.admin.supervisor' => \App\Http\Middleware\EnsureAdminOrSupervisor::class,
            'ensure.admin.access' => \App\Http\Middleware\EnsureAdminAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
