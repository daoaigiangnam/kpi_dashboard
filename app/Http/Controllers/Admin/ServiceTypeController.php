<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceTypeController extends Controller
{
    private const TERMS = [1, 3, 6, 9, 12, 24];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $showDeleted = $request->boolean('deleted');

        $serviceTypes = ($showDeleted ? ServiceType::withTrashed() : ServiceType::query())
            ->with('terms')
            ->when($search !== '', fn ($q) => $q->where(fn ($x) => $x->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
            ->when($showDeleted, fn ($q) => $q->whereNotNull('deleted_at'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.service-types.index', compact('serviceTypes', 'search', 'showDeleted'));
    }

    public function create()
    {
        return view('admin.service-types.form', ['serviceType' => new ServiceType(), 'selectedTerms' => []]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $terms = $data['terms'] ?? [];
        unset($data['terms']);

        $serviceType = ServiceType::create($data);
        $serviceType->terms()->createMany(array_map(fn ($months) => ['months' => (int) $months], $terms));

        return redirect()->route('admin.service_types.index')->with('success', 'Service type created.');
    }

    public function edit(ServiceType $serviceType)
    {
        return view('admin.service-types.form', [
            'serviceType' => $serviceType->load('terms'),
            'selectedTerms' => $serviceType->terms->pluck('months')->map(fn ($v) => (int) $v)->all(),
        ]);
    }

    public function update(Request $request, ServiceType $serviceType)
    {
        $data = $request->validate($this->rules($serviceType));
        $terms = $data['terms'] ?? [];
        unset($data['terms']);

        $serviceType->update($data);
        $serviceType->terms()->delete();
        $serviceType->terms()->createMany(array_map(fn ($months) => ['months' => (int) $months], $terms));

        return redirect()->route('admin.service_types.index')->with('success', 'Service type updated.');
    }

    public function destroy(ServiceType $serviceType)
    {
        $serviceType->delete();
        return back()->with('success', 'Service type deleted.');
    }

    public function restore(int $serviceType)
    {
        ServiceType::withTrashed()->findOrFail($serviceType)->restore();
        return back()->with('success', 'Service type restored.');
    }

    private function rules(?ServiceType $serviceType = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('service_types', 'code')->ignore($serviceType?->id)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'terms' => ['required', 'array', 'min:1'],
            'terms.*' => ['integer', Rule::in(self::TERMS)],
        ];
    }
}
