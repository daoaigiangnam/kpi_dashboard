<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserGroup;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $showDeleted = $request->boolean('deleted');
        $query = User::with(['group','jobTitle','departmentRelation','unit']);
        if ($showDeleted) $query->onlyTrashed();
        $users = $query->when($search !== '', function ($q) use ($search) {
            $q->where(function ($q) use ($search) {
                $q->where('employee_code','like',"%{$search}%")
                  ->orWhere('name','like',"%{$search}%")
                  ->orWhere('email','like',"%{$search}%")
                  ->orWhere('phone','like',"%{$search}%")
                  ->orWhereHas('departmentRelation', fn($d) => $d->withTrashed()->where('name','like',"%{$search}%"))
                  ->orWhereHas('unit', fn($u) => $u->withTrashed()->where('name','like',"%{$search}%"));
            });
        })->orderBy('name')->paginate(20)->withQueryString();
        $pendingCount = User::where('registration_status','pending')->count();
        return view('admin.users.index', compact('users','search','showDeleted','pendingCount'));
    }

    public function pending()
    {
        $this->ensureSuperAdmin();
        $users = User::with(['jobTitle','departmentRelation','unit'])->where('registration_status','pending')->orderBy('created_at')->get();
        return view('admin.users.pending', compact('users'));
    }

    public function approve(User $user)
    {
        $this->ensureSuperAdmin();
        if ($user->registration_status !== 'pending') return back()->withErrors('This registration has already been reviewed.');
        $user->update([
            'is_active' => true,
            'registration_status' => 'approved',
            'registration_reviewed_at' => now(),
            'registration_reviewed_by' => auth()->id(),
            'registration_rejection_reason' => null,
        ]);
        return back()->with('success', 'Registration approved. The user can now sign in. Assign an access group from User Management if needed.');
    }

    public function reject(Request $request, User $user)
    {
        $this->ensureSuperAdmin();
        if ($user->registration_status !== 'pending') return back()->withErrors('This registration has already been reviewed.');
        $data = $request->validate(['reason' => ['nullable','string','max:1000']]);
        $user->update([
            'is_active' => false,
            'registration_status' => 'rejected',
            'registration_reviewed_at' => now(),
            'registration_reviewed_by' => auth()->id(),
            'registration_rejection_reason' => $data['reason'] ?? null,
        ]);
        return back()->with('success', 'Registration rejected. The account remains inactive.');
    }

    private function ensureSuperAdmin(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403, 'Only the Super Admin can approve or reject user registrations.');
    }

    public function create()
    {
        return view('admin.users.form', [
            'user'=>new User(['is_active'=>true]),
            'groups'=>UserGroup::orderBy('name')->get(),
            'jobTitles'=>JobTitle::where('is_active',true)->orderBy('name')->get(),
            'departments'=>Department::where('is_active',true)->orderBy('name')->get(),
            'units'=>Unit::where('is_active',true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $user = User::create($data + [
            'password' => Hash::make(Str::random(64)),
            'is_active' => $request->boolean('is_active'),
            'registration_status' => 'approved',
        ]);

        try {
            $this->sendPasswordSetupEmail($user, true);
            return redirect()->route('admin.users.index')->with('success', 'User created. A password setup email with OTP and secure link has been sent to '.$user->email.'.');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('admin.users.index')->with('warning', 'User created, but the password setup email could not be sent. Use Reset Password from the user record to send it again.');
        }
    }

    public function edit(User $user)
    {
        return view('admin.users.form', [
            'user'=>$user,
            'groups'=>UserGroup::orderBy('name')->get(),
            'jobTitles'=>JobTitle::where('is_active',true)->orWhere('id',$user->job_title_id)->orderBy('name')->get(),
            'departments'=>Department::where('is_active',true)->orWhere('id',$user->department_id)->orderBy('name')->get(),
            'units'=>Unit::where('is_active',true)->orWhere('id',$user->unit_id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate($this->rules($user, false));
        $user->update($data + ['is_active'=>$request->boolean('is_active')]);
        if ($user->registration_status === 'pending' && $request->boolean('is_active')) {
            $user->update(['registration_status'=>'approved','registration_reviewed_at'=>now(),'registration_reviewed_by'=>auth()->id()]);
        }
        return redirect()->route('admin.users.index')->with('success','User updated.');
    }

    public function resetPassword(User $user)
    {
        if (!$user->is_active) {
            return back()->withErrors('The user is inactive. Activate the account before sending a password setup/reset email.');
        }

        try {
            $this->sendPasswordSetupEmail($user, false);
            return back()->with('success', 'A new password reset email with OTP and secure link has been sent to '.$user->email.'.');
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors('The password reset email could not be sent. Please check the System Settings mail configuration.');
        }
    }

    private function sendPasswordSetupEmail(User $user, bool $initialSetup = false): void
    {
        $token = Password::broker()->createToken($user);
        $otp = (string) random_int(100000, 999999);

        DB::table('password_reset_otps')->where('email', $user->email)->delete();
        DB::table('password_reset_otps')->insert([
            'email' => $user->email,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->notify(new PasswordResetOtpNotification($token, $otp, $initialSetup));
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) return back()->withErrors('You cannot delete your own account.');
        $user->delete();
        return back()->with('success','User deleted. The record was retained for history and can be restored later.');
    }

    public function restore(int $user)
    {
        $model = User::withTrashed()->findOrFail($user);
        $model->restore();
        return back()->with('success','User restored.');
    }

    public function template()
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->setTitle('Users');
        $sheet->fromArray([
            ['Employee Code','Name','Email','Phone','Date of Birth','Gender','Join Date','Department','Unit','Group','Job Title Code','Notes','Status'],
            ['EMP-0001','Example Employee','employee@example.com','','1990-01-15','Male','2026-01-01','IT','HCMC','Employee','IT-HELPDESK-L1','Example only - remove this row before import.','Active'],
        ], null, 'A1');
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        foreach (range('A','M') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->freezePane('A2'); $sheet->setAutoFilter('A1:M2');
        $writer = new Xlsx($sheet->getParent());
        return response()->streamDownload(fn()=> $writer->save('php://output'), 'user-import-template.xlsx', ['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->query('search',''));
        $showDeleted = $request->boolean('deleted');
        $query = User::withTrashed()->with(['group','jobTitle','departmentRelation','unit']);
        if (!$showDeleted) $query->whereNull('users.deleted_at');
        $users = $query->when($search !== '', function($q) use($search) {
            $q->where(function($q) use($search) {
                $q->where('employee_code','like',"%{$search}%")->orWhere('name','like',"%{$search}%")->orWhere('email','like',"%{$search}%")->orWhere('phone','like',"%{$search}%")
                  ->orWhereHas('departmentRelation',fn($d)=>$d->where('name','like',"%{$search}%"))->orWhereHas('unit',fn($u)=>$u->where('name','like',"%{$search}%"));
            });
        })->orderBy('name')->get();
        $sheet = (new Spreadsheet())->getActiveSheet(); $sheet->setTitle('Users');
        $sheet->fromArray([['Employee Code','Name','Email','Phone','Date of Birth','Gender','Join Date','Department','Unit','Group','Job Title Code','Job Title','Target Workload Point','Notes','Status']],null,'A1');
        $row=2; foreach($users as $u){$status=$u->trashed()?'Deleted':($u->is_active?'Active':'Inactive');$sheet->fromArray([[$u->employee_code,$u->name,$u->email,$u->phone,$u->date_of_birth?->format('Y-m-d'),$u->gender,$u->join_date?->format('Y-m-d'),$u->departmentRelation?->name,$u->unit?->name,$u->group?->name,$u->jobTitle?->code,$u->jobTitle?->name,$u->jobTitle?->target_workload_point,$u->notes,$status]],null,"A{$row}");$row++;}
        $sheet->getStyle('A1:O1')->getFont()->setBold(true); foreach(range('A','O') as $column)$sheet->getColumnDimension($column)->setAutoSize(true); $sheet->freezePane('A2'); $sheet->setAutoFilter('A1:O'.max(1,$row-1));
        $writer=new Xlsx($sheet->getParent()); return response()->streamDownload(fn()=> $writer->save('php://output'),'users-'.now()->format('Ymd-His').'.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(Request $request)
    {
        $request->validate(['file'=>'required|file|mimes:xlsx,xls,csv|max:10240']);
        try {$rows=IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null,true,true,true);} catch(\Throwable $e){return back()->withErrors('The Excel file could not be read. Please use the User export/template format.');}
        if(count($rows)<2)return back()->withErrors('The import file contains no data rows.');
        $headers=array_map(fn($v)=>strtolower(trim((string)$v)), $rows[1]);
        $required=['employee code','name','email','phone','date of birth','gender','join date','department','unit','group','job title code','notes','status'];
        $missing=array_values(array_diff($required,$headers)); if($missing)return back()->withErrors('Invalid template. Missing columns: '.implode(', ',$missing).'.');
        $columns=array_flip($headers); $errors=[]; $prepared=[]; $seen=[];
        foreach(array_slice($rows,1,null,true) as $n=>$row){
            $v=fn($h)=>trim((string)($row[$columns[$h]]??'')); $code=$v('employee code'); $name=$v('name'); $email=$v('email'); $status=strtolower($v('status')?:'active'); $groupName=$v('group'); $jobCode=$v('job title code'); $departmentName=$v('department'); $unitName=$v('unit');
            if($code===''&&$name===''&&$email==='')continue;
            if($code===''||!preg_match('/^[A-Za-z0-9_-]+$/',$code)){$errors[]="Row {$n}: Employee Code is required and may contain only letters, numbers, hyphens and underscores.";continue;}
            if($name===''){$errors[]="Row {$n}: Name is required.";continue;} if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$errors[]="Row {$n}: Email is invalid.";continue;}
            if(isset($seen[$code])){$errors[]="Row {$n}: Duplicate Employee Code '{$code}'.";continue;} $seen[$code]=true;
            if(!in_array($status,['active','inactive'],true)){$errors[]="Row {$n}: Status must be Active or Inactive.";continue;}
            $dob=$v('date of birth'); $join=$v('join date');
            if($dob!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dob)){$errors[]="Row {$n}: Date of Birth must use YYYY-MM-DD.";continue;}
            if($join!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$join)){$errors[]="Row {$n}: Join Date must use YYYY-MM-DD.";continue;}
            $group=$groupName!==''?UserGroup::where('name',$groupName)->first():null; $job=$jobCode!==''?JobTitle::where('code',$jobCode)->first():null; $department=$departmentName!==''?Department::where('name',$departmentName)->first():null; $unit=$unitName!==''?Unit::where('name',$unitName)->first():null;
            if($groupName!==''&&!$group){$errors[]="Row {$n}: Group '{$groupName}' was not found.";continue;} if($jobCode!==''&&!$job){$errors[]="Row {$n}: Job Title Code '{$jobCode}' was not found.";continue;} if($departmentName!==''&&!$department){$errors[]="Row {$n}: Department '{$departmentName}' was not found.";continue;} if($unitName!==''&&!$unit){$errors[]="Row {$n}: Unit '{$unitName}' was not found.";continue;}
            $existing=User::withTrashed()->where('employee_code',$code)->first() ?: User::withTrashed()->where('email',$email)->first();
            $data=['employee_code'=>$code,'name'=>$name,'email'=>$email,'phone'=>$v('phone')?:null,'date_of_birth'=>$dob?:null,'gender'=>$v('gender')?:null,'join_date'=>$join?:null,'department'=>$departmentName?:null,'location'=>$unitName?:null,'department_id'=>$department?->id,'unit_id'=>$unit?->id,'user_group_id'=>$group?->id,'job_title_id'=>$job?->id,'notes'=>$v('notes')?:null,'is_active'=>$status==='active','registration_status'=>'approved'];
            if (!$existing) $data['password'] = Hash::make(Str::random(64));
            $prepared[]=[$existing,$data];
        }
        if($errors)return back()->withErrors(array_slice($errors,0,50))->with('import_error_count',count($errors)); if(!$prepared)return back()->withErrors('The import file contains no valid data rows.');
        $newUsers=[];
        DB::transaction(function()use($prepared,&$newUsers){foreach($prepared as [$existing,$data]){if($existing){$existing->update($data);if($existing->trashed())$existing->restore();}else{$newUsers[]=User::create($data);}}});
        $mailFailures=0;
        foreach($newUsers as $newUser){try{$this->sendPasswordSetupEmail($newUser, true);}catch(\Throwable $e){report($e);$mailFailures++;}}
        $message=count($prepared).' user(s) imported successfully. New user accounts receive a password setup email with OTP and secure link.';
        if($mailFailures>0)$message.=" {$mailFailures} setup email(s) could not be sent; use Reset Password from the user record to resend.";
        return back()->with($mailFailures>0?'warning':'success',$message);
    }

    private function rules(?User $user=null, bool $creating=true): array
    {
        return [
            'employee_code'=>['required','string','max:50','alpha_dash',Rule::unique('users','employee_code')->ignore($user?->id)],
            'name'=>'required|string|max:255','email'=>['required','email','max:255',Rule::unique('users','email')->ignore($user?->id)],
            'phone'=>'nullable|string|max:30','date_of_birth'=>'nullable|date','gender'=>'nullable|in:Male,Female,Other','join_date'=>'nullable|date',
            'department_id'=>'nullable|exists:departments,id','unit_id'=>'nullable|exists:units,id','user_group_id'=>'nullable|exists:user_groups,id','job_title_id'=>'nullable|exists:job_titles,id','notes'=>'nullable|string|max:500','is_active'=>'boolean',
        ];
    }
}
