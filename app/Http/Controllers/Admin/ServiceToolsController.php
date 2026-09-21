<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\ItTools\DomainAuditService;
use Illuminate\Http\Request;

class ServiceToolsController extends Controller
{
    public function detectDomainExpiry(Service $service, DomainAuditService $domainAudit)
    {
        $service->loadMissing('serviceType');
        if (strtoupper((string) $service->serviceType?->code) !== 'DOMAIN') {
            return response()->json(['ok' => false, 'message' => 'Detect Expiry is available only for Domain services.'], 422);
        }

        $domain = trim((string) ($service->value ?: $service->service_name));
        $result = $domainAudit->check($domain);

        if (!empty($result['expires_at'])) {
            try {
                $service->expiry_date = \Carbon\Carbon::parse($result['expires_at'])->toDateString();
                $service->save();
                $result['saved_expiry_date'] = $service->expiry_date->format('Y-m-d');
            } catch (\Throwable) {
                // Return the detected value even if date conversion fails.
            }
        }

        return response()->json(['ok' => !empty($result['expires_at']), 'result' => $result]);
    }

    public function export(Request $request)
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
            ->orderBy('customer_id')->orderBy('service_name')->get();

        return response()->streamDownload(function () use ($services) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Customer','Service Name','Service Type','Value','Provider','Cost','Currency','Billing Cycle',
                'Term (months)','Expiry Date','Alert Policy','Responsible IT','Status','Auto Renew','WAN IP / Target',
                'Check Method','Check Port','Interval (sec)','Timeout (sec)','Monitor Status','Latency (ms)',
                'Packet Loss (%)','Failure Count','Last Check','Down Since','Note'
            ]);
            foreach ($services as $s) {
                fputcsv($out, [
                    $s->customer?->name, $s->service_name, $s->serviceType?->name, $s->value, $s->provider?->name,
                    $s->cost_amount, $s->cost_currency, $s->cost_billing_cycle, $s->service_term_months,
                    optional($s->expiry_date)->format('Y-m-d'), $s->alertPolicy?->name, $s->responsibleIt?->name,
                    $s->status, $s->auto_renew ? 'Yes' : 'No', $s->monitor_target,
                    strtoupper((string) $s->monitor_check_method), $s->monitor_port, $s->monitor_interval_seconds,
                    $s->monitor_timeout_seconds, $s->monitor_status, $s->monitor_last_latency_ms,
                    $s->monitor_packet_loss_percent, $s->monitor_failure_count,
                    optional($s->monitor_last_checked_at)->format('Y-m-d H:i:s'),
                    optional($s->monitor_down_since)->format('Y-m-d H:i:s'), $s->note,
                ]);
            }
            fclose($out);
        }, 'it_services_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
