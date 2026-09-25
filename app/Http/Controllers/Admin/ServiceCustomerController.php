<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCustomer;
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
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->whereHas('services', fn ($sq) => $sq->visibleTo($user)))
            ->with('alertRecipients')
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
        return view('admin.service-customers.form', ['customer' => new ServiceCustomer()]);
    }

    public function store(Request $request)
    {
        $customer = ServiceCustomer::create($this->validated($request));
        $this->saveAlertRecipient($request, $customer);
        return redirect()->route('admin.service_customers.index')->with('success', 'Customer created.');
    }

    public function edit(ServiceCustomer $serviceCustomer)
    {
        abort_unless($this->canAccess($serviceCustomer), 403);
        $serviceCustomer->load('alertRecipients');
        return view('admin.service-customers.form', ['customer' => $serviceCustomer]);
    }

    public function update(Request $request, ServiceCustomer $serviceCustomer)
    {
        abort_unless($this->canAccess($serviceCustomer), 403);
        $serviceCustomer->update($this->validated($request, $serviceCustomer));
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
            || $customer->services()->visibleTo($user)->exists();
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
