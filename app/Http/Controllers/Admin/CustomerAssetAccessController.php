<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerBranch;
use App\Models\PcAudit;
use App\Models\ServiceCustomer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerAssetAccessController extends Controller
{
    public function index(Request $request)
    {
        $customers = ServiceCustomer::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        $selectedCustomerId = $request->integer('customer_id');
        $users = User::query()
            ->where('account_type', 'customer')
            ->with('customer')
            ->when($selectedCustomerId, fn ($q) => $q->where('service_customer_id', $selectedCustomerId))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.customer-assets.users', compact('customers', 'users', 'selectedCustomerId'));
    }

    public function create()
    {
        $customers = ServiceCustomer::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        return view('admin.customer-assets.user-form', ['user' => new User(), 'customers' => $customers]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'service_customer_id' => ['required', 'integer', 'exists:service_customers,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'can_view_assets' => ['nullable', 'boolean'],
            'can_view_audit' => ['nullable', 'boolean'],
            'can_download_qr' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $customer = ServiceCustomer::query()->whereKey($data['service_customer_id'])->where('is_active', true)->firstOrFail();

        $user = User::create([
            'name' => trim($data['name']),
            'email' => Str::lower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make(Str::random(64)),
            'account_type' => 'customer',
            'service_customer_id' => $customer->id,
            'can_view_assets' => $request->boolean('can_view_assets'),
            'can_view_audit' => $request->boolean('can_view_audit'),
            'can_download_qr' => $request->boolean('can_download_qr'),
            'is_active' => $request->boolean('is_active', true),
            'registration_status' => 'approved',
        ]);

        return redirect()->route('admin.customer_assets.users')->with(
            'success',
            'Đã tạo tài khoản khách hàng. Tài khoản được liên kết với '.$customer->name.'. Hãy dùng chức năng đặt lại mật khẩu/gửi email thiết lập mật khẩu trước khi bàn giao.'
        );
    }

    public function edit(User $user)
    {
        abort_unless($user->account_type === 'customer', 404);
        $customers = ServiceCustomer::query()->where('is_active', true)->orWhereKey($user->service_customer_id)->orderBy('name')->get(['id', 'code', 'name']);
        return view('admin.customer-assets.user-form', compact('user', 'customers'));
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->account_type === 'customer', 404);

        $data = $request->validate([
            'service_customer_id' => ['required', 'integer', 'exists:service_customers,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'can_view_assets' => ['nullable', 'boolean'],
            'can_view_audit' => ['nullable', 'boolean'],
            'can_download_qr' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        ServiceCustomer::query()->whereKey($data['service_customer_id'])->where('is_active', true)->firstOrFail();

        $user->update([
            'name' => trim($data['name']),
            'email' => Str::lower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'service_customer_id' => (int) $data['service_customer_id'],
            'can_view_assets' => $request->boolean('can_view_assets'),
            'can_view_audit' => $request->boolean('can_view_audit'),
            'can_download_qr' => $request->boolean('can_download_qr'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.customer_assets.users')->with('success', 'Đã cập nhật tài khoản và quyền khách hàng.');
    }

    public function toggle(User $user)
    {
        abort_unless($user->account_type === 'customer', 404);
        $user->update(['is_active' => !$user->is_active]);
        return back()->with('success', 'Đã cập nhật trạng thái tài khoản khách hàng.');
    }

    public function assets()
    {
        $user = request()->user();
        abort_unless($user->account_type === 'customer' && $user->can_view_assets, 403);
        abort_unless($user->service_customer_id, 403, 'Tài khoản chưa được gán khách hàng.');

        $assets = PcAudit::query()
            ->whereHas('auditCode.branch', fn ($q) => $q->where('customer_id', $user->service_customer_id))
            ->with(['auditCode.branch.customer'])
            ->latest('collected_at')
            ->paginate(25);

        return view('customer.assets.index', compact('assets'));
    }

    public function asset(string $token)
    {
        $user = request()->user();
        abort_unless($user->account_type === 'customer' && ($user->can_view_assets || $user->can_view_audit), 403);
        abort_unless($user->service_customer_id, 403, 'Tài khoản chưa được gán khách hàng.');

        $audit = PcAudit::query()
            ->where('asset_lookup_token', $token)
            ->whereHas('auditCode.branch', fn ($q) => $q->where('customer_id', $user->service_customer_id))
            ->with(['auditCode.branch.customer', 'details', 'memory', 'storage', 'monitors', 'gpu', 'batteries', 'network', 'antivirus', 'bitlocker', 'firewall', 'licenses', 'software'])
            ->firstOrFail();

        return view('customer.assets.show', compact('audit'));
    }

    public function downloadQr(string $token)
    {
        $user = request()->user();
        abort_unless($user->account_type === 'customer' && $user->can_view_assets && $user->can_download_qr, 403);
        abort_unless($user->service_customer_id, 403);

        $audit = PcAudit::query()
            ->where('asset_lookup_token', $token)
            ->whereHas('auditCode.branch', fn ($q) => $q->where('customer_id', $user->service_customer_id))
            ->with('auditCode.branch.customer')
            ->firstOrFail();

        // QR encodes an authenticated portal URL. No asset fields or sequential database IDs are embedded.
        $url = route('customer.assets.show', ['token' => $audit->asset_lookup_token]);
        return view('customer.assets.qr', compact('audit', 'url'));
    }
}
