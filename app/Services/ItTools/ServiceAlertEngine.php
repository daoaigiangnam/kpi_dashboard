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
        if ($service->status !== 'active') {
            return null;
        }

        if (!$service->expiry_date || !$service->service_term_months || !$service->alertPolicy) {
            return null;
        }

        $start = Carbon::parse($service->expiry_date)
            ->copy()
            ->subMonthsNoOverflow((int) $service->service_term_months);
        $expiry = Carbon::parse($service->expiry_date);
        $today = now()->startOfDay();
        $totalDays = max(1, $start->diffInDays($expiry));
        $remainingDays = $today->lt($expiry)
            ? $today->diffInDays($expiry)
            : -$today->diffInDays($expiry);
        $remainingPercent = $today->gte($expiry)
            ? 0.0
            : round(($remainingDays / $totalDays) * 100, 2);

        $policy = $service->alertPolicy;
        $stage = match (true) {
            $today->gte($expiry) => 4,
            $remainingPercent <= (float) $policy->alert_3_percent => 3,
            $remainingPercent <= (float) $policy->alert_2_percent => 2,
            $remainingPercent <= (float) $policy->alert_1_percent => 1,
            default => 0,
        };

        $currentStage = (int) $service->alert_stage;

        // If the service expiry/term/policy was changed so the current risk is lower,
        // reset the persisted monitoring stage and close obsolete open alerts.
        if ($stage < $currentStage) {
            return DB::transaction(function () use ($service, $stage) {
                ServiceAlertEvent::query()
                    ->where('service_id', $service->id)
                    ->whereIn('status', ['open', 'acknowledged'])
                    ->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'note' => 'Alert closed automatically because the service monitoring state was recalculated.',
                    ]);

                $service->update([
                    'alert_stage' => $stage,
                    'last_alert_at' => null,
                ]);

                return null;
            });
        }

        if ($stage === 0 || $stage <= $currentStage) {
            return null;
        }

        return DB::transaction(function () use ($service, $policy, $stage, $remainingPercent, $expiry) {
            $event = ServiceAlertEvent::create([
                'service_id' => $service->id,
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
}
