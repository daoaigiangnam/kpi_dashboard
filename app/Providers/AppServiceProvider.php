<?php
namespace App\Providers;
use App\Models\User; use Illuminate\Support\Facades\Gate; use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider { public function boot():void { Gate::before(fn(User $user)=>$user->isSuperAdmin()?true:null); Gate::define('access-admin',fn(User $user)=>$user->isSuperAdmin()||$user->hasPermission('admin.view')); } }
