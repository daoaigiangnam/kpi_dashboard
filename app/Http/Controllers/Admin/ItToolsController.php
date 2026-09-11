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
        ]);

        return response()->json($audit->audit($data['domain'], $data['wan_ip'] ?? null));
    }
}
