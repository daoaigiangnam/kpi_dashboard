<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PcAuditMailConfigController;
use Illuminate\Support\Facades\Route;

Route::get('/pc-audit/mail-config', [PcAuditMailConfigController::class, 'show']);
