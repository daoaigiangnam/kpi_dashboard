<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCustomer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceCustomerController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $search = trim((string) $request->query('search', ''));
        $showDeleted = $request->boolean('deleted');

        $query = $showDeleted ? ServiceCustomer::withTrashed() : ServiceCustomer::query();

        $customers = $query
            ->when(!$user->isSuperAdmin(), function ($q) use ($user): void {
                $q->where(function ($x) use ($user): void {
                    // Current ownership is by the assigned IT user.
                    $x->where('responsible_it_id', $user->id);

                    // Keep legacy customers visible when they have not yet been assigned
                    // to a specific IT user and are still assigned to this user's group.
                    if ($user->user_group_id) {
                        $x->orWhere(function ($legacy) use ($user): void {
                            $legacy->whereNull('responsible_it_id')
                                ->whereHas('groups', fn ($g) => $g->whereKey($user->user_group_id));
                        });
                    }
                });
            })
            ->with(['groups', 'responsibleIt'])
            ->when($search !== '', fn ($q) => $q->where(fn ($x) => $x
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('sales_name', 'like', "%{$search}%")
                ->orWhere('sales_email', 'like', "%{$search}%")
            ))
            ->when($showDeleted, fn ($q) => $q->whereNotNull('deleted_at'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.service-customers.index', compact('customers', 'search', 'showDeleted'));
    }

    public function create()
    {
        abort_unless(auth()->user()->hasPermission('service_customers.create'), 403);

        return view('admin.service-customers.form', [
            'customer' => new ServiceCustomer(),
            'itUsers' => $this->responsibleItUsers(),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('service_customers.create'), 403);

        $data = $this->validated($request);
        $data['responsible_it_id'] = $this->responsibleItIdForRequest($request);
        $data['sales_active'] = $request->boolean('sales_active');

        if (!$data['sales_active']) {
            $data['sales_name'] = null;
            $data['sales_email'] = null;
        }

        $customer = ServiceCustomer::create($data);
        $this->syncResponsibleGroup($customer);

        return redirect()->route('admin.service_customers.index')->with('success', 'Customer created.');
    }

    public function edit(ServiceCustomer $serviceCustomer)
    {
        abort_unless(auth()->user()->hasPermission('service_customers.edit'), 403);
        abort_unless($this->canAccess($serviceCustomer), 403);

        $serviceCustomer->load(['groups', 'responsibleIt']);

        return view('admin.service-customers.form', [
            'customer' => $serviceCustomer,
            'itUsers' => $this->responsibleItUsers(),
        ]);
    }

    public function update(Request $request, ServiceCustomer $serviceCustomer)
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('service_customers.edit'), 403);
        abort_unless($this->canAccess($serviceCustomer), 403);

        $data = $this->validated($request, $serviceCustomer);

        if ($user->isSuperAdmin()) {
            $data['responsible_it_id'] = $this->responsibleItIdForRequest($request, $serviceCustomer);
        } else {
            // A normal Team IT user cannot reassign ownership.
            $data['responsible_it_id'] = $serviceCustomer->responsible_it_id ?: $user->id;
        }

        $data['sales_active'] = $request->boolean('sales_active');
        if (!$data['sales_active']) {
            $data['sales_name'] = null;
            $data['sales_email'] = null;
        }

        $serviceCustomer->update($data);
        $this->syncResponsibleGroup($serviceCustomer);

        return redirect()->route('admin.service_customers.index')->with('success', 'Customer updated.');
    }

    public function destroy(ServiceCustomer $serviceCustomer)
    {
        abort_unless(auth()->user()->hasPermission('service_customers.delete'), 403);
        abort_unless($this->canAccess($serviceCustomer), 403);

        $serviceCustomer->delete();
        return back()->with('success', 'Customer deleted.');
    }

    public function restore(int $serviceCustomer)
    {
        abort_unless(auth()->user()->hasPermission('service_customers.delete'), 403);

        $customer = ServiceCustomer::withTrashed()->findOrFail($serviceCustomer);
        abort_unless($this->canAccess($customer), 403);
        $customer->restore();

        return back()->with('success', 'Customer restored.');
    }

    private function canAccess(ServiceCustomer $customer): bool
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ((int) $customer->responsible_it_id === (int) $user->id) {
            return true;
        }

        // Legacy compatibility only: unassigned customers may still be managed
        // by members of the legacy IT group until a specific IT owner is selected.
        return $customer->responsible_it_id === null
            && $user->user_group_id
            && $customer->groups()->whereKey($user->user_group_id)->exists();
    }

    private function responsibleItUsers()
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('group', fn ($q) => $q->where('name', 'Team IT'))
            ->with('group')
            ->orderBy('name')
            ->get();
    }

    private function responsibleItIdForRequest(Request $request, ?ServiceCustomer $customer = null): int
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin()) {
            return (int) $user->id;
        }

        $id = (int) $request->input('responsible_it_id', $customer?->responsible_it_id ?? 0);
        abort_unless($id > 0, 422, 'Vui lòng chọn Đầu mối vận hành IT.');

        $responsible = User::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->whereHas('group', fn ($q) => $q->where('name', 'Team IT'))
            ->first();

        abort_unless($responsible, 422, 'Đầu mối vận hành IT không hợp lệ.');

        return $responsible->id;
    }

    private function syncResponsibleGroup(ServiceCustomer $customer): void
    {
        $responsible = $customer->responsibleIt()->with('group')->first();
        $groupId = $responsible?->user_group_id;

        if ($groupId) {
            $customer->groups()->sync([$groupId]);
        } else {
            $customer->groups()->detach();
        }
    }

    private function validated(Request $request, ?ServiceCustomer $customer = null): array
    {
        return $request->validate([
            'code' => ['required','string','max:50','alpha_dash',Rule::unique('service_customers','code')->ignore($customer?->id)],
            'name' => ['required','string','max:150'],
            'contact_name' => ['nullable','string','max:150'],
            'email' => ['nullable','email','max:190'],
            'phone' => ['nullable','string','max:50'],
            'is_active' => ['nullable','boolean'],
            'sales_name' => ['nullable','string','max:150'],
            'sales_email' => ['nullable','email','max:190'],
            'sales_active' => ['nullable','boolean'],
        ]);
    }
}
