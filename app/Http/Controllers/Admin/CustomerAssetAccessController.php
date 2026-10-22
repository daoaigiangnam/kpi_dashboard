<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCustomer;
use App\Models\CustomerBranch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use App\Notifications\PasswordResetOtpNotification;

class CustomerAssetAccessController extends Controller
{
    public function index(Request $request)
    {
        $customers = ServiceCustomer::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        $selectedCustomerId = $request->integer('customer_id');
        $users = User::query()
            ->where('account_type', 'customer')
            ->with(['customer', 'permittedBranches'])
            ->when($selectedCustomerId, fn ($q) => $q->where('service_customer_id', $selectedCustomerId))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.customer-assets.users', compact('customers', 'users', 'selectedCustomerId'));
    }

    public function create()
    {
        $customers = ServiceCustomer::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        $branches = CustomerBranch::query()->whereIn('customer_id', $customers->pluck('id'))->where('is_active', true)->orderBy('name')->get(['id', 'customer_id', 'code', 'name']);
        return view('admin.customer-assets.user-form', ['user' => new User(), 'customers' => $customers, 'branches' => $branches, 'selectedBranchIds' => []]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'service_customer_id' => ['required', 'integer', 'exists:service_customers,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'can_view_assets' => ['nullable', 'boolean'],
            'can_create_assets' => ['nullable', 'boolean'],
            'can_edit_assets' => ['nullable', 'boolean'],
            'can_delete_assets' => ['nullable', 'boolean'],
            'can_export_assets' => ['nullable', 'boolean'],
            'can_view_audit' => ['nullable', 'boolean'],
            'can_download_qr' => ['nullable', 'boolean'],
            'can_access_all_branches' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:customer_branches,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $customer = ServiceCustomer::query()->whereKey($data['service_customer_id'])->where('is_active', true)->firstOrFail();

        $allBranches = $request->boolean('can_access_all_branches');
        $branchIds = array_values(array_unique(array_map('intval', $data['branch_ids'] ?? [])));
        abort_if(!$allBranches && count($branchIds) === 0, 422, 'Hãy chọn ít nhất một chi nhánh hoặc cấp quyền tất cả chi nhánh.');
        if (!$allBranches) {
            $validCount = CustomerBranch::query()->where('customer_id', $customer->id)->whereIn('id', $branchIds)->count();
            abort_unless($validCount === count($branchIds), 422, 'Có chi nhánh không thuộc khách hàng đã chọn.');
        }

        $user = User::create([
            'name' => trim($data['name']),
            'email' => Str::lower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make(Str::random(64)),
            'account_type' => 'customer',
            'service_customer_id' => $customer->id,
            'can_view_assets' => $request->boolean('can_view_assets'),
            'can_create_assets' => $request->boolean('can_create_assets'),
            'can_edit_assets' => $request->boolean('can_edit_assets'),
            'can_delete_assets' => $request->boolean('can_delete_assets'),
            'can_export_assets' => $request->boolean('can_export_assets'),
            'can_view_audit' => $request->boolean('can_view_audit'),
            'can_download_qr' => $request->boolean('can_download_qr'),
            'can_access_all_branches' => $allBranches,
            'is_active' => $request->boolean('is_active', true),
            'registration_status' => 'approved',
        ]);

        $user->permittedBranches()->sync($allBranches ? [] : $branchIds);

        try {
            $this->sendPasswordSetupEmail($user, true);
            return redirect()->route('admin.customer_assets.users')->with(
                'success',
                'Đã tạo tài khoản khách hàng và gửi email thiết lập mật khẩu tới '.$user->email.'.'
            );
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('admin.customer_assets.users')->with(
                'warning',
                'Tài khoản đã được tạo nhưng chưa gửi được email thiết lập mật khẩu. Vui lòng kiểm tra SMTP và thực hiện gửi lại.'
            );
        }
    }

    public function edit(User $user)
    {
        abort_unless($user->account_type === 'customer', 404);
        $customers = ServiceCustomer::query()
            ->where(function ($query) use ($user): void {
                $query->where('is_active', true)->orWhereKey($user->service_customer_id);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $branches = CustomerBranch::query()->whereHas('customer', fn ($q) => $q->where('is_active', true))->where('is_active', true)->orderBy('name')->get(['id', 'customer_id', 'code', 'name']);
        $selectedBranchIds = $user->permittedBranches()->pluck('customer_branches.id')->map(fn ($id) => (int) $id)->all();
        return view('admin.customer-assets.user-form', compact('user', 'customers', 'branches', 'selectedBranchIds'));
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
            'can_create_assets' => ['nullable', 'boolean'],
            'can_edit_assets' => ['nullable', 'boolean'],
            'can_delete_assets' => ['nullable', 'boolean'],
            'can_export_assets' => ['nullable', 'boolean'],
            'can_view_audit' => ['nullable', 'boolean'],
            'can_download_qr' => ['nullable', 'boolean'],
            'can_access_all_branches' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:customer_branches,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        ServiceCustomer::query()
            ->whereKey($data['service_customer_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $allBranches = $request->boolean('can_access_all_branches');
        $branchIds = array_values(array_unique(array_map('intval', $data['branch_ids'] ?? [])));
        abort_if(!$allBranches && count($branchIds) === 0, 422, 'Hãy chọn ít nhất một chi nhánh hoặc cấp quyền tất cả chi nhánh.');
        if (!$allBranches) {
            $validCount = CustomerBranch::query()->where('customer_id', (int) $data['service_customer_id'])->whereIn('id', $branchIds)->count();
            abort_unless($validCount === count($branchIds), 422, 'Có chi nhánh không thuộc khách hàng đã chọn.');
        }

        $user->update([
            'name' => trim($data['name']),
            'email' => Str::lower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'service_customer_id' => (int) $data['service_customer_id'],
            'can_view_assets' => $request->boolean('can_view_assets'),
            'can_create_assets' => $request->boolean('can_create_assets'),
            'can_edit_assets' => $request->boolean('can_edit_assets'),
            'can_delete_assets' => $request->boolean('can_delete_assets'),
            'can_export_assets' => $request->boolean('can_export_assets'),
            'can_view_audit' => $request->boolean('can_view_audit'),
            'can_download_qr' => $request->boolean('can_download_qr'),
            'can_access_all_branches' => $allBranches,
            'is_active' => $request->boolean('is_active'),
        ]);

        $user->permittedBranches()->sync($allBranches ? [] : $branchIds);
        return redirect()->route('admin.customer_assets.users')->with('success', 'Đã cập nhật tài khoản và quyền khách hàng.');
    }

    public function sendSetupPassword(User $user)
    {
        abort_unless($user->account_type === 'customer', 404);
        abort_unless($user->is_active, 422, 'Hãy kích hoạt tài khoản trước khi gửi email.');
        try {
            $this->sendPasswordSetupEmail($user, false);
            return back()->with('success', 'Đã gửi email thiết lập/đặt lại mật khẩu tới '.$user->email.'.');
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors('Không gửi được email. Vui lòng kiểm tra cấu hình SMTP.');
        }
    }

    private function sendPasswordSetupEmail(User $user, bool $initialSetup = false): void
    {
        $token = Password::broker()->createToken($user);
        $otp = (string) random_int(100000, 999999);
        DB::table('password_reset_otps')->where('email', $user->email)->delete();
        DB::table('password_reset_otps')->insert([
            'email' => $user->email,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user->notify(new PasswordResetOtpNotification($token, $otp, $initialSetup));
    }

    public function toggle(User $user)
    {
        abort_unless($user->account_type === 'customer', 404);
        $user->update(['is_active' => !$user->is_active]);
        return back()->with('success', 'Đã cập nhật trạng thái tài khoản khách hàng.');
    }

}
