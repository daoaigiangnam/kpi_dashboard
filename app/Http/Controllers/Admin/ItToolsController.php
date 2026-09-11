<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        return response()->json($audit->audit(
            $data['domain'],
            $data['wan_ip'] ?? null,
            $selectors,
        ));
    }
}
