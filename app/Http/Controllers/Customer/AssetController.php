<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerBranch;
use App\Models\PcAudit;
use App\Models\PcAuditCode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    private function scopedAssets($user)
    {
        abort_unless($user?->isCustomerAccount() && $user->service_customer_id, 403);

        return PcAudit::query()->whereHas('auditCode.branch', function ($branchQuery) use ($user): void {
            $branchQuery->where('customer_id', $user->service_customer_id);
            if (!$user->can_access_all_branches) {
                $branchQuery->whereIn('customer_branches.id', $user->permittedBranches()->select('customer_branches.id'));
            }
        });
    }

    private function filteredAssets(Request $request, $user)
    {
        return $this->scopedAssets($user)
            ->when(trim((string)$request->query('q')) !== '', function ($q) use ($request): void {
                $term = '%' . addcslashes(trim((string)$request->query('q')), '%_') . '%';
                $q->where(function ($x) use ($term): void {
                    $x->where('computer_name', 'like', $term)
                        ->orWhere('asset_tag', 'like', $term)
                        ->orWhere('serial_number', 'like', $term)
                        ->orWhere('employee_name', 'like', $term)
                        ->orWhere('manufacturer', 'like', $term)
                        ->orWhere('model', 'like', $term);
                });
            })
            ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('auditCode.branch', fn ($b) => $b->whereKey($request->integer('branch_id'))))
            ->when($request->filled('status'), fn ($q) => $q->where('audit_status', $request->query('status')))
            ->when($request->filled('manufacturer'), fn ($q) => $q->where('manufacturer', $request->query('manufacturer')));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.view'), 403);
        $branches = CustomerBranch::query()->where('customer_id', $user->service_customer_id)
            ->where('is_active', true)
            ->where(function ($q) use ($user): void {
                if (!$user->can_access_all_branches) {
                    $q->whereIn('id', $user->permittedBranches()->select('customer_branches.id'));
                }
            })->orderBy('name')->get(['id', 'name', 'code']);

        $assets = $this->filteredAssets($request, $user)
            ->with(['auditCode.branch.customer'])
            ->latest('collected_at')->latest('id')->paginate(25)->withQueryString();

        $statuses = ['PASS', 'FAIL', 'REVIEW'];
        $manufacturers = $this->scopedAssets($user)->whereNotNull('manufacturer')->where('manufacturer', '<>', '')
            ->distinct()->orderBy('manufacturer')->pluck('manufacturer');

        return view('customer.assets.index', compact('assets', 'branches', 'statuses', 'manufacturers'));
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.export'), 403);
        $allFiltered = $request->boolean('all_filtered');
        $ids = collect($request->input('ids', []))->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->take(5000)->values();
        abort_if(!$allFiltered && $ids->isEmpty(), 422, 'Hãy chọn tài sản hoặc chọn xuất toàn bộ kết quả theo bộ lọc.');

        $query = $allFiltered ? $this->filteredAssets($request, $user) : $this->scopedAssets($user)->whereIn('id', $ids);
        $assets = $query->with(['auditCode.branch.customer','details','memory','storage','monitors','gpu','batteries','network','antivirus','bitlocker','firewall','licenses','software'])->orderBy('id')->get();
        abort_if($assets->isEmpty(), 404, 'Không có tài sản phù hợp để xuất.');

        $text = static function ($v): string {
            if (is_array($v) || is_object($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $v = $v === null ? '' : (string) $v;
            return preg_match('/^[=+@\\-\\t\\r]/', $v) ? "'".$v : $v;
        };
        $many = static function ($items): string {
            return $items->map(fn ($item) => collect($item->getAttributes())->except(['id','pc_audit_id','created_at','updated_at'])->map(fn ($v,$k) => str_replace('_',' ',$k).': '.(is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v))->implode(' | '))->filter()->implode("\n");
        };
        return response()->streamDownload(function () use ($assets, $text, $many): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Khách hàng','Chi nhánh','Phòng ban','Họ Tên','Di động','Email','Username','Domain','Tên máy tính','Manufacturer','Model','Serial Number','Asset Tag','Mainboard','BIOS','CPU','RAM','HDD','Monitor','VGA','Battery','OS','Windows Update','Last Boot','Uptime','LAN','WIFI','MODEM','IP','MAC','Gateway','DNS','DHCP','Connection Status','Link Speed','Antivirus','BitLocker','Firewall','TPM','Secure Boot','Windows Activation','Office Activation','Ngày thu thập','SOFTWARE']);
            foreach ($assets as $x) {
                $net = $x->network;
                $netText = fn ($type) => $net->filter(fn ($n) => strtoupper((string)($n->type ?? '')) === $type)->map(fn ($n) => $many(collect([$n])))->filter()->implode("\n");
                $field = fn ($items, $key) => $items->map(fn ($n) => $n->{$key} ?? null)->filter(fn ($v) => $v !== null && $v !== '')->unique()->implode("\n");
                $licenses = $x->licenses;
                $values = [
                    $x->auditCode?->branch?->customer?->name, $x->auditCode?->branch?->name, $x->department,
                    $x->employee_name, $x->employee_mobile, $x->employee_email, $x->employee_username, $x->domain,
                    $x->computer_name, $x->manufacturer, $x->model, $x->serial_number, $x->asset_tag,
                    $x->mainboard ?: ($x->details?->hardware ?? null), $x->bios, $x->details?->cpu, $many($x->memory),
                    $many($x->storage), $many($x->monitors), $many($x->gpu), $many($x->batteries),
                    $x->operating_system, $x->windows_update, $x->last_boot, $x->uptime,
                    $netText('LAN'), $netText('WIFI'), $netText('MODEM'), $field($net,'ipv4'), $field($net,'mac'),
                    $field($net,'gateway'), $field($net,'dns'), $field($net,'dhcp'), $field($net,'connection_status'),
                    $field($net,'link_speed'), $many($x->antivirus), $many($x->bitlocker), $many($x->firewall),
                    $x->tpm, $x->secure_boot,
                    $many($licenses->filter(fn ($l) => stripos((string)($l->product_type ?? ''),'windows') !== false)),
                    $many($licenses->filter(fn ($l) => stripos((string)($l->product_type ?? ''),'office') !== false)),
                    $x->collected_at?->format('Y-m-d H:i:s'), $many($x->software),
                ];
                fputcsv($out, array_map($text, $values));
            }
            fclose($out);
        }, 'PC_Audit_Inventory_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.create'), 403);
        $branches = CustomerBranch::query()->where('customer_id', $user->service_customer_id)
            ->where('is_active', true)
            ->when(!$user->can_access_all_branches, fn ($q) => $q->whereIn('id', $user->permittedBranches()->select('customer_branches.id')))
            ->with(['pcAuditCodes' => fn ($q) => $q->orderBy('code')])->orderBy('name')->get();
        return view('customer.assets.form', ['asset' => new PcAudit(), 'branches' => $branches]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.create'), 403);
        $data = $request->validate([
            'pc_audit_code_id' => ['required', 'integer', 'exists:pc_audit_codes,id'],
            'computer_name' => ['required', 'string', 'max:255'],
            'asset_tag' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'employee_name' => ['nullable', 'string', 'max:255'],
            'employee_mobile' => ['nullable', 'string', 'max:50'],
            'employee_email' => ['nullable', 'email', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
        ]);
        $this->assertAuditCodeInScope($user, (int)$data['pc_audit_code_id']);
        $asset = PcAudit::create($data + [
            'asset_lookup_token' => bin2hex(random_bytes(24)),
            'audit_status' => 'REVIEW',
            'collected_at' => now(),
        ]);
        return redirect()->route('customer.assets.show', $asset->asset_lookup_token)->with('success', 'Đã tạo tài sản.');
    }

    public function edit(Request $request, string $token)
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.edit'), 403);
        $asset = $this->scopedAssets($user)->where('asset_lookup_token', $token)->firstOrFail();
        $branches = CustomerBranch::query()->where('customer_id', $user->service_customer_id)
            ->where('is_active', true)
            ->when(!$user->can_access_all_branches, fn ($q) => $q->whereIn('id', $user->permittedBranches()->select('customer_branches.id')))
            ->with(['pcAuditCodes' => fn ($q) => $q->orderBy('code')])->orderBy('name')->get();
        return view('customer.assets.form', compact('asset', 'branches'));
    }

    public function update(Request $request, string $token)
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.edit'), 403);
        $asset = $this->scopedAssets($user)->where('asset_lookup_token', $token)->firstOrFail();
        $data = $request->validate([
            'pc_audit_code_id' => ['required', 'integer', 'exists:pc_audit_codes,id'],
            'computer_name' => ['required', 'string', 'max:255'],
            'asset_tag' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'employee_name' => ['nullable', 'string', 'max:255'],
            'employee_mobile' => ['nullable', 'string', 'max:50'],
            'employee_email' => ['nullable', 'email', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
        ]);
        $this->assertAuditCodeInScope($user, (int)$data['pc_audit_code_id']);
        $asset->update($data);
        return redirect()->route('customer.assets.show', $asset->asset_lookup_token)->with('success', 'Đã cập nhật tài sản.');
    }

    public function destroy(Request $request, string $token)
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.delete'), 403);
        $asset = $this->scopedAssets($user)->where('asset_lookup_token', $token)->firstOrFail();
        $asset->delete();
        return redirect()->route('customer.assets.index')->with('success', 'Đã xóa tài sản.');
    }

    public function show(Request $request, string $token)
    {
        $user = $request->user();
        abort_unless($user?->isCustomerAccount() && ($user->hasPermission('customer.assets.view') || $user->hasPermission('customer.audit.view')), 403);
        $asset = $this->scopedAssets($user)->where('asset_lookup_token', $token)
            ->with(['auditCode.branch.customer', 'details', 'memory', 'storage', 'monitors', 'gpu', 'batteries', 'network', 'antivirus', 'bitlocker', 'firewall', 'licenses', 'software'])
            ->firstOrFail();
        return view('customer.assets.show', compact('asset'));
    }

    public function publicShow(string $token)
    {
        $asset = PcAudit::query()->where('asset_lookup_token', $token)
            ->whereHas('auditCode.branch.customer', fn ($q) => $q->where('is_active', true))
            ->with([
                'auditCode.branch.customer', 'details', 'memory', 'storage', 'monitors', 'gpu',
                'batteries', 'network', 'antivirus', 'bitlocker', 'firewall', 'licenses', 'software',
            ])
            ->firstOrFail();

        // QR is read-only. Do not expose raw_payload or credential material.
        return response()->view('customer.assets.public-show', compact('asset'))
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function qr(Request $request, string $token)
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.qr'), 403);
        $asset = $this->scopedAssets($user)->where('asset_lookup_token', $token)->with('auditCode.branch.customer')->firstOrFail();
        $url = route('assets.public.show', ['token' => $asset->asset_lookup_token]);
        return view('customer.assets.qr', compact('asset', 'url'));
    }

    private function assertAuditCodeInScope($user, int $codeId): void
    {
        $valid = PcAuditCode::query()->whereKey($codeId)->whereHas('branch', function ($q) use ($user): void {
            $q->where('customer_id', $user->service_customer_id);
            if (!$user->can_access_all_branches) {
                $q->whereIn('customer_branches.id', $user->permittedBranches()->select('customer_branches.id'));
            }
        })->exists();
        abort_unless($valid, 403, 'Audit Code không thuộc phạm vi tài khoản.');
    }
}
