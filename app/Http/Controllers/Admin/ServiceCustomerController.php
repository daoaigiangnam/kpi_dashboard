<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCustomer;
use App\Models\User;
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
                    $x->orWhere('responsible_it_id', $user->id);
                    $x->orWhereHas('services', fn ($sq) => $sq->visibleTo($user));
                });
            })
            ->with(['alertRecipients', 'groups', 'responsibleIt', 'salesContact'])
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
        abort_unless(auth()->user()->hasPermission('service_customers.create'), 403);

        return view('admin.service-customers.form', [
            'customer' => new ServiceCustomer(),
            'groups' => collect(),
            'itUsers' => $this->responsibleItUsers(),
            'salesUsers' => $this->salesUsers(),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('service_customers.create'), 403);

        $data = $this->validated($request);
        $data['responsible_it_id'] = $this->responsibleItIdForRequest($request);
        $data['sales_contact_id'] = $this->salesContactIdForRequest($request);

        $customer = ServiceCustomer::create($data);
        $this->syncResponsibleGroup($customer);
        $this->saveAlertRecipient($request, $customer);

        return redirect()->route('admin.service_customers.index')->with('success', 'Customer created.');
    }

    public function edit(ServiceCustomer $serviceCustomer)
    {
        abort_unless(auth()->user()->hasPermission('service_customers.edit'), 403);
        abort_unless($this->canAccess($serviceCustomer), 403);

        $serviceCustomer->load(['alertRecipients', 'groups', 'responsibleIt', 'salesContact']);

        return view('admin.service-customers.form', [
            'customer' => $serviceCustomer,
            'groups' => collect(),
            'itUsers' => $this->responsibleItUsers(),
            'salesUsers' => $this->salesUsers(),
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
            $data['responsible_it_id'] = $serviceCustomer->responsible_it_id ?: $user->id;
        }
        $data['sales_contact_id'] = $this->salesContactIdForRequest($request, $serviceCustomer);

        $serviceCustomer->update($data);
        $this->syncResponsibleGroup($serviceCustomer);
        $this->saveAlertRecipient($request, $serviceCustomer);

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

        return $user->isSuperAdmin()
            || (int) $customer->responsible_it_id === (int) $user->id
            || ($user->user_group_id && $customer->groups()->whereKey($user->user_group_id)->exists())
            || $customer->services()->visibleTo($user)->exists();
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

    private function salesUsers()
    {
        return User::query()
            ->where('is_active', true)
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

    private function salesContactIdForRequest(Request $request, ?ServiceCustomer $customer = null): ?int
    {
        $id = (int) $request->input('sales_contact_id', $customer?->sales_contact_id ?? 0);
        if ($id <= 0) {
            return null;
        }

        $sales = User::query()->whereKey($id)->where('is_active', true)->first();
        abort_unless($sales, 422, 'Đầu mối Sales không hợp lệ.');

        return $sales->id;
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
