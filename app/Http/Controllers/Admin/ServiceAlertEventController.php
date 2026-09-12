<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceAlertEvent;
use Illuminate\Http\Request;

class ServiceAlertEventController extends Controller
{
    public function index(Request $request)
    {
        $status = trim((string) $request->query('status', ''));
        $events = ServiceAlertEvent::query()
            ->with(['service', 'alertPolicy'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest('triggered_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.service-alert-events.index', compact('events', 'status'));
    }

    public function acknowledge(ServiceAlertEvent $serviceAlertEvent)
    {
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

    public function resolve(ServiceAlertEvent $serviceAlertEvent)
    {
        if ($serviceAlertEvent->status === 'resolved') {
            return back()->with('info', 'Alert is already resolved.');
        }

        $serviceAlertEvent->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        return back()->with('success', 'Alert resolved.');
    }
}
