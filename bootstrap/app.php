<?php

use App\Http\Controllers\Admin\PcAuditDeleteController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware(['auth', 'permission:pc_audit.delete'])
                ->prefix('admin')
                ->name('admin.')
                ->group(function (): void {
                    Route::post('pc-audit/{pcAudit}/delete', [PcAuditDeleteController::class, 'destroy'])
                        ->whereNumber('pcAudit')
                        ->name('pc_audit.delete');
                });
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['permission' => App\Http\Middleware\CheckPermission::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {})
    ->create();
