<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PcAuditConfigController;
use Illuminate\Support\Facades\Route;

Route::get('/pc-audit/config', [PcAuditConfigController::class, 'show']);
