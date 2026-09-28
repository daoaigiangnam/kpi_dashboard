<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCustomer;
use App\Models\UserGroup;
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
            ->when(!$user->isSuperAdmin(), function ($q) use ($user) {
                $q->where(function ($x) use ($user) {
                    if ($user->user_group_id) {
                        $x->whereHas('groups', fn ($g) => $g->whereKey($user->user_group_id));
                    }
                    $x->orWhereHas('services', fn ($sq) => $sq->visibleTo($user));
                });
            })
            ->with(['alertRecipients', 'groups'])
            ->when($search !== '', fn ($q) => $q->where(fn ($x) => $x
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
            ))
            ->when($showDeleted, fn ($q) => $q->whereNotNull('deleted_at'))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.service-customers.index', compact('customers', 'search', 'showDeleted'));
    }

    public function create()
    {
        $groups = auth()->user()->hasPermission('service_customers.access')
            ? UserGroup::query()->orderBy('name')->get()
            : collect();

        return view('admin.service-customers.form', [
            'customer' => new ServiceCustomer(),
            'groups' => $groups,
        ]);
    }

    public function store(Request $request)
    {
        $customer = ServiceCustomer::create($this->validated($request));
        $this->syncGroups($request, $customer);
        $this->saveAlertRecipient($request, $customer);

        return redirect()->route('admin.service_customers.index')->with('success', 'Customer created.');
    }

    public function edit(ServiceCustomer $serviceCustomer)
    {
        abort_unless($this->canAccess($serviceCustomer), 403);
        $serviceCustomer->load(['alertRecipients', 'groups']);
        $groups = auth()->user()->hasPermission('service_customers.access')
            ? UserGroup::query()->orderBy('name')->get()
            : collect();

        return view('admin.service-customers.form', [
            'customer' => $serviceCustomer,
            'groups' => $groups,
        ]);
    }

    public function update(Request $request, ServiceCustomer $serviceCustomer)
    {
        abort_unless($this->canAccess($serviceCustomer), 403);
        $serviceCustomer->update($this->validated($request, $serviceCustomer));
        $this->syncGroups($request, $serviceCustomer);
        $this->saveAlertRecipient($request, $serviceCustomer);

        return redirect()->route('admin.service_customers.index')->with('success', 'Customer updated.');
    }

    public function destroy(ServiceCustomer $serviceCustomer)
    {
        abort_unless($this->canAccess($serviceCustomer), 403);
        $serviceCustomer->delete();
        return back()->with('success', 'Customer deleted.');
    }

    public function restore(int $serviceCustomer)
    {
        $customer = ServiceCustomer::withTrashed()->findOrFail($serviceCustomer);
        abort_unless($this->canAccess($customer), 403);
        $customer->restore();
        return back()->with('success', 'Customer restored.');
    }

    private function canAccess(ServiceCustomer $customer): bool
    {
        $user = auth()->user();

        return $user->isSuperAdmin()
            || ($user->user_group_id && $customer->groups()->whereKey($user->user_group_id)->exists())
            || $customer->services()->visibleTo($user)->exists();
    }

    private function syncGroups(Request $request, ServiceCustomer $customer): void
    {
        $user = auth()->user();

        if ($user->hasPermission('service_customers.access')) {
            $groupIds = $request->input('group_ids', []);
            $groupIds = array_values(array_unique(array_map('intval', is_array($groupIds) ? $groupIds : [])));
            $validIds = UserGroup::query()->whereIn('id', $groupIds)->pluck('id')->all();
            $customer->groups()->sync($validIds);
            return;
        }

        // An operator without access-management permission can only manage
        // customers belonging to their own group. New customers are automatically
        // assigned to that group and existing assignments are left intact.
        if ($user->user_group_id && !$customer->groups()->whereKey($user->user_group_id)->exists()) {
            $customer->groups()->syncWithoutDetaching([$user->user_group_id]);
        }
    }

    private function saveAlertRecipient(Request $request, ServiceCustomer $customer): void
    {
        $data = $request->validate([
            'alert_recipient.name' => ['nullable', 'string', 'max:150'],
            'alert_recipient.email' => ['nullable', 'email', 'max:190'],
            'alert_recipient.phone' => ['nullable', 'string', 'max:50'],
            'alert_recipient.is_active' => ['nullable', 'boolean'],
        ]);

        $row = $data['alert_recipient'] ?? [];
        $name = trim((string) ($row['name'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));
        $phone = trim((string) ($row['phone'] ?? ''));
        $active = !empty($row['is_active']) && $name !== '' && $email !== '';

        if ($name === '' && $email === '' && $phone === '') {
            $customer->alertRecipients()->delete();
            return;
        }

        $customer->alertRecipients()->updateOrCreate(
            ['level' => 1],
            [
                'recipient_name' => $name !== '' ? $name : $customer->name,
                'recipient_email' => $email !== '' ? $email : 'disabled-1@invalid.local',
                'recipient_phone' => $phone !== '' ? $phone : null,
                'is_active' => $active,
            ]
        );

        $customer->alertRecipients()->where('level', '!=', 1)->delete();
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
        ]);
    }
}
