<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SoftwareManagementController;

Route::middleware('auth')->prefix('admin/software-management')->name('admin.software_management.')->group(function () {
    Route::get('/', [SoftwareManagementController::class, 'index'])->middleware('permission:software.requests.view')->name('index');
    Route::get('/create', [SoftwareManagementController::class, 'create'])->middleware('permission:software.requests.create')->name('create');
    Route::post('/', [SoftwareManagementController::class, 'store'])->middleware('permission:software.requests.create')->name('store');
    Route::get('/licenses', [SoftwareManagementController::class, 'licenses'])->middleware('permission:software.licenses.view')->name('licenses');
    Route::post('/licenses', [SoftwareManagementController::class, 'storeLicense'])->middleware('permission:software.licenses.manage')->name('licenses.store');
    Route::get('/attachments/{softwareAttachment}/download', [SoftwareManagementController::class, 'download'])->middleware('permission:software.attachments.view')->name('attachments.download');
    Route::get('/{softwareRequest}', [SoftwareManagementController::class, 'show'])->middleware('permission:software.requests.view')->name('show');
    Route::post('/{softwareRequest}/hod-decision', [SoftwareManagementController::class, 'decideHod'])->middleware('permission:software.approve')->name('hod_decision');
    Route::post('/{softwareRequest}/assessment', [SoftwareManagementController::class, 'assess'])->middleware('permission:software.assess')->name('assessment');
    Route::post('/{softwareRequest}/purchase-decision', [SoftwareManagementController::class, 'decidePurchase'])->middleware('permission:software.purchase_approve')->name('purchase_decision');
    Route::post('/{softwareRequest}/installation', [SoftwareManagementController::class, 'install'])->middleware('permission:software.install')->name('installation');
    Route::post('/{softwareRequest}/acceptance', [SoftwareManagementController::class, 'accept'])->middleware('permission:software.requests.view')->name('acceptance');
    Route::post('/{softwareRequest}/checklist', [SoftwareManagementController::class, 'saveChecklist'])->middleware('permission:software.close')->name('checklist');
    Route::post('/{softwareRequest}/close', [SoftwareManagementController::class, 'close'])->middleware('permission:software.close')->name('close');
    Route::post('/{softwareRequest}/exceptions', [SoftwareManagementController::class, 'addException'])->middleware('permission:software.exception.create')->name('exceptions.store');
    Route::post('/exceptions/{softwareException}/decision', [SoftwareManagementController::class, 'decideException'])->middleware('permission:software.exception.approve')->name('exceptions.decision');
    Route::post('/{softwareRequest}/attachments', [SoftwareManagementController::class, 'upload'])->middleware('permission:software.attachments.manage')->name('attachments.store');
});