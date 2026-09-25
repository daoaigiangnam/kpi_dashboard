<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceAlertPolicy;
use App\Models\ServiceCustomer;
use App\Models\ServiceProvider;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\ItTools\DomainAuditService;
use App\Services\ItTools\NetworkMonitoringService;
use App\Services\ItTools\ServiceAlertEmailService;
use App\Services\ItTools\ServiceAlertEngine;
use Carbon\Carbon;
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
            ->when($search !== '', fn ($q) => $q->where(fn ($x) => $x
                ->where('service_name', 'like', "%{$search}%")
                ->orWhere('value', 'like', "%{$search}%")
                ->orWhere('monitor_target', 'like', "%{$search}%")
            ))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($showDeleted, fn ($q) => $q->whereNotNull('deleted_at'))
            ->orderByRaw('expiry_date IS NULL, expiry_date')
            ->paginate(25)
            ->withQueryString();

        return view('admin.services.index', compact('services', 'search', 'status', 'showDeleted'));
    }

    public function exportDetails(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $services = Service::query()
            ->with(['customer', 'serviceType', 'provider', 'alertPolicy', 'responsibleIt'])
            ->when($search !== '', fn ($q) => $q->where(fn ($x) => $x
                ->where('service_name', 'like', "%{$search}%")
                ->orWhere('value', 'like', "%{$search}%")
                ->orWhere('monitor_target', 'like', "%{$search}%")
            ))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderBy('customer_id')
            ->orderBy('service_name')
            ->get();

        $filename = 'services_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($services) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Customer', 'Service Name', 'Service Type', 'Value', 'Provider',
                'Cost', 'Currency', 'Billing Cycle', 'Term Months', 'Expiry Date',
                'Alert Policy', 'Responsible IT', 'Status', 'Auto Renew',
                'Monitor Target', 'Check Method', 'Check Port', 'Interval Seconds',
                'Timeout Seconds', 'Monitor Status', 'Latency ms', 'Packet Loss %',
                'Failure Count', 'Last Checked At', 'Down Since', 'Note',
            ]);

            foreach ($services as $service) {
                fputcsv($out, [
                    $service->customer?->name,
                    $service->service_name,
                    $service->serviceType?->name,
                    $service->value,
                    $service->provider?->name,
                    $service->cost_amount,
                    $service->cost_currency,
                    $service->cost_billing_cycle,
                    $service->service_term_months,
                    optional($service->expiry_date)->format('Y-m-d'),
                    $service->alertPolicy?->name,
                    $service->responsibleIt?->name,
                    $service->status,
                    $service->auto_renew ? 'Yes' : 'No',
                    $service->monitor_target,
                    $service->monitor_check_method,
                    $service->monitor_port,
                    $service->monitor_interval_seconds,
                    $service->monitor_timeout_seconds,
                    $service->monitor_status,
                    $service->monitor_last_latency_ms,
                    $service->monitor_packet_loss_percent,
                    $service->monitor_failure_count,
                    optional($service->monitor_last_checked_at)?->format('Y-m-d H:i:s'),
                    optional($service->monitor_down_since)?->format('Y-m-d H:i:s'),
                    $service->note,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function detectExpiry(Service $service, DomainAuditService $domainAudit)
    {
        $service->load('serviceType');
        if (strtoupper((string) $service->serviceType?->code) !== 'DOMAIN') {
            return response()->json(['ok' => false, 'message' => 'Detect Expiry is available only for Domain services.'], 422);
        }

        $domain = trim((string) $service->value);
        if ($domain === '') {
            return response()->json(['ok' => false, 'message' => 'Domain value is empty.'], 422);
        }

        $result = $domainAudit->check($domain);
        if (empty($result['expires_at'])) {
            return response()->json(['ok' => false, 'result' => $result], 422);
        }

        try {
            $expiry = Carbon::parse($result['expires_at']);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'Detected expiry date could not be parsed.'], 422);
        }

        $service->update(['expiry_date' => $expiry->toDateString()]);
        $result['saved_expiry_date'] = $expiry->toDateString();

        return response()->json(['ok' => true, 'result' => $result]);
    }

    public function create()
    {
        return view('admin.services.form', [
            'service' => new Service([
                'status' => 'active',
                'auto_renew' => false,
                'monitor_interval_seconds' => 60,
                'monitor_timeout_seconds' => 5,
                'cost_currency' => 'VND',
                'cost_billing_cycle' => 'monthly',
            ]),
            ...$this->formData(),
        ]);
    }

    public function store(Request $request, ServiceAlertEngine $engine, ServiceAlertEmailService $emailService)
    {
        $service = Service::create($this->validated($request));
        $event = $engine->evaluate($service->load('alertPolicy'));
        if ($event) $emailService->notifyNewAlert($event);
        return redirect()->route('admin.services.index')->with('success', 'Service created.');
    }

    public function edit(Request $request, Service $service, NetworkMonitoringService $networkMonitor)
    {
        if ($request->boolean('network_test')) {
            if ($service->status !== 'active' || !in_array($service->monitor_check_method, ['ping', 'port'], true) || !$service->monitor_target) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Monitoring is not configured. Please set WAN IP / Monitor Target and Check Method first.',
                ], 422);
            }

            $result = $networkMonitor->test($service->refresh());

            return response()->json([
                'ok' => (bool) ($result['online'] ?? false),
                'result' => $result,
                'message' => $result['online']
                    ? 'Connection test successful.'
                    : 'Connection test failed. The service is currently OFFLINE.',
            ], $result['online'] ? 200 : 503);
        }

        return view('admin.services.form', [
            'service' => $service->load(['customer', 'serviceType', 'provider', 'alertPolicy', 'responsibleIt']),
            ...$this->formData($service),
        ]);
    }

    public function update(Request $request, Service $service, ServiceAlertEngine $engine, ServiceAlertEmailService $emailService)
    {
        $service->update($this->validated($request, $service));
        $service->refresh()->load('alertPolicy');
        $event = $engine->evaluate($service);
        if ($event) $emailService->notifyNewAlert($event);
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
            'cost_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'cost_currency' => ['required', 'string', 'size:3'],
            'cost_billing_cycle' => ['required', Rule::in(['monthly', 'quarterly', 'yearly', 'one_time'])],
            'service_term_months' => ['nullable', 'integer', Rule::in([1,3,6,9,12,24])],
            'expiry_date' => ['nullable', 'date'],
            'alert_policy_id' => ['nullable', 'integer', Rule::exists('service_alert_policies', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))],
            'responsible_it_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['required', 'in:active,suspended,expired'],
            'auto_renew' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
            'monitor_check_method' => ['nullable', Rule::in(['ping', 'port'])],
            'monitor_target' => ['nullable', 'string', 'max:255'],
            'monitor_port' => ['nullable', 'integer', 'between:1,65535'],
            'monitor_interval_seconds' => ['nullable', 'integer', 'between:30,86400'],
            'monitor_timeout_seconds' => ['nullable', 'integer', 'between:1,60'],
        ]);

        $hasExpiry = !empty($data['expiry_date']);
        if ($hasExpiry && empty($data['service_term_months'])) abort(422, 'Service Term is required when Expiry Date is set.');
        if ($hasExpiry && empty($data['alert_policy_id'])) abort(422, 'Alert Policy is required when Expiry Date is set.');

        $type = ServiceType::with('terms')->findOrFail($data['service_type_id']);
        if (!empty($data['service_term_months']) && !$type->terms->pluck('months')->contains((int) $data['service_term_months'])) abort(422, 'Selected service term is not allowed for this Service Type.');

        if (!empty($data['alert_policy_id'])) {
            $policy = ServiceAlertPolicy::findOrFail($data['alert_policy_id']);
            if ((int) $policy->service_type_id !== (int) $data['service_type_id']) abort(422, 'Selected Alert Policy does not belong to the selected Service Type.');
        }

        $isInternet = strtoupper((string) $type->code) === 'INTERNET';
        if (!$isInternet) {
            $data['monitor_check_method'] = null;
            $data['monitor_target'] = null;
            $data['monitor_port'] = null;
        } else {
            $method = $data['monitor_check_method'] ?? null;
            if ($method && empty($data['monitor_target'])) abort(422, 'WAN IP / Monitor Target is required when monitoring is enabled.');
            if ($method === 'port' && empty($data['monitor_port'])) abort(422, 'Monitor Port is required when Check Method is Port.');
            if (!$method) {
                $data['monitor_target'] = null;
                $data['monitor_port'] = null;
            } elseif ($method !== 'port') {
                $data['monitor_port'] = null;
            }
            $data['monitor_interval_seconds'] = $data['monitor_interval_seconds'] ?? 60;
            $data['monitor_timeout_seconds'] = $data['monitor_timeout_seconds'] ?? 5;
        }

        return $data;
    }
}
