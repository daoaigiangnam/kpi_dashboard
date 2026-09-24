<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PcAudit;
use App\Models\ServiceCustomer;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PcAuditController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $customerId = $request->query('customer_id');
        $customers = ServiceCustomer::query()->orderBy('name')->get(['id','code','name']);
        $audits = PcAudit::query()->with(['auditCode.branch.customer'])
            ->when($customerId, fn($q) => $q->whereHas('auditCode.branch', fn($b) => $b->where('customer_id',$customerId)))
            ->when($search !== '', function($q) use ($search) {
                $like = "%{$search}%";
                $q->where(function($x) use ($like) {
                    $x->where('computer_name','like',$like)->orWhere('serial_number','like',$like)->orWhere('employee_name','like',$like)->orWhere('department','like',$like)->orWhere('manufacturer','like',$like)->orWhere('model','like',$like)->orWhereHas('auditCode',fn($c)=>$c->where('code','like',$like));
                });
            })->latest('collected_at')->paginate(25)->withQueryString();
        return view('admin.pc-audit.index', compact('audits','search','customerId','customers'));
    }

    public function show(PcAudit $pcAudit)
    {
        $pcAudit->load(['auditCode.branch.customer','details','memory','storage','monitors','gpu','batteries','network','antivirus','bitlocker','firewall','licenses','software']);
        return view('admin.pc-audit.show',['audit'=>$pcAudit]);
    }

    public function export(Request $request): StreamedResponse
    {
        $ids=collect($request->input('ids',[]))->map(fn($id)=>(int)$id)->filter(fn($id)=>$id>0)->unique()->take(200)->values();
        abort_if($ids->isEmpty(),422,'Chưa chọn máy để xuất Excel.');
        $audits=PcAudit::with(['auditCode.branch.customer','details','memory','storage','monitors','gpu','batteries','network','antivirus','bitlocker','firewall','licenses','software'])->whereIn('id',$ids)->get();
        $spreadsheet=new Spreadsheet(); $spreadsheet->removeSheetByIndex(0);
        foreach($audits as $audit){
            $sheet=$spreadsheet->createSheet();
            $name=substr(preg_replace('/[^A-Za-z0-9_-]/','_',(string)($audit->computer_name?:'PC-'.$audit->id)),0,28); $sheet->setTitle($name.'-'.$audit->id);
            $rows=[['PC AUDIT',''],['Customer',$audit->auditCode?->branch?->customer?->name],['Branch',$audit->auditCode?->branch?->name],['Audit Code',$audit->auditCode?->code],['Department',$audit->department],['Employee',$audit->employee_name],['Username',$audit->employee_username],['Domain',$audit->domain],['Computer Name',$audit->computer_name],['Manufacturer',$audit->manufacturer],['Model',$audit->model],['Serial Number',$audit->serial_number],['Asset Tag',$audit->asset_tag],['Operating System',json_encode($audit->operating_system,JSON_UNESCAPED_UNICODE)],['Mainboard',json_encode($audit->mainboard,JSON_UNESCAPED_UNICODE)],['BIOS',json_encode($audit->bios,JSON_UNESCAPED_UNICODE)],['Windows Update',json_encode($audit->windows_update,JSON_UNESCAPED_UNICODE)],['Last Boot',$audit->last_boot],['Uptime',$audit->uptime],['TPM',json_encode($audit->tpm,JSON_UNESCAPED_UNICODE)],['Secure Boot',json_encode($audit->secure_boot,JSON_UNESCAPED_UNICODE)],['Collected At',optional($audit->collected_at)->format('Y-m-d H:i:s')]];
            foreach($rows as $row)$sheet->fromArray($row,null,'A'.($sheet->getHighestRow()+1));
            $sections=['RAM'=>$audit->memory->toArray(),'Storage'=>$audit->storage->toArray(),'Monitors'=>$audit->monitors->toArray(),'GPU'=>$audit->gpu->toArray(),'Battery'=>$audit->batteries->toArray(),'Network'=>$audit->network->toArray(),'Antivirus'=>$audit->antivirus->toArray(),'BitLocker'=>$audit->bitlocker->toArray(),'Firewall'=>$audit->firewall->toArray(),'Licenses'=>$audit->licenses->toArray(),'Software'=>$audit->software->toArray()];
            $start=$sheet->getHighestRow()+2;
            foreach($sections as $title=>$items){$sheet->setCellValue('A'.$start,$title);$start++;if($items){$headers=array_keys($items[0]);$sheet->fromArray($headers,null,'A'.$start);$start++;foreach($items as $item){unset($item['id'],$item['pc_audit_id'],$item['created_at'],$item['updated_at']);$sheet->fromArray(array_values($item),null,'A'.$start);$start++;}}else{$sheet->setCellValue('A'.$start,'No data');$start++;}$start++;}
            $sheet->getColumnDimension('A')->setWidth(28);$sheet->getColumnDimension('B')->setWidth(45);$sheet->freezePane('A2');
        }
        $writer=new Xlsx($spreadsheet);
        return response()->streamDownload(fn()=>$writer->save('php://output'),'PC_Audit_'.now()->format('Ymd-His').'.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Cache-Control'=>'no-store, no-cache, must-revalidate','Pragma'=>'no-cache']);
    }
}
