<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ServerMonitoringController;
Route::middleware(['auth'])->prefix('admin/server-monitoring')->name('admin.server_monitoring.')->group(function():void {
 Route::get('/',[ServerMonitoringController::class,'index'])->middleware('permission:server_monitoring.view,server_monitoring.manage')->name('index');
 Route::get('/create',[ServerMonitoringController::class,'create'])->middleware('permission:server_monitoring.manage')->name('create');
 Route::post('/',[ServerMonitoringController::class,'store'])->middleware('permission:server_monitoring.manage')->name('store');
 Route::get('/{server}',[ServerMonitoringController::class,'show'])->middleware('permission:server_monitoring.view,server_monitoring.manage')->name('show');
 Route::post('/{server}/audit/start',[ServerMonitoringController::class,'startAudit'])->middleware('permission:server_monitoring.audit')->name('audit.start');
 Route::post('/{server}/audit/stop',[ServerMonitoringController::class,'stopAudit'])->middleware('permission:server_monitoring.audit')->name('audit.stop');
 Route::post('/{server}/disable',[ServerMonitoringController::class,'disable'])->middleware('permission:server_monitoring.manage')->name('disable');
});