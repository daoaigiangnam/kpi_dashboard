<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItToolAudit;
use App\Services\ItTools\BulkAuditService;
use App\Services\ItTools\InternetAssetAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                'status' => 'error',
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

        return response()->json($bulk->audit($data['items'], 100));
    }

    public function history(Request $request)
    {
        $query = ItToolAudit::query()->latest();
        if ($request->filled('domain')) {
            $query->where('domain', 'like', '%' . trim($request->string('domain')) . '%');
        }
        return response()->json($query->paginate(min((int) $request->input('per_page', 50), 100)));
    }
}
