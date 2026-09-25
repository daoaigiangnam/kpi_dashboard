<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceAlertEvent;
use App\Models\ServiceMonitorEvent;
use App\Services\ItTools\NetworkMonitoringService;
use App\Services\ItTools\ServiceAlertEmailService;
use App\Services\ItTools\ServiceAlertEngine;
use Illuminate\Http\Request;

class ServiceMonitoringController extends Controller
{
    public function index()
    {
        return $this->dashboard();
    }

    public function dashboard()
    {
        $visible = Service::visibleTo(auth()->user());

        $stats = [
            'total' => (clone $visible)->count(),
            'active' => (clone $visible)->where('status', 'active')->count(),
            'warning' => (clone $visible)->where('alert_stage', 1)->count(),
            'critical' => (clone $visible)->where('alert_stage', 2)->count(),
            'alert3' => (clone $visible)->where('alert_stage', 3)->count(),
            'expired' => (clone $visible)->where(function ($q) {
                $q->where('status', 'expired')->orWhere('alert_stage', 4);
            })->count(),
        ];

        $networkBase = Service::visibleTo(auth()->user())
            ->where('status', 'active')
            ->whereIn('monitor_check_method', ['ping', 'port'])
            ->whereNotNull('monitor_target');

        $networkStats = [
            'total' => (clone $networkBase)->count(),
            'online' => (clone $networkBase)->where('monitor_status', 'online')->count(),
            'offline' => (clone $networkBase)->where('monitor_status', 'offline')->count(),
            'unknown' => (clone $networkBase)->where(function ($q) {
                $q->whereNull('monitor_status')->orWhere('monitor_status', '');
            })->count(),
        ];

        $monitoredServices = (clone $networkBase)
            ->with(['customer', 'provider', 'serviceType', 'responsibleIt'])
            ->orderByRaw("CASE monitor_status WHEN 'offline' THEN 0 WHEN 'online' THEN 1 ELSE 2 END")
            ->orderBy('service_name')
            ->limit(100)
            ->get();

        $networkIncidents = ServiceMonitorEvent::query()
            ->whereHas('service', fn ($q) => $q->visibleTo(auth()->user()))
            ->with(['service.customer', 'service.provider', 'service.responsibleIt'])
            ->where('status', 'open')
            ->latest('started_at')
            ->limit(20)
            ->get();

        $upcoming = Service::visibleTo(auth()->user())
            ->with(['customer', 'serviceType', 'provider', 'alertPolicy', 'responsibleIt'])
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereBetween('alert_stage', [1, 3])
            ->orderBy('expiry_date')
            ->limit(20)
            ->get();

        $openAlerts = ServiceAlertEvent::query()
            ->whereHas('service', fn ($q) => $q->visibleTo(auth()->user()))
            ->with(['service.customer', 'service.serviceType', 'service.responsibleIt', 'alertPolicy'])
            ->whereIn('status', ['open', 'acknowledged'])
            ->latest('triggered_at')
            ->limit(20)
            ->get();

        return view('admin.service-monitoring.dashboard', compact('stats', 'networkStats', 'monitoredServices', 'networkIncidents', 'upcoming', 'openAlerts'));
    }

    public function test(Service $service, NetworkMonitoringService $networkMonitor)
    {
        abort_unless($service->isVisibleTo(auth()->user()), 403);

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

    public function run(Request $request, ServiceAlertEngine $engine, ServiceAlertEmailService $emailService, NetworkMonitoringService $networkMonitor)
    {
        $limit = max(1, min((int) $request->input('limit', 500), 5000));
        $evaluated = 0;
        $created = 0;
        $networkChecked = 0;
        $networkOffline = 0;

        Service::query()
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereNotNull('alert_policy_id')
            ->with('alertPolicy')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Service $service) use ($engine, $emailService, &$evaluated, &$created) {
                $event = $engine->evaluate($service);
                $evaluated++;
                if ($event) {
                    $emailService->notifyNewAlert($event);
                    $created++;
                }
            });

        Service::query()
            ->where('status', 'active')
            ->whereIn('monitor_check_method', ['ping', 'port'])
            ->whereNotNull('monitor_target')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Service $service) use ($networkMonitor, &$networkChecked, &$networkOffline) {
                $result = $networkMonitor->monitor($service);
                if (!$result['checked']) return;
                $networkChecked++;
                if (!$result['online']) $networkOffline++;
            });

        return back()->with('success', "Monitoring completed. Expiry: {$evaluated} service(s), {$created} alert(s). Network: {$networkChecked} check(s), {$networkOffline} offline.");
    }
}
