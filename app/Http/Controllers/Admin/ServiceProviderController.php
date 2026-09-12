<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceProviderController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $showDeleted = $request->boolean('deleted');
        $providers = ($showDeleted ? ServiceProvider::withTrashed() : ServiceProvider::query())
            ->when($search !== '', fn ($q) => $q->where(fn ($x) => $x->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->when($showDeleted, fn ($q) => $q->whereNotNull('deleted_at'))
            ->orderBy('name')->paginate(20)->withQueryString();
        return view('admin.service-providers.index', compact('providers', 'search', 'showDeleted'));
    }

    public function create() { return view('admin.service-providers.form', ['provider' => new ServiceProvider()]); }

    public function store(Request $request)
    {
        ServiceProvider::create($this->validated($request));
        return redirect()->route('admin.service_providers.index')->with('success', 'Provider created.');
    }

    public function edit(ServiceProvider $serviceProvider)
    {
        return view('admin.service-providers.form', ['provider' => $serviceProvider]);
    }

    public function update(Request $request, ServiceProvider $serviceProvider)
    {
        $serviceProvider->update($this->validated($request, $serviceProvider));
        return redirect()->route('admin.service_providers.index')->with('success', 'Provider updated.');
    }

    public function destroy(ServiceProvider $serviceProvider) { $serviceProvider->delete(); return back()->with('success', 'Provider deleted.'); }
    public function restore(int $serviceProvider) { ServiceProvider::withTrashed()->findOrFail($serviceProvider)->restore(); return back()->with('success', 'Provider restored.'); }

    private function validated(Request $request, ?ServiceProvider $provider = null): array
    {
        return $request->validate([
            'code' => ['required','string','max:50','alpha_dash',Rule::unique('service_providers','code')->ignore($provider?->id)],
            'name' => ['required','string','max:150'],
            'website' => ['nullable','url','max:255'],
            'support_contact' => ['nullable','string','max:255'],
            'is_active' => ['nullable','boolean'],
        ]);
    }
}
