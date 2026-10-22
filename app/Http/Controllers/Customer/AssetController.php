<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\PcAudit;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isCustomerAccount() && ($user->hasPermission('customer.assets.view') || $user->hasPermission('customer.audit.view')), 403);
        abort_unless($user->service_customer_id, 403);

        $assets = PcAudit::query()
            ->whereHas('auditCode.branch', fn ($q) => $q->where('customer_id', $user->service_customer_id))
            ->when(!$user->hasPermission('customer.assets.view'), fn ($q) => $q->whereRaw('1 = 0'))
            ->with(['auditCode.branch.customer'])
            ->when(trim((string) $request->query('q')) !== '', function ($q) use ($request): void {
                $term = '%' . addcslashes(trim((string) $request->query('q')), '%_') . '%';
                $q->where(function ($x) use ($term): void {
                    $x->where('computer_name', 'like', $term)
                        ->orWhere('asset_tag', 'like', $term)
                        ->orWhere('serial_number', 'like', $term)
                        ->orWhere('employee_name', 'like', $term);
                });
            })
            ->latest('collected_at')
            ->paginate(25)
            ->withQueryString();

        return view('customer.assets.index', compact('assets'));
    }

    public function show(Request $request, string $token)
    {
        $user = $request->user();
        abort_unless($user?->isCustomerAccount(), 403);
        abort_unless($user->hasPermission('customer.assets.view') || $user->hasPermission('customer.audit.view'), 403);
        abort_unless($user->service_customer_id, 403);

        $asset = PcAudit::query()
            ->where('asset_lookup_token', $token)
            ->whereHas('auditCode.branch', fn ($q) => $q->where('customer_id', $user->service_customer_id))
            ->when(!$user->hasPermission('customer.assets.view') && !$user->hasPermission('customer.audit.view'), fn ($q) => $q->whereRaw('1 = 0'))
            ->with([
                'auditCode.branch.customer', 'details', 'memory', 'storage', 'monitors', 'gpu',
                'batteries', 'network', 'antivirus', 'bitlocker', 'firewall', 'licenses', 'software',
            ])
            ->firstOrFail();

        return view('customer.assets.show', compact('asset'));
    }

    public function qr(Request $request, string $token)
    {
        $user = $request->user();
        abort_unless($user?->isCustomerAccount() && $user->hasPermission('customer.assets.qr'), 403);
        abort_unless($user->service_customer_id, 403);

        $asset = PcAudit::query()
            ->where('asset_lookup_token', $token)
            ->whereHas('auditCode.branch', fn ($q) => $q->where('customer_id', $user->service_customer_id))
            ->with('auditCode.branch.customer')
            ->firstOrFail();

        $url = route('customer.assets.show', ['token' => $asset->asset_lookup_token]);
        return view('customer.assets.qr', compact('asset', 'url'));
    }
}
