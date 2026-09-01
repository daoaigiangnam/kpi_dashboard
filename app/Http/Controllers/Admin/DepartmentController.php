<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $search=trim((string)$request->query('search','')); $showHidden=$request->boolean('show_hidden');
        $query=Department::withCount('users'); if($showHidden)$query->withTrashed();
        $departments=$query->when($search!=='' ,fn($q)=>$q->where(fn($x)=>$x->where('code','like',"%{$search}%")->orWhere('name','like',"%{$search}%")->orWhere('description','like',"%{$search}%")))->orderBy('name')->paginate(20)->withQueryString();
        return view('admin.departments.index',compact('departments','search','showHidden'));
    }
    public function create(){return view('admin.departments.form',['department'=>new Department(['is_active'=>true])]);}
    public function store(Request $request){Department::create($request->validate($this->rules()));return redirect()->route('admin.departments.index')->with('success','Department created.');}
    public function edit(Department $department){return view('admin.departments.form',compact('department'));}
    public function update(Request $request,Department $department){$department->update($request->validate($this->rules($department)));return redirect()->route('admin.departments.index')->with('success','Department updated.');}
    public function destroy(Department $department){$department->delete();return back()->with('success','Department hidden. The record was retained for history.');}
    public function restore(int $department){Department::withTrashed()->findOrFail($department)->restore();return back()->with('success','Department restored.');}
    private function rules(?Department $department=null):array{return ['code'=>['required','string','max:50','alpha_dash',Rule::unique('departments','code')->ignore($department?->id)],'name'=>'required|string|max:150','description'=>'nullable|string|max:255','is_active'=>'boolean'];}
}
