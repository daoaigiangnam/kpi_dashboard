<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceAlertEvent;
use App\Services\ItTools\ServiceAlertEmailService;
use Illuminate\Http\Request;

class ServiceAlertEventController extends Controller
{
    public function index(Request $request)
    {
        $status = trim((string) $request->query('status', 'current'));
        $events = ServiceAlertEvent::query()
            ->whereHas('service', fn ($q) => $q->visibleTo(auth()->user()))
            ->with(['service', 'alertPolicy'])
            ->when($status === 'current', fn ($q) => $q->whereIn('status', ['open', 'acknowledged']))
            ->when(in_array($status, ['open', 'acknowledged', 'resolved'], true), fn ($q) => $q->where('status', $status))
            ->latest('triggered_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.service-alert-events.index', compact('events', 'status'));
    }

    public function acknowledge(ServiceAlertEvent $serviceAlertEvent)
    {
        abort_unless($serviceAlertEvent->service()->first()?->isVisibleTo(auth()->user()), 403);

        if ($serviceAlertEvent->status !== 'open') {
            return back()->with('info', 'Alert is already acknowledged or resolved.');
        }

        $serviceAlertEvent->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => auth()->id(),
        ]);

        return back()->with('success', 'Alert acknowledged.');
    }

    public function resolve(ServiceAlertEvent $serviceAlertEvent, ServiceAlertEmailService $emailService)
    {
        abort_unless($serviceAlertEvent->service()->first()?->isVisibleTo(auth()->user()), 403);

        if ($serviceAlertEvent->status === 'resolved') {
            return back()->with('info', 'Alert is already resolved.');
        }

        $serviceAlertEvent->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        /*
         * A manually resolved EXPIRED service-expiry alert means the current
         * incident has been handled. Reset the service to the normal monitoring
         * state so the next scheduled monitoring cycle can evaluate it again.
         *
         * Do NOT reset stages 1-3: resolving a warning should not immediately
         * cause the same warning to be generated again on every scan.
         */
        if (
            $serviceAlertEvent->alert_type === 'service_expiry'
            && (int) $serviceAlertEvent->alert_stage === 4
        ) {
            $service = $serviceAlertEvent->service()->first();

            if ($service) {
                $service->update([
                    'status' => 'active',
                    'alert_stage' => 0,
                    'last_alert_at' => null,
                ]);

                // Close any other currently-open Stage-4 service-expiry alerts
                // for the same service so the resolved incident disappears
                // from all "current alert" views immediately.
                ServiceAlertEvent::query()
                    ->where('service_id', $service->id)
                    ->where('alert_type', 'service_expiry')
                    ->where('alert_stage', 4)
                    ->whereIn('status', ['open', 'acknowledged'])
                    ->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'resolved_by' => auth()->id(),
                        'note' => 'Closed together with the resolved expired-service alert.',
                    ]);
            }
        }

        $emailService->notifyResolved($serviceAlertEvent);

        return back()->with('success', 'Alert resolved and resolution report sent.');
    }
}
