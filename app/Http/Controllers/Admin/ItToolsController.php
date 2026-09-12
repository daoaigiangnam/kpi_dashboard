<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItToolAudit;
use App\Services\ItTools\AuditExcelService;
use App\Services\ItTools\BulkAuditImportService;
use App\Services\ItTools\BulkAuditService;
use App\Services\ItTools\InternetAssetAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItToolsController extends Controller
{
    public function index()
    {
        return view('admin.it-tools.index');
    }

    public function audit(Request $request, InternetAssetAuditService $audit)
    {
        $data = $request->validate([
            'domain' => ['required','string','max:253'],
            'wan_ip' => ['nullable','ip'],
            'dkim_selectors' => ['nullable','string','max:500'],
            'service_hosts' => ['nullable','string','max:1000'],
        ]);

        $selectors = collect(preg_split('/[,\s]+/', (string) ($data['dkim_selectors'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($value) => preg_replace('/[^a-z0-9._-]/i', '', $value))
            ->filter()->unique()->take(20)->values()->all();

        $serviceHosts = collect(preg_split('/[,\s]+/', (string) ($data['service_hosts'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($value) => preg_replace('/[^a-z0-9.-]/i', '', $value))
            ->filter()->unique()->take(50)->values()->all();

        $started = microtime(true);
        try {
            $result = $audit->audit($data['domain'], $data['wan_ip'] ?? null, $selectors, $serviceHosts);
            ItToolAudit::create([
                'user_id' => auth()->id(), 'domain' => $data['domain'], 'wan_ip' => $data['wan_ip'] ?? null,
                'status' => 'completed', 'duration_ms' => (int) round((microtime(true) - $started) * 1000), 'result' => $result,
            ]);
            return response()->json($result);
        } catch (\Throwable $e) {
            ItToolAudit::create([
                'user_id' => auth()->id(), 'domain' => $data['domain'], 'wan_ip' => $data['wan_ip'] ?? null,
                'status' => 'error', 'duration_ms' => (int) round((microtime(true) - $started) * 1000), 'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function bulkAudit(Request $request, BulkAuditService $bulk)
    {
        $data = $request->validate([
            'items' => ['required','array','min:1','max:100'],
            'items.*.domain' => ['required','string','max:253'],
            'items.*.wan_ip' => ['nullable','ip'],
        ]);
        return response()->json($bulk->audit($data['items'], 100));
    }

    public function importBulk(Request $request, BulkAuditImportService $importer)
    {
        $data = $request->validate(['file' => ['required','file','max:5120','mimes:xlsx,xls,csv,txt']]);
        return response()->json($importer->import($data['file']->getRealPath(), 100));
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Domain', 'WAN IP'], ['example.com', '1.2.3.4'], ['example.vn', '']]);
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'it-tools-bulk-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'no-store',
        ]);
    }

    public function history(Request $request)
    {
        $query = ItToolAudit::query()->latest();
        if ($request->filled('domain')) $query->where('domain', 'like', '%' . trim($request->string('domain')) . '%');
        if ($request->expectsJson()) return response()->json($query->paginate(min((int) $request->input('per_page', 50), 100)));
        return view('admin.it-tools.history', ['audits' => $query->paginate(50)]);
    }

    public function export(Request $request, AuditExcelService $excel)
    {
        $domain = $request->input('domain');
        $filename = 'it-tool-audits-' . now()->format('Ymd-His') . '.xlsx';
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'it-tools-exports';
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        try {
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new \RuntimeException('Unable to create IT Tools export directory.');
            }
            if (! is_writable($directory)) {
                throw new \RuntimeException('IT Tools export directory is not writable.');
            }

            $limit = min(max((int) $request->input('limit', 1000), 1), 1000);
            $excel->setExportLimit($limit);
            $writer = $excel->output($domain);
            $writer->setPreCalculateFormulas(false);
            $writer->save($path);

            if (! is_file($path) || filesize($path) < 100) {
                throw new \RuntimeException('Excel file was not generated correctly.');
            }

            return response()->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Length' => (string) filesize($path),
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if (is_file($path)) @unlink($path);
            Log::error('IT Tools Excel export failed', [
                'domain' => $domain,
                'limit' => $request->input('limit', 1000),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'memory' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ]);

            abort(500, 'IT Tools Excel export failed: ' . $e->getMessage());
        }
    }
}
