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
    public function index(Request $request): View
    {
        $user = $request->user();

        // Customer portal is read-only and must never expose another customer's data.
        abort_unless($user && $user->isCustomerAccount() && $user->service_customer_id, 403);

        $customerId = (int) $user->service_customer_id;

        $servicesQuery = Service::query()
            ->where('customer_id', $customerId);

        $configured = static function ($query): void {
            $query->whereIn('monitor_check_method', ['ping', 'port'])
                ->whereNotNull('monitor_target')
                ->where('monitor_target', '<>', '');
        };

        $stats = [
            'total' => (clone $servicesQuery)->count(),
            'online' => (clone $servicesQuery)->where($configured)->where('monitor_status', 'online')->count(),
            'offline' => (clone $servicesQuery)->where($configured)->where('monitor_status', 'offline')->count(),
            'unknown' => (clone $servicesQuery)->where($configured)->where(function ($query): void {
                $query->whereNull('monitor_status')
                    ->orWhere('monitor_status', '')
                    ->orWhereIn('monitor_status', ['pending', 'unknown']);
            })->count(),
            'not_configured' => (clone $servicesQuery)->where(function ($query): void {
                $query->whereNull('monitor_check_method')
                    ->orWhereNotIn('monitor_check_method', ['ping', 'port'])
                    ->orWhereNull('monitor_target')
                    ->orWhere('monitor_target', '');
            })->count(),
        ];

        $services = (clone $servicesQuery)
            ->with(['serviceType', 'provider'])
            ->orderBy('service_name')
            ->limit(1000)
            ->get();

        $customerServiceIds = (clone $servicesQuery)->select('id');

        $networkIncidents = ServiceMonitorEvent::query()
            ->whereIn('service_id', $customerServiceIds)
            ->with('service:id,service_name,customer_id')
            ->latest('started_at')
            ->limit(50)
            ->get();

        $serviceAlerts = ServiceAlertEvent::query()
            ->whereIn('service_id', $customerServiceIds)
            ->with('service:id,service_name,customer_id')
            ->whereIn('status', ['open', 'acknowledged'])
            ->latest('triggered_at')
            ->limit(50)
            ->get();

        return view('customer.service-monitoring.index', [
            'services' => $services,
            'stats' => $stats,
            'networkIncidents' => $networkIncidents,
            'serviceAlerts' => $serviceAlerts,
        ]);
    }
}
