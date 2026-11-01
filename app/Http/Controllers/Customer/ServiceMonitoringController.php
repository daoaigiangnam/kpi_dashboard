<?php

declare(strict_types=1);

namespace App\\Http\\Controllers\\Customer;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Service;
use App\\Models\\ServiceAlertEvent;
use App\\Models\\ServiceMonitorEvent;
use Illuminate\\Http\\Request;

class ServiceMonitoringController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isCustomerAccount() && $user->service_customer_id, 403);

        // Tenant boundary: customer portal only reads services belonging to the
        // authenticated customer's own service_customer_id.
        $servicesQuery = Service::query()
            ->where('customer_id', $user->service_customer_id)
            ->with(['serviceType', 'provider'])
            ->orderBy('service_name');

        $services = $servicesQuery->get();

        $stats = [
            'total' => $services->count(),
            'online' => $services->where('monitor_status', 'online')->count(),
            'offline' => $services->where('monitor_status', 'offline')->count(),
            'unknown' => $services->filter(fn ($s) => in_array($s->monitor_status, [null, '', 'pending', 'unknown'], true))->count(),
            'not_configured' => $services->filter(fn ($s) => !in_array($s->monitor_check_method, ['ping', 'port'], true) || !$s->monitor_target)->count(),
        ];

        $networkIncidents = ServiceMonitorEvent::query()
            ->whereHas('service', fn ($q) => $q->where('customer_id', $user->service_customer_id))
            ->with('service:id,service_name,customer_id')
            ->latest('started_at')
            ->limit(50)
            ->get();

        $serviceAlerts = ServiceAlertEvent::query()
            ->whereHas('service', fn ($q) => $q->where('customer_id', $user->service_customer_id))
            ->with('service:id,service_name,customer_id')
            ->whereIn('status', ['open', 'acknowledged'])
            ->latest('triggered_at')
            ->limit(50)
            ->get();

        return view('customer.service-monitoring.index', compact(
            'services', 'stats', 'networkIncidents', 'serviceAlerts'
        ));
    }
}
