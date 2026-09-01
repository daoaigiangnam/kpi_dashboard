<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $users = User::with(['group','jobTitle'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('employee_code','like',"%{$search}%")
                      ->orWhere('name','like',"%{$search}%")
                      ->orWhere('email','like',"%{$search}%")
                      ->orWhere('phone','like',"%{$search}%")
                      ->orWhere('department','like',"%{$search}%");
                });
            })->orderBy('name')->paginate(20)->withQueryString();
        return view('admin.users.index', compact('users','search'));
    }

    public function create()
    {
        return view('admin.users.form', ['user'=>new User(['is_active'=>true]), 'groups'=>UserGroup::orderBy('name')->get(), 'jobTitles'=>JobTitle::where('is_active',true)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        User::create($data + ['is_active'=>$request->boolean('is_active')]);
        return redirect()->route('admin.users.index')->with('success','User created.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', ['user'=>$user, 'groups'=>UserGroup::orderBy('name')->get(), 'jobTitles'=>JobTitle::where('is_active',true)->orderBy('name')->get()]);
    }

    public function update(Request $request, User $user)
    {
        $rules = $this->rules($user, false);
        if ($request->filled('password')) $rules['password'] = 'string|min:8|confirmed';
        $data = $request->validate($rules);
        if (!$request->filled('password')) unset($data['password']);
        $user->update($data + ['is_active'=>$request->boolean('is_active')]);
        return redirect()->route('admin.users.index')->with('success','User updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) return back()->withErrors('You cannot delete your own account.');
        $user->delete();
        return back()->with('success','User deleted.');
    }

    public function template()
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->setTitle('Users');
        $sheet->fromArray([
            ['Employee Code','Name','Email','Phone','Date of Birth','Gender','Join Date','Department','Location','Group','Job Title Code','Notes','Password','Status'],
            ['EMP-0001','Example Employee','employee@example.com','','1990-01-15','Male','2026-01-01','IT','HCMC','Employee','IT-HELPDESK-L1','Example only - remove this row before import.','ChangeMe123!','Active'],
        ], null, 'A1');
        $sheet->getStyle('A1:N1')->getFont()->setBold(true);
        foreach (range('A','N') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->freezePane('A2'); $sheet->setAutoFilter('A1:N2');
        $writer = new Xlsx($sheet->getParent());
        return response()->streamDownload(fn()=> $writer->save('php://output'), 'user-import-template.xlsx', ['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->query('search',''));
        $users = User::with(['group','jobTitle'])->when($search !== '', function($q) use($search) {
            $q->where(function($q) use($search) { $q->where('employee_code','like',"%{$search}%")->orWhere('name','like',"%{$search}%")->orWhere('email','like',"%{$search}%")->orWhere('phone','like',"%{$search}%")->orWhere('department','like',"%{$search}%"); });
        })->orderBy('name')->get();
        $sheet = (new Spreadsheet())->getActiveSheet(); $sheet->setTitle('Users');
        $sheet->fromArray([['Employee Code','Name','Email','Phone','Date of Birth','Gender','Join Date','Department','Location','Group','Job Title Code','Job Title','Target Workload Point','Notes','Status']],null,'A1');
        $row=2; foreach($users as $u){$sheet->fromArray([[$u->employee_code,$u->name,$u->email,$u->phone,$u->date_of_birth?->format('Y-m-d'),$u->gender,$u->join_date?->format('Y-m-d'),$u->department,$u->location,$u->group?->name,$u->jobTitle?->code,$u->jobTitle?->name,$u->jobTitle?->target_workload_point,$u->notes,$u->is_active?'Active':'Inactive']],null,"A{$row}");$row++;}
        $sheet->getStyle('A1:O1')->getFont()->setBold(true); foreach(range('A','O') as $column)$sheet->getColumnDimension($column)->setAutoSize(true); $sheet->freezePane('A2'); $sheet->setAutoFilter('A1:O'.max(1,$row-1));
        $writer=new Xlsx($sheet->getParent()); return response()->streamDownload(fn()=> $writer->save('php://output'),'users-'.now()->format('Ymd-His').'.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(Request $request)
    {
        $request->validate(['file'=>'required|file|mimes:xlsx,xls,csv|max:10240']);
        try {$rows=IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null,true,true,true);} catch(\Throwable $e){return back()->withErrors('The Excel file could not be read. Please use the User export/template format.');}
        if(count($rows)<2)return back()->withErrors('The import file contains no data rows.');
        $headers=array_map(fn($v)=>strtolower(trim((string)$v)), $rows[1]);
        $required=['employee code','name','email','phone','date of birth','gender','join date','department','location','group','job title code','notes','password','status'];
        $missing=array_values(array_diff($required,$headers)); if($missing)return back()->withErrors('Invalid template. Missing columns: '.implode(', ',$missing).'.');
        $columns=array_flip($headers); $errors=[]; $prepared=[]; $seen=[];
        foreach(array_slice($rows,1,null,true) as $n=>$row){
            $v=fn($h)=>trim((string)($row[$columns[$h]]??'')); $code=$v('employee code'); $name=$v('name'); $email=$v('email'); $password=$v('password'); $status=strtolower($v('status')?:'active'); $groupName=$v('group'); $jobCode=$v('job title code');
            if($code===''&&$name===''&&$email==='')continue;
            if($code===''||!preg_match('/^[A-Za-z0-9_-]+$/',$code)){$errors[]="Row {$n}: Employee Code is required and may contain only letters, numbers, hyphens and underscores.";continue;}
            if($name===''){$errors[]="Row {$n}: Name is required.";continue;} if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$errors[]="Row {$n}: Email is invalid.";continue;}
            if(isset($seen[$code])){$errors[]="Row {$n}: Duplicate Employee Code '{$code}'.";continue;} $seen[$code]=true;
            if(!in_array($status,['active','inactive'],true)){$errors[]="Row {$n}: Status must be Active or Inactive.";continue;}
            $dob=$v('date of birth'); $join=$v('join date');
            if($dob!==''&& !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dob)){$errors[]="Row {$n}: Date of Birth must use YYYY-MM-DD.";continue;}
            if($join!==''&& !preg_match('/^\d{4}-\d{2}-\d{2}$/',$join)){$errors[]="Row {$n}: Join Date must use YYYY-MM-DD.";continue;}
            $group=$groupName!==''?UserGroup::where('name',$groupName)->first():null; $job=$jobCode!==''?JobTitle::where('code',$jobCode)->first():null;
            if($groupName!==''&&!$group){$errors[]="Row {$n}: Group '{$groupName}' was not found.";continue;} if($jobCode!==''&&!$job){$errors[]="Row {$n}: Job Title Code '{$jobCode}' was not found.";continue;}
            $existing=User::where('employee_code',$code)->first() ?: User::where('email',$email)->first();
            if(!$existing && strlen($password)<8){$errors[]="Row {$n}: Password is required for new users and must be at least 8 characters.";continue;}
            $data=['employee_code'=>$code,'name'=>$name,'email'=>$email,'phone'=>$v('phone')?:null,'date_of_birth'=>$dob?:null,'gender'=>$v('gender')?:null,'join_date'=>$join?:null,'department'=>$v('department')?:null,'location'=>$v('location')?:null,'user_group_id'=>$group?->id,'job_title_id'=>$job?->id,'notes'=>$v('notes')?:null,'is_active'=>$status==='active'];
            if($password!=='')$data['password']=Hash::make($password); $prepared[]=[$existing,$data];
        }
        if($errors)return back()->withErrors(array_slice($errors,0,50))->with('import_error_count',count($errors)); if(!$prepared)return back()->withErrors('The import file contains no valid data rows.');
        DB::transaction(function()use($prepared){foreach($prepared as [$existing,$data]){if($existing)$existing->update($data);else User::create($data);}});
        return back()->with('success',count($prepared).' user(s) imported successfully. Existing records were updated by Employee Code or Email.');
    }

    private function rules(?User $user=null, bool $creating=true): array
    {
        return [
            'employee_code'=>['required','string','max:50','alpha_dash',Rule::unique('users','employee_code')->ignore($user?->id)],
            'name'=>'required|string|max:255','email'=>['required','email','max:255',Rule::unique('users','email')->ignore($user?->id)],
            'phone'=>'nullable|string|max:30','date_of_birth'=>'nullable|date','gender'=>'nullable|in:Male,Female,Other','join_date'=>'nullable|date','department'=>'nullable|string|max:150','location'=>'nullable|string|max:150',
            'user_group_id'=>'nullable|exists:user_groups,id','job_title_id'=>'nullable|exists:job_titles,id','notes'=>'nullable|string|max:500','is_active'=>'boolean',
            'password'=>$creating?'required|string|min:8|confirmed':'nullable|string|min:8|confirmed',
        ];
    }
}
