<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\User; use App\Models\UserGroup; use Illuminate\Http\Request; use Illuminate\Support\Facades\Hash;
class UserController extends Controller {
 public function index(){return view('admin.users.index',['users'=>User::with('group')->orderBy('name')->paginate(20)]);}
 public function create(){return view('admin.users.form',['user'=>new User(),'groups'=>UserGroup::orderBy('name')->get()]);}
 public function store(Request $r){$d=$r->validate(['name'=>'required|string|max:255','email'=>'required|email|max:255|unique:users,email','password'=>'required|string|min:8|confirmed','user_group_id'=>'nullable|exists:user_groups,id']); User::create($d); return redirect()->route('admin.users.index')->with('success','User created.');}
 public function edit(User $user){return view('admin.users.form',['user'=>$user,'groups'=>UserGroup::orderBy('name')->get()]);}
 public function update(Request $r,User $user){$rules=['name'=>'required|string|max:255','email'=>'required|email|max:255|unique:users,email,'.$user->id,'user_group_id'=>'nullable|exists:user_groups,id','is_active'=>'nullable|boolean']; if($r->filled('password'))$rules['password']='string|min:8|confirmed'; $d=$r->validate($rules); if(!$r->filled('password'))unset($d['password']); $user->update($d+['is_active'=>$r->boolean('is_active')]); return redirect()->route('admin.users.index')->with('success','User updated.');}
 public function destroy(User $user){if($user->id===auth()->id())return back()->withErrors('You cannot delete your own account.'); $user->delete(); return back()->with('success','User deleted.');}
}
