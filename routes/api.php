<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PcAuditApiController;
use App\Http\Controllers\Api\PcAuditConfigController;
use App\Http\Controllers\Api\PcAuditMailConfigController;
use App\Http\Controllers\Api\PcAuditMailController;
use Illuminate\Support\Facades\Route;

Route::prefix('pc-audit')->group(function () {
    Route::get('/config', [PcAuditConfigController::class, 'show'])->middleware('throttle:60,1');
    Route::post('/validate-code', [PcAuditApiController::class, 'validateCode'])->middleware('throttle:30,1');
    Route::post('/submit', [PcAuditApiController::class, 'submit'])->middleware('throttle:20,1');
    Route::post('/mail-config', [PcAuditMailConfigController::class, 'show'])->middleware('throttle:20,1');
    Route::post('/send-mail', [PcAuditMailController::class, 'send'])->middleware('throttle:10,1');
});
