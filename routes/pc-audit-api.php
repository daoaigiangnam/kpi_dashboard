<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PcAuditApiController;
use Illuminate\Support\Facades\Route;

Route::post('/pc-audit/validate-code', [PcAuditApiController::class, 'validateCode']);
Route::post('/pc-audit/submit', [PcAuditApiController::class, 'submit']);
