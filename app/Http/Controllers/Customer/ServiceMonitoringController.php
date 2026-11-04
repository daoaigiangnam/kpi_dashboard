<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceAlertEvent;
use App\Models\ServiceMonitorEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceMonitoringController extends Controller
{
    /**
     * Customer-facing, read-only monitoring dashboard.
     *
     * The query is always scoped to the authenticated customer's own services.
     * This does not reuse the Admin controller, which contains administrative
     * repair and monitoring actions.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless(
            $user && $user->isCustomerAccount() && $user->service_customer_id,
            403
        );

        $servicesQuery = Service::query()
            ->where('customer_id', $user->service_customer_id);

        $configured = static function ($query): void {
            $query->whereIn('monitor_check_method', ['ping', 'port'])
                ->whereNotNull('monitor_target')
                ->where('monitor_target', '<>', '');
        };

        $stats = [
            'total' => (clone $servicesQuery)->count(),
            'online' => (clone $servicesQuery)
                ->where($configured)
                ->where('monitor_status', 'online')
                ->count(),
            'offline' => (clone $servicesQuery)
                ->where($configured)
                ->where('monitor_status', 'offline')
                ->count(),
            'unknown' => (clone $servicesQuery)
                ->where($configured)
                ->where(function ($query): void {
                    $query->whereNull('monitor_status')
                        ->orWhere('monitor_status', '')
                        ->orWhereIn('monitor_status', ['pending', 'unknown']);
                })
                ->count(),
            'not_configured' => (clone $servicesQuery)
                ->where(function ($query): void {
                    $query->whereNotIn('monitor_check_method', ['ping', 'port'])
                        ->orWhereNull('monitor_check_method')
                        ->orWhereNull('monitor_target')
                        ->orWhere('monitor_target', '');
                })
                ->count(),
        ];

        $services = (clone $servicesQuery)
            ->with(['serviceType', 'provider'])
            ->orderBy('service_name')
            ->limit(1000)
            ->get();

        $serviceIds = (clone $servicesQuery)->select('id');

        $networkIncidents = ServiceMonitorEvent::query()
            ->whereIn('service_id', $serviceIds)
            ->with('service:id,service_name')
            ->where('status', 'open')
            ->latest('started_at')
            ->limit(50)
            ->get();

        $serviceAlerts = ServiceAlertEvent::query()
            ->whereIn('service_id', $serviceIds)
            ->with('service:id,service_name')
            ->whereIn('status', ['open', 'acknowledged'])
            ->latest('triggered_at')
            ->limit(50)
            ->get();

        return view('customer.service-monitoring.index', compact(
            'stats',
            'services',
            'networkIncidents',
            'serviceAlerts'
        ));
    }
}
