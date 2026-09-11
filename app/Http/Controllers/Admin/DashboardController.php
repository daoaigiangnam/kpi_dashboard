<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\User; use App\Models\UserGroup; use App\Models\Permission;
class DashboardController extends Controller { public function index(){return view('admin.dashboard',['userCount'=>User::count(),'activeUsers'=>User::where('is_active',true)->count(),'groupCount'=>UserGroup::count(),'permissionCount'=>Permission::count()]);} }
