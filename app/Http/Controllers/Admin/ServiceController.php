<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceAlertPolicy;
use App\Models\ServiceCustomer;
use App\Models\ServiceProvider;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $showDeleted = $request->boolean('deleted');

        $services = ($showDeleted ? Service::withTrashed() : Service::query())
            ->with(['customer', 'serviceType', 'provider', 'alertPolicy', 'responsibleIt'])
            ->when($search !== '', fn ($q) => $q->where(fn ($x) =>
                $x->where('service_name', 'like', "%{$search}%")
                    ->orWhere('value', 'like', "%{$search}%")
            ))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($showDeleted, fn ($q) => $q->whereNotNull('deleted_at'))
            ->orderByRaw('expiry_date IS NULL, expiry_date')
            ->paginate(25)
            ->withQueryString();

        return view('admin.services.index', compact('services', 'search', 'status', 'showDeleted'));
    }

    public function create()
    {
        return view('admin.services.form', [
            'service' => new Service(['status' => 'active', 'auto_renew' => false]),
            ...$this->formData(),
        ]);
    }

    public function store(Request $request)
    {
        Service::create($this->validated($request));
        return redirect()->route('admin.services.index')->with('success', 'Service created.');
    }

    public function edit(Service $service)
    {
        return view('admin.services.form', [
            'service' => $service->load(['customer', 'serviceType', 'provider', 'alertPolicy', 'responsibleIt']),
            ...$this->formData($service),
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $service->update($this->validated($request, $service));
        return redirect()->route('admin.services.index')->with('success', 'Service updated.');
    }

    public function destroy(Service $service) { $service->delete(); return back()->with('success', 'Service deleted.'); }
    public function restore(int $service) { Service::withTrashed()->findOrFail($service)->restore(); return back()->with('success', 'Service restored.'); }

    private function formData(?Service $service = null): array
    {
        $serviceTypes = ServiceType::query()->where('is_active', true)->with('terms')->orderBy('name')->get();
        return [
            'customers' => ServiceCustomer::query()->where('is_active', true)->orderBy('name')->get(),
            'providers' => ServiceProvider::query()->where('is_active', true)->orderBy('name')->get(),
            'serviceTypes' => $serviceTypes,
            'policies' => ServiceAlertPolicy::query()->where('is_active', true)->orderBy('name')->get(),
            'responsibleUsers' => User::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?Service $service = null): array
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('service_customers', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))],
            'service_type_id' => ['required', 'integer', Rule::exists('service_types', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))],
            'provider_id' => ['nullable', 'integer', Rule::exists('service_providers', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))],
            'service_name' => ['required', 'string', 'max:190'],
            'value' => ['nullable', 'string', 'max:500'],
            'service_term_months' => ['nullable', 'integer', Rule::in([1,3,6,9,12,24])],
            'expiry_date' => ['nullable', 'date'],
            'alert_policy_id' => ['nullable', 'integer', Rule::exists('service_alert_policies', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))],
            'responsible_it_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['required', 'in:active,suspended,expired'],
            'auto_renew' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $hasExpiry = !empty($data['expiry_date']);
        if ($hasExpiry && empty($data['service_term_months'])) {
            abort(422, 'Service Term is required when Expiry Date is set.');
        }
        if ($hasExpiry && empty($data['alert_policy_id'])) {
            abort(422, 'Alert Policy is required when Expiry Date is set.');
        }

        $type = ServiceType::with('terms')->findOrFail($data['service_type_id']);
        if (!empty($data['service_term_months']) && !$type->terms->pluck('months')->contains((int) $data['service_term_months'])) {
            abort(422, 'Selected service term is not allowed for this Service Type.');
        }

        if (!empty($data['alert_policy_id'])) {
            $policy = ServiceAlertPolicy::findOrFail($data['alert_policy_id']);
            if ((int) $policy->service_type_id !== (int) $data['service_type_id']) {
                abort(422, 'Selected Alert Policy does not belong to the selected Service Type.');
            }
        }

        return $data;
    }
}
