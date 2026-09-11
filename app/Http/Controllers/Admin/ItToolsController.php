<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItToolAudit;
use App\Services\ItTools\BulkAuditService;
use App\Services\ItTools\InternetAssetAuditService;
use Illuminate\Http\Request;

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
        ]);

        $selectors = collect(preg_split('/[,\s]+/', (string) ($data['dkim_selectors'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($value) => preg_replace('/[^a-z0-9._-]/i', '', $value))
            ->filter()->unique()->take(20)->values()->all();

        $started = microtime(true);
        try {
            $result = $audit->audit($data['domain'], $data['wan_ip'] ?? null, $selectors);
            ItToolAudit::create([
                'user_id' => auth()->id(),
                'domain' => $data['domain'],
                'wan_ip' => $data['wan_ip'] ?? null,
                'status' => 'completed',
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'result' => $result,
            ]);
            return response()->json($result);
        } catch (\Throwable $e) {
            ItToolAudit::create([
                'user_id' => auth()->id(),
                'domain' => $data['domain'],
                'wan_ip' => $data['wan_ip'] ?? null,
                'status' => 'failed',
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'error' => $e->getMessage(),
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

        $started = microtime(true);
        $results = $bulk->audit($data['items'], 100);
        foreach ($results as $item) {
            ItToolAudit::create([
                'user_id' => auth()->id(),
                'domain' => $item['domain'] ?? 'unknown',
                'wan_ip' => $item['wan_ip'] ?? null,
                'status' => ($item['status'] ?? 'completed') === 'failed' ? 'failed' : 'completed',
                'duration_ms' => $item['duration_ms'] ?? null,
                'result' => $item,
                'error' => $item['error'] ?? null,
            ]);
        }

        return response()->json([
            'count' => count($results),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'results' => $results,
        ]);
    }

    public function history(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 25), 100));
        return response()->json(
            ItToolAudit::query()->with('user:id,name')->latest()->paginate($perPage)
        );
    }
}
