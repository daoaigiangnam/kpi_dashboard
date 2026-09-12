<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ItTools\ApiTesterService;
use App\Services\ItTools\AuditExcelService;
use App\Services\ItTools\BulkAuditService;
use App\Services\ItTools\InternetAssetAuditService;
use App\Services\ItTools\IpScannerService;
use App\Services\ItTools\NetworkDiagnosticService;
use App\Services\ItTools\PortCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItToolsController extends Controller
{
    public function index() { return view('admin.it-tools.check-domain'); }
    public function portCheckPage() { return view('admin.it-tools.port-check'); }
    public function apiTesterPage() { return view('admin.it-tools.api-tester'); }
    public function ipScannerPage() { return view('admin.it-tools.ip-scanner'); }
    public function networkDiagnosticPage() { return view('admin.it-tools.network-diagnostic'); }

    public function portCheck(Request $request, PortCheckService $ports)
    {
        $data = $request->validate(['host'=>['required','string','max:253'],'ports'=>['required','array','min:1','max:30'],'ports.*'=>['integer','between:1,65535']]);
        $portList = collect($data['ports'])->map(fn($port)=>(int)$port)->unique()->take(30)->values()->all();
        return response()->json($ports->check($data['host'],$portList));
    }

    public function apiTester(Request $request, ApiTesterService $api)
    {
        $data = $request->validate(['url'=>['required','url','max:2048'],'method'=>['required','in:GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS'],'query'=>['nullable','array','max:50'],'headers'=>['nullable','array','max:50'],'headers.*'=>['nullable','string','max:2000'],'auth'=>['nullable','array'],'body_type'=>['nullable','in:none,json,form,raw'],'body'=>['nullable','string','max:1000000'],'timeout'=>['nullable','integer','between:1,60'],'verify_ssl'=>['nullable','boolean'],'follow_redirects'=>['nullable','boolean']]);
        return response()->json($api->send($data));
    }

    public function ipScanner(Request $request, IpScannerService $scanner)
    {
        $data = $request->validate(['range'=>['required','string','max:64'],'ports'=>['nullable','array','max:50'],'ports.*'=>['integer','between:1,65535'],'all_ports'=>['nullable','boolean']]);
        try { return response()->json($scanner->scan($data['range'], $data['ports'] ?? [], (bool)($data['all_ports'] ?? false))); }
        catch (\InvalidArgumentException $e) { return response()->json(['message'=>$e->getMessage()], 422); }
    }

    public function networkDiagnostic(Request $request, NetworkDiagnosticService $network)
    {
        $data = $request->validate(['mode'=>['required','in:ping,trace'],'host'=>['required','string','max:253'],'count'=>['nullable','integer','between:1,64']]);
        return response()->json($data['mode'] === 'trace' ? $network->traceroute($data['host'], $data['count'] ?? 30) : $network->ping($data['host'], $data['count'] ?? 4));
    }

    public function audit(Request $request, InternetAssetAuditService $audit)
    {
        $data = $request->validate(['domain'=>['required','string','max:253'],'wan_ip'=>['nullable','ip'],'dkim_selectors'=>['nullable','string','max:500'],'service_hosts'=>['nullable','string','max:1000']]);
        $selectors=collect(preg_split('/[,\s]+/',(string)($data['dkim_selectors']??''),-1,PREG_SPLIT_NO_EMPTY))->map(fn($v)=>preg_replace('/[^a-z0-9._-]/i','',$v))->filter()->unique()->take(20)->values()->all();
        $serviceHosts=collect(preg_split('/[,\s]+/',(string)($data['service_hosts']??''),-1,PREG_SPLIT_NO_EMPTY))->map(fn($v)=>preg_replace('/[^a-z0-9.-]/i','',$v))->filter()->unique()->take(50)->values()->all();
        return response()->json($audit->audit($data['domain'],$data['wan_ip']??null,$selectors,$serviceHosts));
    }

    public function bulkAudit(Request $request, BulkAuditService $bulk)
    {
        $data=$request->validate(['items'=>['required','array','min:1','max:100'],'items.*.domain'=>['required','string','max:253'],'items.*.wan_ip'=>['nullable','ip']]);
        $request->headers->set('X-IT-Bulk-Audit','1'); return response()->json($bulk->audit($data['items'],100));
    }

    public function export(Request $request, AuditExcelService $excel): StreamedResponse
    {
        $data=$request->validate(['rows'=>['required','array','min:1','max:100'],'rows.*'=>['required','array']]); $filename='it-tools-check-domain-'.now()->format('Ymd-His').'.xlsx';
        try{$spreadsheet=$excel->outputRows($data['rows']);$writer=new Xlsx($spreadsheet);$writer->setPreCalculateFormulas(false);}catch(\Throwable $e){Log::error('IT Tools Excel export failed',['row_count'=>count($data['rows']),'exception'=>get_class($e),'message'=>$e->getMessage(),'memory'=>memory_get_usage(true),'peak_memory'=>memory_get_peak_usage(true)]);abort(500,'IT Tools Excel export failed: '.$e->getMessage());}
        return response()->streamDownload(fn()=>$writer->save('php://output'),$filename,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Cache-Control'=>'no-store, no-cache, must-revalidate','Pragma'=>'no-cache']);
    }
}
