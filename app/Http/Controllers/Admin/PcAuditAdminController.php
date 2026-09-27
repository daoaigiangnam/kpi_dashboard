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

        // PC Audit dùng trực tiếp Customer của module Service, không tạo Customer riêng.
        $customers = ServiceCustomer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        // Chỉ khai báo bổ sung Chi nhánh cho Customer Service khi cần.
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
            ->when($search !== '', fn ($q) => $q->where(function ($x) use ($search) {
                $x->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            }))
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
        if (CustomerBranch::query()
            ->where('customer_id', $customer->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists()) {
            return back()->withInput()->withErrors([
                'name' => 'Chi nhánh này đã tồn tại trong Customer.',
            ]);
        }

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
        // Dùng chung POST /pc-audit/codes để giao diện vẫn chỉ có một luồng cấu hình.
        if ($request->input('action') === 'create_branch') {
            return $this->storeBranch($request);
        }

        $data = $request->validate([
            'branch_id' => ['required', 'exists:customer_branches,id'],
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

        // Audit Code là mã ngắn để User nhập nhanh trên PC. Không chứa Customer/Chi nhánh.
        // Loại bỏ các ký tự dễ nhầm: 0/O và 1/I.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (PcAuditCode::query()->where('code', $code)->exists());

        PcAuditCode::create([
            'branch_id' => $branch->id,
            'code' => $code,
            'name' => 'PC Audit - ' . $branch->name,
            'is_active' => true,
        ]);

        return back()->with('success', "Đã tạo Audit Code: {$code}");
    }

    public function toggleCode(PcAuditCode $pcAuditCode)
    {
        $pcAuditCode->update(['is_active' => !$pcAuditCode->is_active]);

        return back()->with('success', 'Đã cập nhật trạng thái Audit Code.');
    }

    // Giữ các route cũ để không làm hỏng bookmark/URL hiện hữu. Giao diện PC Audit không còn dùng chúng.
    public function recipients(Request $request)
    {
        $customers = ServiceCustomer::query()
            ->where('is_active', true)
            ->with('alertRecipients')
            ->orderBy('name')
            ->get();
        $customerId = $request->query('customer_id');
        $customer = $customerId ? $customers->firstWhere('id', (int) $customerId) : null;
        return view('admin.pc-audit.recipients', compact('customers', 'customer', 'customerId'));
    }

    public function storeRecipient(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:service_customers,id'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'recipient_email' => ['required', 'email', 'max:190'],
            'recipient_phone' => ['nullable', 'string', 'max:50'],
            'level' => ['nullable', 'integer', 'min:1', 'max:99'],
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

        $data['level'] = (int) ($data['level'] ?? 1);
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
