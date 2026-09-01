<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\JobTitleController;

Route::get('/login',[LoginController::class,'show'])->name('login');
Route::post('/login',[LoginController::class,'login'])->name('login.attempt');
Route::post('/logout',[LoginController::class,'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function(){
 Route::get('/',[DashboardController::class,'index'])->middleware('permission:admin.view')->name('dashboard');

 Route::get('users',[UserController::class,'index'])->middleware('permission:users.view')->name('users.index');
 Route::get('users/create',[UserController::class,'create'])->middleware('permission:users.create')->name('users.create');
 Route::post('users',[UserController::class,'store'])->middleware('permission:users.create')->name('users.store');
 Route::get('users/{user}/edit',[UserController::class,'edit'])->middleware('permission:users.edit')->name('users.edit');
 Route::put('users/{user}',[UserController::class,'update'])->middleware('permission:users.edit')->name('users.update');
 Route::delete('users/{user}',[UserController::class,'destroy'])->middleware('permission:users.delete')->name('users.destroy');

 Route::get('groups',[GroupController::class,'index'])->middleware('permission:groups.view')->name('groups.index');
 Route::get('groups/create',[GroupController::class,'create'])->middleware('permission:groups.create')->name('groups.create');
 Route::post('groups',[GroupController::class,'store'])->middleware('permission:groups.create')->name('groups.store');
 Route::get('groups/{group}/edit',[GroupController::class,'edit'])->middleware('permission:groups.edit')->name('groups.edit');
 Route::put('groups/{group}',[GroupController::class,'update'])->middleware('permission:groups.edit')->name('groups.update');
 Route::delete('groups/{group}',[GroupController::class,'destroy'])->middleware('permission:groups.delete')->name('groups.destroy');

 Route::get('job-titles',[JobTitleController::class,'index'])->middleware('permission:job_titles.view')->name('job_titles.index');
 Route::get('job-titles/create',[JobTitleController::class,'create'])->middleware('permission:job_titles.create')->name('job_titles.create');
 Route::post('job-titles',[JobTitleController::class,'store'])->middleware('permission:job_titles.create')->name('job_titles.store');
 Route::get('job-titles/{jobTitle}/edit',[JobTitleController::class,'edit'])->middleware('permission:job_titles.edit')->name('job_titles.edit');
 Route::put('job-titles/{jobTitle}',[JobTitleController::class,'update'])->middleware('permission:job_titles.edit')->name('job_titles.update');
 Route::delete('job-titles/{jobTitle}',[JobTitleController::class,'destroy'])->middleware('permission:job_titles.delete')->name('job_titles.destroy');
});

Route::redirect('/','/admin');
