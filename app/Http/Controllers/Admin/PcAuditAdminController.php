<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerBranch;
use App\Models\PcAuditCode;
use App\Models\PcAuditSetting;
use App\Models\ServiceCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PcAuditAdminController extends Controller
{
    public function settings()
    {
        $setting = PcAuditSetting::query()->first() ?? new PcAuditSetting([
            'api_base_url' => config('app.url') . '/api',
            'tool_version' => '1.0.0',
            'minimum_tool_version' => '1.0.0',
            'enabled' => true,
        ]);

        return view('admin.pc-audit.settings', compact('setting'));
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'api_base_url' => ['required', 'url', 'max:500'],
            'tool_version' => ['required', 'string', 'max:50'],
            'minimum_tool_version' => ['required', 'string', 'max:50'],
            'enabled' => ['nullable', 'boolean'],
            'download_url' => ['nullable', 'url', 'max:1000'],
            'disabled_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['api_base_url'] = rtrim($data['api_base_url'], '/');
        $data['enabled'] = $request->boolean('enabled');

        PcAuditSetting::query()->updateOrCreate(['id' => 1], $data);

        return back()->with('success', 'Đã lưu cấu hình PC Audit.');
    }

    public function codes(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $customerId = $request->query('customer_id');

        // PC Audit dùng trực tiếp Customer của module Service, không tạo Customer riêng.
        $customers = ServiceCustomer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        // Chi nhánh chỉ khai báo bổ sung cho Customer Service; không tạo Customer mới.
        $branches = CustomerBranch::query()
            ->with('customer:id,code,name')
            ->where('is_active', true)
            ->whereHas('customer', fn ($q) => $q->where('is_active', true))
            ->orderBy('customer_id')
            ->orderBy('name')
            ->get();

        $codes = PcAuditCode::query()
            ->with('branch.customer')
            ->whereHas('branch', fn ($q) => $q
                ->where('is_active', true)
                ->whereHas('customer', fn ($c) => $c->where('is_active', true)))
            ->when($search !== '', fn ($q) => $q->where(fn ($x) => $x
                ->where('code', 'like', "%{$search}%")
                ->orWhere('department', 'like', "%{$search}%")))
            ->when($customerId, fn ($q) => $q->whereHas('branch', fn ($b) => $b->where('customer_id', $customerId)))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.pc-audit.codes', compact('codes', 'customers', 'branches', 'search', 'customerId'));
    }

    public function storeBranch(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:service_customers,id'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        $customer = ServiceCustomer::query()
            ->whereKey($data['customer_id'])
            ->where('is_active', true)
            ->first();

        if (!$customer) {
            return back()->withInput()->withErrors([
                'customer_id' => 'Customer Service không tồn tại hoặc đang không hoạt động.',
            ]);
        }

        $name = trim($data['name']);
        $baseCode = strtoupper(Str::limit(Str::slug($name, '-'), 45, ''));
        if ($baseCode === '') {
            $baseCode = 'BRANCH';
        }

        $branchCode = $baseCode;
        $suffix = 2;
        while (CustomerBranch::query()
            ->where('customer_id', $customer->id)
            ->where('code', $branchCode)
            ->exists()) {
            $branchCode = Str::limit($baseCode, 45, '') . '-' . $suffix++;
        }

        if (CustomerBranch::query()
            ->where('customer_id', $customer->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists()) {
            return back()->withInput()->withErrors([
                'name' => 'Chi nhánh này đã tồn tại trong Customer.',
            ]);
        }

        CustomerBranch::create([
            'customer_id' => $customer->id,
            'code' => $branchCode,
            'name' => $name,
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.pc_audit.codes', ['customer_id' => $customer->id])
            ->with('success', "Đã thêm chi nhánh {$name} cho Customer {$customer->name}.");
    }

    public function storeCode(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:customer_branches,id'],
            'department' => ['required', 'string', 'max:200'],
        ]);

        $branch = CustomerBranch::query()
            ->with('customer')
            ->whereKey($data['branch_id'])
            ->where('is_active', true)
            ->whereHas('customer', fn ($q) => $q->where('is_active', true))
            ->first();

        if (!$branch) {
            return back()->withInput()->withErrors([
                'branch_id' => 'Chi nhánh không tồn tại hoặc Customer Service đang không hoạt động.',
            ]);
        }

        $department = trim($data['department']);
        $baseCode = collect([
            $branch->customer?->code,
            $branch->code,
            Str::slug($department, '-'),
        ])->filter()->map(fn ($value) => strtoupper((string) $value))->implode('-');

        $baseCode = Str::limit($baseCode, 92, '');
        $code = $baseCode;
        $suffix = 2;

        while (PcAuditCode::query()->where('code', $code)->exists()) {
            $suffixText = '-' . $suffix++;
            $code = Str::limit($baseCode, 100 - strlen($suffixText), '') . $suffixText;
        }

        PcAuditCode::create([
            'branch_id' => $branch->id,
            'code' => $code,
            'name' => 'PC Audit - ' . $department,
            'department' => $department,
            'is_active' => true,
        ]);

        return back()->with('success', "Đã tạo Audit Code: {$code}");
    }

    public function toggleCode(PcAuditCode $pcAuditCode)
    {
        $pcAuditCode->update(['is_active' => !$pcAuditCode->is_active]);

        return back()->with('success', 'Đã cập nhật trạng thái Audit Code.');
    }
}
