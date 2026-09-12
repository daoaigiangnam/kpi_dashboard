<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ItTools\AuditExcelService;
use App\Services\ItTools\BulkAuditService;
use App\Services\ItTools\InternetAssetAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItToolsController extends Controller
{
    public function index()
    {
        return view('admin.it-tools.check-domain');
    }

    public function audit(Request $request, InternetAssetAuditService $audit)
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253'],
            'wan_ip' => ['nullable', 'ip'],
            'dkim_selectors' => ['nullable', 'string', 'max:500'],
            'service_hosts' => ['nullable', 'string', 'max:1000'],
        ]);

        $selectors = collect(preg_split('/[,\s]+/', (string) ($data['dkim_selectors'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($v) => preg_replace('/[^a-z0-9._-]/i', '', $v))
            ->filter()->unique()->take(20)->values()->all();

        $serviceHosts = collect(preg_split('/[,\s]+/', (string) ($data['service_hosts'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($v) => preg_replace('/[^a-z0-9.-]/i', '', $v))
            ->filter()->unique()->take(50)->values()->all();

        return response()->json($audit->audit(
            $data['domain'], $data['wan_ip'] ?? null, $selectors, $serviceHosts
        ));
    }

    public function bulkAudit(Request $request, BulkAuditService $bulk)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.domain' => ['required', 'string', 'max:253'],
            'items.*.wan_ip' => ['nullable', 'ip'],
        ]);

        $request->headers->set('X-IT-Bulk-Audit', '1');
        return response()->json($bulk->audit($data['items'], 100));
    }

    public function export(Request $request, AuditExcelService $excel): StreamedResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:100'],
            'rows.*' => ['required', 'array'],
        ]);

        $filename = 'it-tools-check-domain-' . now()->format('Ymd-His') . '.xlsx';

        try {
            // Build the workbook from the rows currently displayed in the Check Domain UI.
            // Do not write to storage and do not read audit history/database records.
            $spreadsheet = $excel->outputRows($data['rows']);
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
        } catch (\Throwable $e) {
            Log::error('IT Tools Excel export failed', [
                'row_count' => count($data['rows']),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'memory' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ]);
            abort(500, 'IT Tools Excel export failed: ' . $e->getMessage());
        }

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
