<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceAlertPolicy;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceAlertPolicyController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $serviceTypeId = $request->integer('service_type_id');
        $showDeleted = $request->boolean('deleted');

        $policies = ($showDeleted ? ServiceAlertPolicy::withTrashed() : ServiceAlertPolicy::query())
            ->with('serviceType')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($serviceTypeId, fn ($q) => $q->where('service_type_id', $serviceTypeId))
            ->when($showDeleted, fn ($q) => $q->whereNotNull('deleted_at'))
            ->orderBy('service_type_id')->orderBy('name')
            ->paginate(20)->withQueryString();

        $serviceTypes = ServiceType::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.service-alert-policies.index', compact('policies', 'serviceTypes', 'search', 'serviceTypeId', 'showDeleted'));
    }

    public function create(Request $request)
    {
        $serviceTypes = ServiceType::query()->where('is_active', true)->with('terms')->orderBy('name')->get();
        $selectedServiceTypeId = $request->integer('service_type_id');

        return view('admin.service-alert-policies.form', compact('serviceTypes', 'selectedServiceTypeId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        ServiceAlertPolicy::create($data);

        return redirect()->route('admin.service_alert_policies.index')->with('success', 'Alert policy created.');
    }

    public function edit(ServiceAlertPolicy $serviceAlertPolicy)
    {
        $serviceTypes = ServiceType::query()->where('is_active', true)->with('terms')->orderBy('name')->get();

        return view('admin.service-alert-policies.form', [
            'policy' => $serviceAlertPolicy->load('serviceType'),
            'serviceTypes' => $serviceTypes,
            'selectedServiceTypeId' => $serviceAlertPolicy->service_type_id,
        ]);
    }

    public function update(Request $request, ServiceAlertPolicy $serviceAlertPolicy)
    {
        $data = $request->validate($this->rules($serviceAlertPolicy));
        $serviceAlertPolicy->update($data);

        return redirect()->route('admin.service_alert_policies.index')->with('success', 'Alert policy updated.');
    }

    public function destroy(ServiceAlertPolicy $serviceAlertPolicy)
    {
        $serviceAlertPolicy->delete();
        return back()->with('success', 'Alert policy deleted.');
    }

    public function restore(int $serviceAlertPolicy)
    {
        ServiceAlertPolicy::withTrashed()->findOrFail($serviceAlertPolicy)->restore();
        return back()->with('success', 'Alert policy restored.');
    }

    private function rules(?ServiceAlertPolicy $policy = null): array
    {
        return [
            'service_type_id' => ['required', 'integer', 'exists:service_types,id'],
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('service_alert_policies', 'name')
                    ->where(fn ($q) => $q->where('service_type_id', request('service_type_id')))
                    ->ignore($policy?->id),
            ],
            'alert_1_percent' => ['required', 'numeric', 'gt:0', 'lt:100'],
            'alert_2_percent' => ['required', 'numeric', 'gt:0', 'lt:100'],
            'alert_3_percent' => ['required', 'numeric', 'gt:0', 'lt:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
