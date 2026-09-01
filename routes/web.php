<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\JobTitleController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\UnitController;

Route::get('/login',[LoginController::class,'show'])->name('login');
Route::post('/login',[LoginController::class,'login'])->name('login.attempt');
Route::post('/logout',[LoginController::class,'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function(){
 Route::get('/',[DashboardController::class,'index'])->middleware('permission:admin.view')->name('dashboard');
 Route::get('users',[UserController::class,'index'])->middleware('permission:users.view')->name('users.index');
 Route::get('users/template',[UserController::class,'template'])->middleware('permission:users.import')->name('users.template');
 Route::get('users/export',[UserController::class,'export'])->middleware('permission:users.export')->name('users.export');
 Route::post('users/import',[UserController::class,'import'])->middleware('permission:users.import')->name('users.import');
 Route::get('users/create',[UserController::class,'create'])->middleware('permission:users.create')->name('users.create');
 Route::post('users',[UserController::class,'store'])->middleware('permission:users.create')->name('users.store');
 Route::get('users/{user}/edit',[UserController::class,'edit'])->middleware('permission:users.edit')->name('users.edit');
 Route::put('users/{user}',[UserController::class,'update'])->middleware('permission:users.edit')->name('users.update');
 Route::delete('users/{user}',[UserController::class,'destroy'])->middleware('permission:users.delete')->name('users.destroy');
 Route::patch('users/{user}/restore',[UserController::class,'restore'])->middleware('permission:users.delete')->name('users.restore');
 Route::get('groups',[GroupController::class,'index'])->middleware('permission:groups.view')->name('groups.index');
 Route::get('groups/create',[GroupController::class,'create'])->middleware('permission:groups.create')->name('groups.create');
 Route::post('groups',[GroupController::class,'store'])->middleware('permission:groups.create')->name('groups.store');
 Route::get('groups/{group}/edit',[GroupController::class,'edit'])->middleware('permission:groups.edit')->name('groups.edit');
 Route::put('groups/{group}',[GroupController::class,'update'])->middleware('permission:groups.edit')->name('groups.update');
 Route::delete('groups/{group}',[GroupController::class,'destroy'])->middleware('permission:groups.delete')->name('groups.destroy');
 Route::patch('groups/{group}/restore',[GroupController::class,'restore'])->middleware('permission:groups.delete')->name('groups.restore');
 Route::get('job-titles',[JobTitleController::class,'index'])->middleware('permission:job_titles.view')->name('job_titles.index');
 Route::get('job-titles/template',[JobTitleController::class,'template'])->middleware('permission:job_titles.import')->name('job_titles.template');
 Route::get('job-titles/export',[JobTitleController::class,'export'])->middleware('permission:job_titles.export')->name('job_titles.export');
 Route::post('job-titles/import',[JobTitleController::class,'import'])->middleware('permission:job_titles.import')->name('job_titles.import');
 Route::get('job-titles/create',[JobTitleController::class,'create'])->middleware('permission:job_titles.create')->name('job_titles.create');
 Route::post('job-titles',[JobTitleController::class,'store'])->middleware('permission:job_titles.create')->name('job_titles.store');
 Route::get('job-titles/{jobTitle}/edit',[JobTitleController::class,'edit'])->middleware('permission:job_titles.edit')->name('job_titles.edit');
 Route::put('job-titles/{jobTitle}',[JobTitleController::class,'update'])->middleware('permission:job_titles.edit')->name('job_titles.update');
 Route::delete('job-titles/{jobTitle}',[JobTitleController::class,'destroy'])->middleware('permission:job_titles.delete')->name('job_titles.destroy');
 Route::patch('job-titles/{jobTitle}/restore',[JobTitleController::class,'restore'])->middleware('permission:job_titles.delete')->name('job_titles.restore');
 Route::get('departments',[DepartmentController::class,'index'])->middleware('permission:departments.view')->name('departments.index');
 Route::get('departments/create',[DepartmentController::class,'create'])->middleware('permission:departments.create')->name('departments.create');
 Route::post('departments',[DepartmentController::class,'store'])->middleware('permission:departments.create')->name('departments.store');
 Route::get('departments/{department}/edit',[DepartmentController::class,'edit'])->middleware('permission:departments.edit')->name('departments.edit');
 Route::put('departments/{department}',[DepartmentController::class,'update'])->middleware('permission:departments.edit')->name('departments.update');
 Route::delete('departments/{department}',[DepartmentController::class,'destroy'])->middleware('permission:departments.delete')->name('departments.destroy');
 Route::patch('departments/{department}/restore',[DepartmentController::class,'restore'])->middleware('permission:departments.delete')->name('departments.restore');
 Route::get('units',[UnitController::class,'index'])->middleware('permission:units.view')->name('units.index');
 Route::get('units/create',[UnitController::class,'create'])->middleware('permission:units.create')->name('units.create');
 Route::post('units',[UnitController::class,'store'])->middleware('permission:units.create')->name('units.store');
 Route::get('units/{unit}/edit',[UnitController::class,'edit'])->middleware('permission:units.edit')->name('units.edit');
 Route::put('units/{unit}',[UnitController::class,'update'])->middleware('permission:units.edit')->name('units.update');
 Route::delete('units/{unit}',[UnitController::class,'destroy'])->middleware('permission:units.delete')->name('units.destroy');
 Route::patch('units/{unit}/restore',[UnitController::class,'restore'])->middleware('permission:units.delete')->name('units.restore');
});

Route::redirect('/','/admin');
