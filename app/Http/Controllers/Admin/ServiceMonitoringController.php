<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceAlertEvent;
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
        $base = Service::query();
        $stats = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'warning' => (clone $base)->where('alert_stage', 1)->count(),
            'critical' => (clone $base)->where('alert_stage', 2)->count(),
            'alert3' => (clone $base)->where('alert_stage', 3)->count(),
            'expired' => (clone $base)->where('status', 'expired')->orWhere('alert_stage', 4)->count(),
        ];

        // "Upcoming Expiry" is an action list, not a list of all services.
        // Only services that have entered an active alert stage (1-3) are shown.
        // Normal services (alert_stage = 0) must not appear here.
        $upcoming = Service::query()
            ->with(['customer', 'serviceType', 'provider', 'alertPolicy'])
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereBetween('alert_stage', [1, 3])
            ->orderBy('expiry_date')
            ->limit(20)
            ->get();

        $openAlerts = ServiceAlertEvent::query()
            ->with(['service.customer', 'service.serviceType', 'alertPolicy'])
            ->whereIn('status', ['open', 'acknowledged'])
            ->latest('triggered_at')
            ->limit(20)
            ->get();

        return view('admin.service-monitoring.dashboard', compact('stats', 'upcoming', 'openAlerts'));
    }

    public function run(Request $request, ServiceAlertEngine $engine, ServiceAlertEmailService $emailService)
    {
        $limit = max(1, min((int) $request->input('limit', 500), 5000));
        $evaluated = 0;
        $created = 0;

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

        return back()->with('success', "Monitoring completed. Evaluated {$evaluated} service(s), created {$created} new alert(s).");
    }
}
