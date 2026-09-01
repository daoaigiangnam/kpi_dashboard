<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserGroupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('users', UserController::class)->only(['index', 'create', 'store']);
    Route::get('groups', [UserGroupController::class, 'index'])->name('groups.index');
    Route::get('groups/{group}/edit', [UserGroupController::class, 'edit'])->name('groups.edit');
    Route::put('groups/{group}', [UserGroupController::class, 'update'])->name('groups.update');
});
