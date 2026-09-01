<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\UserGroup; use App\Models\Permission; use Illuminate\Http\Request;
class GroupController extends Controller {
 public function index(Request $request){$showHidden=$request->boolean('show_hidden');$query=UserGroup::withCount('users')->with('permissions');if($showHidden)$query->withTrashed();$groups=$query->orderBy('name')->paginate(20)->withQueryString();return view('admin.groups.index',compact('groups','showHidden'));}
 public function create(){return view('admin.groups.form',['group'=>new UserGroup(),'permissions'=>Permission::orderBy('module')->orderBy('name')->get()->groupBy('module')]);}
 public function store(Request $r){$d=$r->validate(['name'=>'required|string|max:100|unique:user_groups,name','description'=>'nullable|string|max:255','permissions'=>'array','permissions.*'=>'exists:permissions,id']);$g=UserGroup::create($d);$g->permissions()->sync($d['permissions']??[]);return redirect()->route('admin.groups.index')->with('success','Group created.');}
 public function edit(UserGroup $group){return view('admin.groups.form',['group'=>$group,'permissions'=>Permission::orderBy('module')->orderBy('name')->get()->groupBy('module')]);}
 public function update(Request $r,UserGroup $group){if($group->is_system&&$group->name==='Super Admin'&&$r->input('name')!=='Super Admin')return back()->withErrors('Super Admin cannot be renamed.');$d=$r->validate(['name'=>'required|string|max:100|unique:user_groups,name,'.$group->id,'description'=>'nullable|string|max:255','permissions'=>'array','permissions.*'=>'exists:permissions,id']);$group->update($d);$group->permissions()->sync($d['permissions']??[]);return redirect()->route('admin.groups.index')->with('success','Group updated.');}
 public function destroy(UserGroup $group){if($group->is_system)return back()->withErrors('System groups cannot be hidden.');$group->delete();return back()->with('success','Group hidden. The record was retained for history.');}
 public function restore(int $group){$model=UserGroup::withTrashed()->findOrFail($group);$model->restore();return back()->with('success','Group restored.');}
}
