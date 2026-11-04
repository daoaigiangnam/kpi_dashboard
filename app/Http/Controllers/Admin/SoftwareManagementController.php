<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SoftwareLicense;
use App\Models\SoftwareRequest;
use App\Models\SoftwareException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SoftwareManagementController extends Controller
{
    private const CLOSE_ITEMS = [
        'hod_approval' => 'Yêu cầu có đủ trường thông tin và HOD phê duyệt',
        'it_assessment' => 'IT đã hoàn thành đánh giá kỹ thuật/bảo mật/giấy phép',
        'branch_evidence' => 'Borrow: có căn cứ quyền hợp pháp; Buy: đủ phê duyệt mua sắm',
        'installation_scope' => 'Cài đặt/cấu hình đúng thiết bị và phạm vi',
        'security_check' => 'Đã kiểm tra hoạt động và cấu hình bảo mật',
        'user_acceptance' => 'Người dùng/HOD xác nhận hoặc lỗi còn lại được ghi rõ',
        'license_catalog' => 'Danh mục phần mềm/giấy phép đã cập nhật khi áp dụng',
        'attachments' => 'Hồ sơ đính kèm, lưu đúng kho và quyền truy cập phù hợp',
        'no_blocker' => 'Không còn điều kiện bắt buộc chưa xử lý',
    ];

    public function index(Request $request)
    {
        $query = SoftwareRequest::with(['requester','assignedIt'])->latest();
        if ($request->filled('search')) {
            $term = trim((string)$request->search);
            $query->where(fn($q) => $q->where('ticket_code','like',"%{$term}%")->orWhere('software_name','like',"%{$term}%")->orWhere('device_name','like',"%{$term}%"));
        }
        if ($request->filled('status')) $query->where('status',$request->status);
        if ($request->filled('branch_type')) $query->where('branch_type',$request->branch_type);
        $requests = $query->paginate(20)->withQueryString();
        $stats = [
            'total'=>SoftwareRequest::count(),
            'pending'=>SoftwareRequest::whereIn('status',['pending_hod','pending_it_review','pending_purchase_approval'])->count(),
            'installing'=>SoftwareRequest::whereIn('status',['approved_for_installation','installing','awaiting_acceptance'])->count(),
            'blocked'=>SoftwareRequest::whereIn('status',['returned','rejected','exception_pending'])->count(),
            'expiring'=>SoftwareLicense::whereNotNull('expires_at')->whereDate('expires_at','<=',now()->addDays(30))->count(),
        ];
        return view('admin.software-management.index',compact('requests','stats'));
    }

    public function create()
    {
        $users = User::where('is_active',true)->orderBy('name')->get(['id','name','email']);
        return view('admin.software-management.form',['softwareRequest'=>new SoftwareRequest(),'users'=>$users,'mode'=>'create']);
    }

    public function store(Request $request)
    {
        $data=$request->validate([
            'software_name'=>['required','string','max:180'],'vendor'=>['nullable','string','max:180'],'version'=>['nullable','string','max:100'],
            'department'=>['nullable','string','max:150'],'hod_id'=>['required','integer','exists:users,id'],'assigned_it_id'=>['nullable','integer','exists:users,id'],
            'device_name'=>['required','string','max:180'],'asset_code'=>['nullable','string','max:100'],'business_purpose'=>['required','string','max:5000'],
            'request_type'=>['required',Rule::in(['install','upgrade','remove'])],'branch_type'=>['required',Rule::in(['borrow','buy'])],
            'permission_type'=>['required',Rule::in(['readonly','edit','other'])],'data_access'=>['nullable','string','max:2000'],
            'needed_at'=>['nullable','date'],'usage_until'=>['nullable','date','after_or_equal:today'],'estimated_cost'=>['nullable','numeric','min:0'],
            'budget_source'=>['nullable','string','max:255'],
        ]);
        $data['ticket_code']='SW-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        $data['requester_id']=$request->user()->id;
        $data['created_by']=$request->user()->id;
        $data['status']='pending_hod';
        $data['hod_decision']='pending';
        $item=SoftwareRequest::create($data);
        $item->approvals()->create(['stage'=>'hod','decision'=>'pending']);
        return redirect()->route('admin.software_management.show',$item)->with('success','Đã tạo yêu cầu '.$item->ticket_code.'. Đang chờ HOD phê duyệt.');
    }

    public function show(SoftwareRequest $softwareRequest)
    {
        $softwareRequest->load(['requester','hod','assignedIt','approvals','assessments','installations','checklists','exceptions','attachments']);
        $users=User::where('is_active',true)->orderBy('name')->get(['id','name','email']);
        return view('admin.software-management.show',['item'=>$softwareRequest,'closeItems'=>self::CLOSE_ITEMS,'users'=>$users]);
    }

    public function decideHod(Request $request, SoftwareRequest $softwareRequest)
    {
        abort_unless($softwareRequest->status==='pending_hod',409,'Yêu cầu không ở trạng thái chờ HOD.');
        $data=$request->validate(['decision'=>['required',Rule::in(['approved','rejected','returned'])],'comment'=>['required','string','max:2000']]);
        abort_if($softwareRequest->requester_id===$request->user()->id,403,'Người yêu cầu không được tự phê duyệt yêu cầu của mình.');
        abort_unless($request->user()->id===$softwareRequest->hod_id || $request->user()->hasPermission('software.approve'),403,'Chỉ HOD được chỉ định hoặc người được cấp quyền mới có thể duyệt.');
        DB::transaction(function() use($softwareRequest,$request,$data){
            $softwareRequest->update(['hod_decision'=>$data['decision'],'hod_comment'=>$data['comment'],'status'=>$data['decision']==='approved'?'pending_it_review':($data['decision']==='returned'?'returned':'rejected')]);
            $softwareRequest->approvals()->create(['stage'=>'hod','decision'=>$data['decision'],'comment'=>$data['comment'],'approver_id'=>$request->user()->id,'decided_at'=>now()]);
        });
        return back()->with('success','Đã ghi nhận quyết định HOD.');
    }

    public function assess(Request $request, SoftwareRequest $softwareRequest)
    {
        abort_unless(in_array($softwareRequest->status,['pending_it_review','returned'],true),409,'Yêu cầu chưa đến bước đánh giá IT.');
        abort_unless($request->user()->hasPermission('software.assess'),403,'Bạn không có quyền đánh giá IT.');
        $data=$request->validate([
            'source_url'=>['nullable','url','max:2048'],'compatibility_result'=>['required',Rule::in(['pass','fail','clarify'])],
            'admin_rights_required'=>['required','boolean'],'data_access_scope'=>['nullable','string','max:2000'],
            'security_risk'=>['required',Rule::in(['accepted','mitigate','reject'])],'license_type'=>['nullable','string','max:150'],
            'license_quantity'=>['nullable','integer','min:1'],'license_until'=>['nullable','date'],'license_evidence'=>['nullable','string','max:2000'],
            'it_conclusion'=>['required',Rule::in(['approved','conditional','rejected','need_info'])],'conditions'=>['nullable','string','max:5000'],
            'notes'=>['nullable','string','max:5000'],'branch_type'=>['required',Rule::in(['borrow','buy'])],
            'assigned_it_id'=>['nullable','integer','exists:users,id'],
        ]);
        if ($data['it_conclusion']==='approved' && ($data['compatibility_result']!=='pass' || $data['security_risk']==='reject')) {
            return back()->withErrors(['it_conclusion'=>'Không thể chấp thuận khi tương thích chưa đạt hoặc rủi ro bảo mật bị từ chối.'])->withInput();
        }
        if ($data['it_conclusion']==='approved' && $data['branch_type']==='borrow' && blank($data['license_evidence'])) {
            return back()->withErrors(['license_evidence'=>'Nhánh Borrow phải ghi căn cứ/bằng chứng quyền sử dụng hợp pháp.'])->withInput();
        }
        $branch=$data['branch_type']; $assignee=$data['assigned_it_id']??$request->user()->id;
        unset($data['branch_type'],$data['assigned_it_id']);
        $data['assessor_id']=$request->user()->id; $data['assessed_at']=now();
        DB::transaction(function() use($softwareRequest,$data,$branch,$assignee){
            $softwareRequest->assessments()->create($data);
            $softwareRequest->update(['branch_type'=>$branch,'assigned_it_id'=>$assignee,'it_conclusion'=>$data['it_conclusion'],'it_assessment'=>$data['notes']??null,
                'status'=>$data['it_conclusion']==='approved'?($branch==='buy'?'pending_purchase_approval':'approved_for_installation'):($data['it_conclusion']==='conditional'?'pending_it_review':($data['it_conclusion']==='need_info'?'returned':'rejected'))]);
        });
        return back()->with('success','Đã lưu đánh giá kỹ thuật/bảo mật/giấy phép.');
    }

    public function decidePurchase(Request $request, SoftwareRequest $softwareRequest)
    {
        abort_unless($softwareRequest->status==='pending_purchase_approval',409,'Yêu cầu chưa chờ phê duyệt mua sắm.');
        abort_unless($request->user()->hasPermission('software.purchase_approve'),403,'Bạn không có quyền phê duyệt mua sắm.');
        $data=$request->validate(['decision'=>['required',Rule::in(['approved','rejected','returned'])],'comment'=>['required','string','max:2000'],'approved_cost'=>['nullable','numeric','min:0']]);
        abort_if($softwareRequest->requester_id===$request->user()->id,403,'Người yêu cầu không được tự duyệt yêu cầu của mình.');
        $softwareRequest->approvals()->create(['stage'=>'purchase','decision'=>$data['decision'],'comment'=>$data['comment'],'approver_id'=>$request->user()->id,'decided_at'=>now(),'metadata'=>['approved_cost'=>$data['approved_cost']??null]]);
        $softwareRequest->update(['status'=>$data['decision']==='approved'?'approved_for_installation':($data['decision']==='returned'?'returned':'rejected')]);
        return back()->with('success','Đã ghi nhận quyết định mua sắm.');
    }

    public function install(Request $request, SoftwareRequest $softwareRequest)
    {
        abort_unless(in_array($softwareRequest->status,['approved_for_installation','installing'],true),409,'Chưa có đủ điều kiện phê duyệt để cài đặt.');
        abort_unless($request->user()->hasPermission('software.install'),403,'Bạn không có quyền ghi nhận thao tác cài đặt.');
        $data=$request->validate([
            'started_at'=>['required','date'],'finished_at'=>['nullable','date','after_or_equal:started_at'],
            'result'=>['required',Rule::in(['success','failed','conditional'])],'functionality_check'=>['required',Rule::in(['pass','fail'])],
            'security_check'=>['required',Rule::in(['pass','fail'])],'readonly_check'=>['required',Rule::in(['pass','fail','na'])],
            'issues'=>['nullable','string','max:5000'],'rollback_plan'=>['nullable','string','max:3000'],'notes'=>['nullable','string','max:3000'],
        ]);
        $data['technician_id']=$request->user()->id;
        $softwareRequest->installations()->create($data);
        $softwareRequest->update(['status'=>'awaiting_acceptance','installation_result'=>$data['result'],'installed_at'=>$data['finished_at']??null]);
        return back()->with('success','Đã lưu kết quả cài đặt. Ticket chuyển sang chờ người dùng nghiệm thu.');
    }

    public function accept(Request $request, SoftwareRequest $softwareRequest)
    {
        abort_unless($softwareRequest->status==='awaiting_acceptance',409,'Yêu cầu chưa chờ nghiệm thu.');
        $data=$request->validate(['acceptance'=>['required',Rule::in(['accepted','not_accepted'])],'comment'=>['nullable','string','max:3000']]);
        $install=$softwareRequest->installations()->latest('id')->firstOrFail();
        $install->update(['user_acceptance'=>$data['acceptance'],'user_acceptance_by'=>$request->user()->id,'user_accepted_at'=>now(),'notes'=>trim(($install->notes??'')."\n".$data['comment'])]);
        $softwareRequest->update(['accepted_at'=>$data['acceptance']==='accepted'?now():null,'status'=>$data['acceptance']==='accepted'?'pending_closure':'installing']);
        return back()->with('success','Đã ghi nhận kết quả nghiệm thu.');
    }

    public function saveChecklist(Request $request, SoftwareRequest $softwareRequest)
    {
        abort_unless($softwareRequest->status==='pending_closure',409,'Checklist chỉ thực hiện sau khi có kết quả nghiệm thu đạt.');
        abort_unless($request->user()->hasPermission('software.close'),403,'Bạn không có quyền kiểm tra checklist đóng Ticket.');
        $data=$request->validate(['items'=>['required','array'],'items.*.passed'=>['required','boolean'],'items.*.evidence'=>['nullable','string','max:2000']]);
        foreach(self::CLOSE_ITEMS as $key=>$label) if(!array_key_exists($key,$data['items'])) abort(422,'Thiếu mục checklist: '.$label);
        DB::transaction(function() use($request,$softwareRequest,$data){
            foreach(self::CLOSE_ITEMS as $key=>$label) $softwareRequest->checklists()->updateOrCreate(['item_key'=>$key],['item_label'=>$label,'passed'=>(bool)$data['items'][$key]['passed'],'evidence'=>$data['items'][$key]['evidence']??null,'checked_by'=>$request->user()->id,'checked_at'=>now()]);
        });
        return back()->with('success','Đã lưu checklist. Tiếp tục kiểm tra các điều kiện bắt buộc trước khi đóng.');
    }

    public function close(Request $request, SoftwareRequest $softwareRequest)
    {
        abort_unless($request->user()->hasPermission('software.close'),403,'Bạn không có quyền đóng Ticket.');
        abort_unless($softwareRequest->status==='pending_closure',409,'Ticket chưa ở trạng thái có thể đóng.');
        $softwareRequest->load(['checklists','assessments','installations']);
        $assessment=$softwareRequest->assessments->last(); $installation=$softwareRequest->installations->last();
        $checklist=$softwareRequest->checklists->keyBy('item_key'); $missing=[];
        foreach(self::CLOSE_ITEMS as $key=>$label) if(!$checklist->has($key)||!$checklist[$key]->passed) $missing[]=$label;
        if(!$assessment||!in_array($assessment->it_conclusion,['approved','conditional'],true)) $missing[]='Đánh giá IT hợp lệ';
        if(!$installation||$installation->user_acceptance!=='accepted'||$installation->functionality_check!=='pass'||$installation->security_check!=='pass') $missing[]='Kiểm tra cài đặt và nghiệm thu đạt';
        if($softwareRequest->branch_type==='borrow'&&blank($assessment?->license_evidence)) $missing[]='Bằng chứng quyền sử dụng hợp pháp (Borrow)';
        if($softwareRequest->branch_type==='buy'&&!$softwareRequest->approvals()->where('stage','purchase')->where('decision','approved')->exists()) $missing[]='Phê duyệt mua sắm (Buy)';
        if($missing) return back()->withErrors(['close'=>'Không thể đóng Ticket. Còn thiếu: '.implode('; ',$missing)]);
        $softwareRequest->update(['status'=>'closed','closed_at'=>now()]);
        return back()->with('success','Ticket đã được đóng sau khi kiểm tra checklist.');
    }

    public function addException(Request $request, SoftwareRequest $softwareRequest)
    {
        abort_unless($request->user()->hasPermission('software.exception.create'),403,'Bạn không có quyền tạo phiếu ngoại lệ.');
        $data=$request->validate([
            'description'=>['required','string','max:3000'],'business_reason'=>['required','string','max:3000'],'risk_impact'=>['required','string','max:3000'],
            'scope'=>['required','string','max:2000'],'mitigation'=>['required','string','max:3000'],'rollback_plan'=>['nullable','string','max:3000'],
            'expires_at'=>['required','date','after:today'],'owner_id'=>['required','integer','exists:users,id'],'it_opinion'=>['required','string','max:3000'],
        ]);
        $data['approval_status']='pending'; $data['created_by']=$request->user()->id;
        $softwareRequest->exceptions()->create($data); $softwareRequest->update(['status'=>'exception_pending']);
        return back()->with('success','Đã gửi phiếu ngoại lệ chờ phê duyệt.');
    }

    public function decideException(Request $request, SoftwareException $softwareException)
    {
        abort_unless($request->user()->hasPermission('software.exception.approve'),403,'Bạn không có quyền phê duyệt ngoại lệ.');
        $data=$request->validate(['decision'=>['required',Rule::in(['approved','rejected'])],'comment'=>['required','string','max:2000']]);
        abort_unless($softwareException->approval_status==='pending',409,'Ngoại lệ đã được xử lý.');
        $softwareException->update(['approval_status'=>$data['decision'],'approver_id'=>$request->user()->id,'approved_at'=>now(),'it_opinion'=>trim($softwareException->it_opinion."\n".$data['comment'])]);
        if($data['decision']==='approved') $softwareException->request()->where('status','exception_pending')->update(['status'=>'pending_it_review']);
        return back()->with('success','Đã ghi nhận quyết định ngoại lệ.');
    }

    public function storeLicense(Request $request)
    {
        abort_unless($request->user()->hasPermission('software.licenses.manage'),403,'Bạn không có quyền quản lý danh mục giấy phép.');
        $data=$request->validate([
            'software_name'=>['required','string','max:180'],'vendor'=>['nullable','string','max:180'],'version'=>['nullable','string','max:100'],
            'license_type'=>['required','string','max:150'],'quantity'=>['required','integer','min:1'],'assigned_user'=>['nullable','string','max:180'],
            'device_name'=>['nullable','string','max:180'],'asset_code'=>['nullable','string','max:100'],'installed_at'=>['nullable','date'],
            'expires_at'=>['nullable','date'],'ticket_code'=>['nullable','string','max:40'],'status'=>['required',Rule::in(['active','expired','revoked','pending'])],
            'notes'=>['nullable','string','max:3000'],
        ]);
        SoftwareLicense::create($data);
        return back()->with('success','Đã thêm mục vào danh mục phần mềm và giấy phép.');
    }

    public function licenses(Request $request)
    {
        abort_unless($request->user()->hasPermission('software.licenses.view'),403,'Bạn không có quyền xem danh mục giấy phép.');
        $licenses=SoftwareLicense::when($request->filled('search'),function($q) use($request){$term=trim($request->search);$q->where(fn($x)=>$x->where('software_name','like',"%{$term}%")->orWhere('vendor','like',"%{$term}%"));})
            ->orderByRaw('expires_at IS NULL, expires_at ASC')->paginate(20)->withQueryString();
        return view('admin.software-management.licenses',compact('licenses'));
    }

    public function upload(Request $request, SoftwareRequest $softwareRequest)
    {
        abort_unless($request->user()->hasPermission('software.attachments.manage'),403,'Bạn không có quyền tải tệp lên.');
        $data=$request->validate(['category'=>['required',Rule::in(['assessment','quote','purchase','license','acceptance','exception','other'])],'file'=>['required','file','max:10240','mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg']]);
        $file=$data['file']; $path=$file->store('software-management/'.$softwareRequest->id,'local');
        $softwareRequest->attachments()->create(['category'=>$data['category'],'original_name'=>$file->getClientOriginalName(),'path'=>$path,'mime_type'=>$file->getMimeType(),'size_bytes'=>$file->getSize(),'uploaded_by'=>$request->user()->id]);
        return back()->with('success','Đã lưu tệp đính kèm trong vùng lưu trữ riêng.');
    }

    public function download(Request $request, \App\Models\SoftwareAttachment $softwareAttachment)
    {
        abort_unless($request->user()->hasPermission('software.attachments.view'),403,'Bạn không có quyền tải tệp.');
        abort_unless(Storage::disk('local')->exists($softwareAttachment->path),404);
        return Storage::disk('local')->download($softwareAttachment->path,$softwareAttachment->original_name);
    }
}