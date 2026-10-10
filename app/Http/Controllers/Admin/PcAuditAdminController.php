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
    public function launcher()
    {
        $customers = ServiceCustomer::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        $branches = CustomerBranch::query()->where('is_active', true)->whereHas('customer', fn ($q) => $q->where('is_active', true))->orderBy('customer_id')->orderBy('name')->get(['id', 'customer_id', 'name']);
        return view('admin.pc-audit.launcher', compact('customers', 'branches'));
    }

    public function generateLauncher(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:service_customers,id'],
            'branch_id' => ['required', 'integer', 'exists:customer_branches,id'],
        ]);

        $customer = ServiceCustomer::query()->whereKey($data['customer_id'])->where('is_active', true)->firstOrFail();
        $branch = CustomerBranch::query()->whereKey($data['branch_id'])->where('customer_id', $customer->id)->where('is_active', true)->firstOrFail();

        $auditCode = PcAuditCode::query()->where('branch_id', $branch->id)->where('is_active', true)->orderBy('id')->first();
        if (!$auditCode) {
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            do {
                $code = '';
                for ($i = 0; $i < 6; $i++) { $code .= $alphabet[random_int(0, strlen($alphabet) - 1)]; }
            } while (PcAuditCode::query()->where('code', $code)->exists());
            $auditCode = PcAuditCode::create(['branch_id' => $branch->id, 'code' => $code, 'name' => 'PC Audit - ' . $branch->name, 'is_active' => true]);
        }

        $basePath = base_path('tools/pc-audit');
        foreach (['Config.ps1', 'ApiClient.ps1', 'Collector.ps1', 'AuditTool.ps1'] as $file) {
            abort_unless(is_file($basePath . DIRECTORY_SEPARATOR . $file), 500, 'Thiếu file nguồn PC Audit: ' . $file);
        }

        $config = preg_replace('/^\\xEF\\xBB\\xBF/', '', (string) file_get_contents($basePath . '/Config.ps1'));
        $api = preg_replace('/^\\xEF\\xBB\\xBF/', '', (string) file_get_contents($basePath . '/ApiClient.ps1'));
        $collector = preg_replace('/^\\xEF\\xBB\\xBF/', '', (string) file_get_contents($basePath . '/Collector.ps1'));
        $main = preg_replace('/^\\xEF\\xBB\\xBF/', '', (string) file_get_contents($basePath . '/AuditTool.ps1'));

        $collector = preg_replace('/^\s*param\(\[switch\]\$Progress\)\s*/', '', (string) $collector, 1);
        $main = preg_replace('/^\$here\s*=.*\R/', '', (string) $main, 1);
        $main = preg_replace('/^\. \(Join-Path \$here .*?\R/m', '', (string) $main);

        $compatibility = <<<'PS'
$Progress = $true
try { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 } catch {}
if (-not (Get-Command Get-CimInstance -ErrorAction SilentlyContinue)) {
    function Get-CimInstance {
        param([Parameter(Position=0)][string]$ClassName,[string]$Namespace='root/cimv2',[string]$Filter,[string]$ErrorAction='Continue')
        $args = @{ Class=$ClassName; Namespace=$Namespace; ErrorAction=$ErrorAction }
        if (-not [string]::IsNullOrWhiteSpace($Filter)) { $args.Filter=$Filter }
        Get-WmiObject @args
    }
}
PS;

        $collector = str_replace(
            '$result.security.bitlocker=@(Get-BitLockerVolume -ErrorAction SilentlyContinue|ForEach-Object{[ordered]@{mount_point=$_.MountPoint;protection_status=[string]$_.ProtectionStatus;volume_status=[string]$_.VolumeStatus;encryption_percent=$_.EncryptionPercentage}})',
            'if (Get-Command Get-BitLockerVolume -ErrorAction SilentlyContinue) {$result.security.bitlocker=@(Get-BitLockerVolume -ErrorAction SilentlyContinue|ForEach-Object{[ordered]@{mount_point=$_.MountPoint;protection_status=[string]$_.ProtectionStatus;volume_status=[string]$_.VolumeStatus;encryption_percent=$_.EncryptionPercentage}})} else {$result.security.bitlocker=@()}',
            (string) $collector
        );
        $collector = str_replace(
            '$result.security.firewall=@(Get-NetFirewallProfile -ErrorAction SilentlyContinue|ForEach-Object{[ordered]@{profile=$_.Name;enabled=$_.Enabled}})',
            'if (Get-Command Get-NetFirewallProfile -ErrorAction SilentlyContinue) {$result.security.firewall=@(Get-NetFirewallProfile -ErrorAction SilentlyContinue|ForEach-Object{[ordered]@{profile=$_.Name;enabled=$_.Enabled}})} else {$result.security.firewall=@()}',
            (string) $collector
        );

        $psQuote = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";
        $main = str_replace(
            "$" . "code = Read-Host 'Audit Code'\nif ([string]::IsNullOrWhiteSpace($" . "code)) { throw 'Audit Code khong duoc de trong.' }\n$" . "code = $" . "code.Trim().ToUpperInvariant()",
            '$code = ' . $psQuote($auditCode->code) . "\nif ([string]::IsNullOrWhiteSpace($" . "code)) { throw 'Audit Code khong duoc de trong.' }\n$" . "code = $" . "code.Trim().ToUpperInvariant()",
            (string) $main
        );

        $ps1 = $compatibility . "\r\n" . $config . "\r\n" . $api . "\r\n" . $collector . "\r\n" . $main;
        $bat = <<<'BAT'
@echo off
setlocal
cd /d "%~dp0"
chcp 65001 >nul
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0PC_Audit.ps1"
if errorlevel 1 (
    echo.
    echo PC Audit that bai. Vui long chup man hinh loi gui cho IT.
    pause
)
endlocal
BAT;

        if (!class_exists(\ZipArchive::class)) {
            return back()->withInput()->withErrors(['launcher' => 'PHP ZipArchive chưa được cài trên server. Vui lòng bật extension zip rồi thử lại.']);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'pc-audit-');
        if ($tmp === false) { return back()->withInput()->withErrors(['launcher' => 'Không tạo được file tạm để đóng gói PC Audit.']); }
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            return back()->withInput()->withErrors(['launcher' => 'Không thể tạo gói ZIP PC Audit.']);
        }
        $zip->addFromString('PC_Audit.ps1', "\xEF\xBB\xBF" . $ps1);
        $zip->addFromString('PC_Audit.bat', $bat);
        $zip->close();

        $filename = 'PC_Audit_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $branch->code ?: $branch->name) . '_' . now()->format('Ymd_His') . '.zip';
        return response()->download($tmp, $filename, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

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
