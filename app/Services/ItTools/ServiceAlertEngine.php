<?php

namespace App\Services\ItTools;

use App\Models\Service;
use App\Models\ServiceAlertEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ServiceAlertEngine
{
    public function evaluate(Service $service): ?ServiceAlertEvent
    {
        if ($service->status !== 'active' || !$service->expiry_date || !$service->service_term_months || !$service->alertPolicy) {
            return null;
        }

        $start = Carbon::parse($service->expiry_date)->copy()->subMonthsNoOverflow((int) $service->service_term_months);
        $expiry = Carbon::parse($service->expiry_date);
        $today = now()->startOfDay();
        $totalDays = max(1, $start->diffInDays($expiry));
        $remainingDays = $today->lt($expiry) ? $today->diffInDays($expiry) : -$today->diffInDays($expiry);
        $remainingPercent = $today->gte($expiry) ? 0.0 : round(($remainingDays / $totalDays) * 100, 2);
        $policy = $service->alertPolicy;
        $stage = match (true) {
            $today->gte($expiry) => 4,
            $remainingPercent <= (float) $policy->alert_3_percent => 3,
            $remainingPercent <= (float) $policy->alert_2_percent => 2,
            $remainingPercent <= (float) $policy->alert_1_percent => 1,
            default => 0,
        };
        $currentStage = (int) $service->alert_stage;

        if ($stage < $currentStage) {
            return DB::transaction(function () use ($service, $stage) {
                ServiceAlertEvent::query()->where('service_id', $service->id)->where('alert_type', 'service_expiry')->whereIn('status', ['open', 'acknowledged'])->update([
                    'status' => 'resolved', 'resolved_at' => now(),
                    'note' => 'Alert closed automatically because the service monitoring state was recalculated.',
                ]);
                $service->update(['alert_stage' => $stage, 'last_alert_at' => null]);
                return null;
            });
        }

        if ($stage === 0 || $stage <= $currentStage) return null;

        return DB::transaction(function () use ($service, $policy, $stage, $remainingPercent, $expiry) {
            $event = ServiceAlertEvent::create([
                'service_id' => $service->id,
                'alert_type' => 'service_expiry',
                'alert_policy_id' => $policy->id,
                'alert_stage' => $stage,
                'remaining_percent' => $remainingPercent,
                'expiry_date' => $expiry->toDateString(),
                'status' => 'open',
                'triggered_at' => now(),
            ]);
            $service->update([
                'alert_stage' => $stage,
                'last_alert_at' => now(),
                'status' => $stage === 4 ? 'expired' : $service->status,
            ]);
            return $event;
        });
    }

    public function evaluateSsl(Service $service): ?ServiceAlertEvent
    {
        if ($service->status !== 'active' || !$service->ssl_detected || !$service->ssl_expiry_date || !$service->alertPolicy) {
            return null;
        }

        $expiry = Carbon::parse($service->ssl_expiry_date);
        $start = $service->ssl_valid_from_date
            ? Carbon::parse($service->ssl_valid_from_date)
            : $expiry->copy()->subDays(90);
        $today = now()->startOfDay();
        $totalDays = max(1, $start->diffInDays($expiry));
        $remainingDays = $today->lt($expiry) ? $today->diffInDays($expiry) : -$today->diffInDays($expiry);
        $remainingPercent = $today->gte($expiry) ? 0.0 : round(($remainingDays / $totalDays) * 100, 2);
        $policy = $service->alertPolicy;
        $stage = match (true) {
            $today->gte($expiry) => 4,
            $remainingPercent <= (float) $policy->alert_3_percent => 3,
            $remainingPercent <= (float) $policy->alert_2_percent => 2,
            $remainingPercent <= (float) $policy->alert_1_percent => 1,
            default => 0,
        };
        $currentStage = (int) $service->ssl_alert_stage;

        if ($stage < $currentStage) {
            return DB::transaction(function () use ($service, $stage) {
                ServiceAlertEvent::query()->where('service_id', $service->id)->where('alert_type', 'ssl_expiry')->whereIn('status', ['open', 'acknowledged'])->update([
                    'status' => 'resolved', 'resolved_at' => now(),
                    'note' => 'SSL alert closed automatically because the certificate monitoring state was recalculated.',
                ]);
                $service->update(['ssl_alert_stage' => $stage, 'ssl_last_alert_at' => null]);
                return null;
            });
        }

        if ($stage === 0 || $stage <= $currentStage) return null;

        return DB::transaction(function () use ($service, $policy, $stage, $remainingPercent, $expiry) {
            $event = ServiceAlertEvent::create([
                'service_id' => $service->id,
                'alert_type' => 'ssl_expiry',
                'alert_policy_id' => $policy->id,
                'alert_stage' => $stage,
                'remaining_percent' => $remainingPercent,
                'expiry_date' => $expiry->toDateString(),
                'status' => 'open',
                'triggered_at' => now(),
            ]);
            $service->update([
                'ssl_alert_stage' => $stage,
                'ssl_last_alert_at' => now(),
            ]);
            return $event;
        });
    }
}
