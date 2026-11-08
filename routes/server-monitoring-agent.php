<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ServerAgentController;
Route::prefix('agent')->middleware(['agent.token','throttle:120,1'])->group(function():void {
 Route::get('/config',[ServerAgentController::class,'config']);
 Route::get('/commands',[ServerAgentController::class,'commands']);
 Route::post('/heartbeat',[ServerAgentController::class,'heartbeat']);
 Route::post('/ack',[ServerAgentController::class,'acknowledge']);
 Route::post('/audit/events',[ServerAgentController::class,'auditEvents']);
 Route::post('/audit/report',[ServerAgentController::class,'report']);
});