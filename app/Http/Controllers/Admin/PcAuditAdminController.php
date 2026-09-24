<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerBranch;
use App\Models\PcAuditCode;
use App\Models\PcAuditSetting;
use App\Models\ServiceCustomer;
use App\Models\ServiceCustomerAlertRecipient;
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
        $customers = ServiceCustomer::query()->orderBy('name')->get(['id', 'code', 'name']);
        $branches = CustomerBranch::query()->with('customer:id,code,name')->where('is_active', true)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))->orderBy('name')->get();
        $codes = PcAuditCode::query()->with('branch.customer')
            ->when($search !== '', fn ($q) => $q->where(fn ($x) => $x->where('code','like',"%{$search}%")->orWhere('name','like',"%{$search}%")))
            ->when($customerId, fn ($q) => $q->whereHas('branch', fn ($b) => $b->where('customer_id', $customerId)))
            ->latest()->paginate(25)->withQueryString();
        return view('admin.pc-audit.codes', compact('codes','customers','branches','search','customerId'));
    }

    public function storeCode(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required','exists:customer_branches,id'],
            'code' => ['nullable','string','max:100','alpha_dash','unique:pc_audit_codes,code'],
            'name' => ['nullable','string','max:150'],
        ]);
        $data['code'] = strtoupper($data['code'] ?? Str::random(10));
        $data['is_active'] = true;
        PcAuditCode::create($data);
        return back()->with('success', 'Đã tạo Audit Code.');
    }

    public function toggleCode(PcAuditCode $pcAuditCode)
    {
        $pcAuditCode->update(['is_active' => !$pcAuditCode->is_active]);
        return back()->with('success', 'Đã cập nhật trạng thái Audit Code.');
    }

    public function recipients(Request $request)
    {
        $customers = ServiceCustomer::query()->with('alertRecipients')->orderBy('name')->get();
        $customerId = $request->query('customer_id');
        $customer = $customerId ? $customers->firstWhere('id', (int)$customerId) : null;
        return view('admin.pc-audit.recipients', compact('customers','customer','customerId'));
    }

    public function storeRecipient(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required','exists:service_customers,id'],
            'recipient_name' => ['required','string','max:150'],
            'recipient_email' => ['required','email','max:190'],
            'recipient_phone' => ['nullable','string','max:50'],
            'level' => ['nullable','integer','min:1','max:99'],
        ]);
        $data['level'] = (int)($data['level'] ?? 1);
        $data['is_active'] = true;
        ServiceCustomerAlertRecipient::create($data);
        return back()->with('success', 'Đã thêm Email nhận Audit.');
    }

    public function deleteRecipient(ServiceCustomerAlertRecipient $recipient)
    {
        $recipient->delete();
        return back()->with('success', 'Đã xóa Email nhận Audit.');
    }

    public function toggleRecipient(ServiceCustomerAlertRecipient $recipient)
    {
        $recipient->update(['is_active' => !$recipient->is_active]);
        return back()->with('success', 'Đã cập nhật trạng thái Email.');
    }
}
