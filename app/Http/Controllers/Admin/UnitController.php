<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $search=trim((string)$request->query('search','')); $showHidden=$request->boolean('show_hidden');
        $query=Unit::withCount('users'); if($showHidden)$query->withTrashed();
        $units=$query->when($search!=='' ,fn($q)=>$q->where(fn($x)=>$x->where('code','like',"%{$search}%")->orWhere('name','like',"%{$search}%")->orWhere('description','like',"%{$search}%")))->orderBy('name')->paginate(20)->withQueryString();
        return view('admin.units.index',compact('units','search','showHidden'));
    }
    public function create(){return view('admin.units.form',['unit'=>new Unit(['is_active'=>true])]);}
    public function store(Request $request){Unit::create($request->validate($this->rules()));return redirect()->route('admin.units.index')->with('success','Unit created.');}
    public function edit(Unit $unit){return view('admin.units.form',compact('unit'));}
    public function update(Request $request,Unit $unit){$unit->update($request->validate($this->rules($unit)));return redirect()->route('admin.units.index')->with('success','Unit updated.');}
    public function destroy(Unit $unit){$unit->delete();return back()->with('success','Unit hidden. The record was retained for history.');}
    public function restore(int $unit){Unit::withTrashed()->findOrFail($unit)->restore();return back()->with('success','Unit restored.');}
    private function rules(?Unit $unit=null):array{return ['code'=>['required','string','max:50','alpha_dash',Rule::unique('units','code')->ignore($unit?->id)],'name'=>'required|string|max:150','description'=>'nullable|string|max:255','is_active'=>'boolean'];}
}
