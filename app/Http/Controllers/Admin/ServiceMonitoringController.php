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

        // Repair stale EXPIRED flags left by an older resolve flow. If a
        // service is marked expired but has no current Stage-4 alert, the
        // alert was already resolved and the service must be re-armed as
        // ACTIVE so the next evaluation can detect the expiry again.
        Service::query()
            ->where('status', 'expired')
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhereDoesntHave('alertEvents', function ($event) {
                        $event->where('alert_type', 'service_expiry')
                            ->where('alert_stage', 4)
                            ->whereIn('status', ['open', 'acknowledged']);
                    });
            })
            ->update([
                'status' => 'active',
                'alert_stage' => 0,
                'last_alert_at' => null,
            ]);

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

        /*
         * Monitoring inventory must NOT be filtered by service expiry.
         *
         * A service can be:
         * - expired but still physically/network reachable;
         * - active with an expiry date;
         * - cancelled/inactive but retained for historical/reference purposes.
         *
         * The Monitoring page is an inventory/observability view, so show every
         * visible service that has monitoring configured. Expiry is an alert
         * dimension, not a visibility condition for the monitoring row.
         */
        $networkBase = Service::visibleTo(auth()->user())
            ->whereIn('monitor_check_method', ['ping', 'port'])
            ->whereNotNull('monitor_target')
            ->where('monitor_target', '!=', '');

        $networkStats = [
            'total' => (clone $networkBase)->count(),
            'online' => (clone $networkBase)->where('monitor_status', 'online')->count(),
            'offline' => (clone $networkBase)->where('monitor_status', 'offline')->count(),
            'unknown' => (clone $networkBase)->where(function ($q) {
                $q->whereNull('monitor_status')
                    ->orWhere('monitor_status', '')
                    ->orWhere('monitor_status', 'pending');
            })->count(),
        ];

        $monitoredServices = (clone $networkBase)
            ->with(['customer', 'provider', 'serviceType', 'responsibleIt'])
            ->orderByRaw("CASE monitor_status
                WHEN 'offline' THEN 0
                WHEN 'unknown' THEN 1
                WHEN 'pending' THEN 1
                WHEN 'online' THEN 2
                ELSE 3
            END")
            ->orderBy('service_name')
            ->limit(500)
            ->get();

        $networkIncidents = ServiceMonitorEvent::query()
            ->whereHas('service', fn ($q) => $q->visibleTo(auth()->user()))
            ->with([
                'service.customer',
                'service.provider',
                'service.responsibleIt',
                'service.serviceType',
            ])
            ->where('status', 'open')
            ->latest('started_at')
            ->limit(50)
            ->get();

        /*
         * Keep expiry reporting separate from network monitoring.
         * Only services with an expiry date are relevant here.
         */
        // Show only currently active expiry alerts. Once an alert is
        // resolved, the service disappears from this warning list immediately.
        $upcoming = Service::visibleTo(auth()->user())
            ->with([
                'customer',
                'serviceType',
                'provider',
                'alertPolicy',
                'responsibleIt',
            ])
            ->whereNotNull('expiry_date')
            ->whereBetween('alert_stage', [1, 4])
            ->whereHas('alertEvents', function ($q) {
                $q->where('alert_type', 'service_expiry')
                    ->whereIn('status', ['open', 'acknowledged']);
            })
            ->orderBy('expiry_date')
            ->limit(50)
            ->get();

        $openAlerts = ServiceAlertEvent::query()
            ->whereHas('service', fn ($q) => $q->visibleTo(auth()->user()))
            ->with([
                'service.customer',
                'service.serviceType',
                'service.responsibleIt',
                'alertPolicy',
            ])
            ->whereIn('status', ['open', 'acknowledged'])
            ->latest('triggered_at')
            ->limit(50)
            ->get();

        return view(
            'admin.service-monitoring.dashboard',
            compact(
                'stats',
                'networkStats',
                'monitoredServices',
                'networkIncidents',
                'upcoming',
                'openAlerts'
            )
        );
    }

    public function test(Service $service, NetworkMonitoringService $networkMonitor)
    {
        abort_unless($service->isVisibleTo(auth()->user()), 403);

        if (!in_array($service->monitor_check_method, ['ping', 'port'], true) || !$service->monitor_target) {
            return response()->json([
                'ok' => false,
                'message' => 'Monitoring is not configured. Please set Monitor Target and Check Method first.',
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

    public function run(
        Request $request,
        ServiceAlertEngine $engine,
        ServiceAlertEmailService $emailService,
        NetworkMonitoringService $networkMonitor
    ) {
        $limit = max(1, min((int) $request->input('limit', 500), 5000));

        $evaluated = 0;
        $created = 0;
        $networkChecked = 0;
        $networkOffline = 0;

        /*
         * Expiry/SSL alert evaluation remains independent of monitoring
         * visibility. Only evaluate services that actually have the relevant
         * expiry/SSL data and alert policy.
         */
        Service::query()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where(function ($x) {
                    $x->whereNotNull('expiry_date')
                        ->whereNotNull('alert_policy_id');
                })->orWhere(function ($x) {
                    $x->where('ssl_detected', true)
                        ->whereNotNull('ssl_expiry_date')
                        ->whereNotNull('alert_policy_id');
                });
            })
            ->with('alertPolicy')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Service $service) use (
                $engine,
                $emailService,
                &$evaluated,
                &$created
            ) {
                $event = $engine->evaluate($service);
                $evaluated++;

                if ($event) {
                    $emailService->notifyNewAlert($event);
                    $created++;
                }

                $sslEvent = $engine->evaluateSsl($service);

                if ($sslEvent) {
                    $emailService->notifyNewAlert($sslEvent);
                    $created++;
                }
            });

        /*
         * Network monitoring must also NOT require status=active.
         * Expired services can remain technically online and should continue
         * to appear in Monitoring until monitoring is explicitly disabled.
         */
        Service::query()
            ->whereIn('monitor_check_method', ['ping', 'port'])
            ->whereNotNull('monitor_target')
            ->where('monitor_target', '!=', '')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Service $service) use (
                $networkMonitor,
                &$networkChecked,
                &$networkOffline
            ) {
                $result = $networkMonitor->monitor($service);

                if (!$result['checked']) {
                    return;
                }

                $networkChecked++;

                if (!$result['online']) {
                    $networkOffline++;
                }
            });

        return back()->with(
            'success',
            "Monitoring completed. Expiry/SSL: {$evaluated} service(s), {$created} alert(s). Network: {$networkChecked} check(s), {$networkOffline} offline."
        );
    }
}
