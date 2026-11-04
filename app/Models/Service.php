<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id', 'service_type_id', 'provider_id', 'service_name', 'website_title', 'value',
        'cost_amount', 'cost_currency', 'cost_billing_cycle',
        'payment_due_day', 'payment_alert_percent',
        'service_term_months', 'expiry_date', 'alert_policy_id', 'responsible_it_id',
        'status', 'auto_renew', 'note', 'alert_stage', 'last_alert_at',
        'monitor_check_method', 'monitor_target', 'monitor_port', 'monitor_ports',
        'monitor_interval_seconds', 'monitor_timeout_seconds',
        'monitor_status', 'monitor_last_latency_ms', 'monitor_packet_loss_percent',
        'monitor_failure_count', 'monitor_last_checked_at', 'monitor_down_since',
        'ssl_detected', 'ssl_valid_from_date', 'ssl_expiry_date', 'ssl_issuer', 'ssl_status',
        'ssl_last_checked_at', 'ssl_alert_stage', 'ssl_last_alert_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'last_alert_at' => 'datetime',
        'auto_renew' => 'boolean',
        'service_term_months' => 'integer',
        'cost_amount' => 'decimal:2',
        'payment_due_day' => 'integer',
        'payment_alert_percent' => 'integer',
        'alert_stage' => 'integer',
        'monitor_port' => 'integer',
        'monitor_ports' => 'array',
        'monitor_interval_seconds' => 'integer',
        'monitor_timeout_seconds' => 'integer',
        'monitor_last_latency_ms' => 'decimal:1',
        'monitor_packet_loss_percent' => 'decimal:2',
        'monitor_failure_count' => 'integer',
        'monitor_last_checked_at' => 'datetime',
        'monitor_down_since' => 'datetime',
        'ssl_detected' => 'boolean',
        'ssl_valid_from_date' => 'date',
        'ssl_expiry_date' => 'date',
        'ssl_last_checked_at' => 'datetime',
        'ssl_alert_stage' => 'integer',
        'ssl_last_alert_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Service $service) {
            if ($service->isDirty([
                'service_type_id','value','monitor_check_method','monitor_target','monitor_port','monitor_ports',
                'monitor_interval_seconds','monitor_timeout_seconds',
            ])) {
                $service->monitor_last_checked_at = null;
                $service->monitor_status = 'pending';
                $service->monitor_failure_count = 0;
                $service->monitor_down_since = null;
            }

            if ($service->isDirty([
                'service_type_id','value','monitor_target','monitor_port','monitor_ports',
            ])) {
                $service->ssl_last_checked_at = null;
                $service->ssl_status = 'pending';
            }

            // A recurring monthly Internet subscription does not have a fixed
            // service-expiry lifecycle. Keep its business status active even if
            // an older record carried an expired status/stage from expiry alerts.
            $service->loadMissing('serviceType');
            if (
                strtoupper((string) $service->serviceType?->code) === 'INTERNET'
                && $service->cost_billing_cycle === 'monthly'
            ) {
                $service->status = 'active';
                $service->expiry_date = null;
                $service->service_term_months = null;
                $service->alert_policy_id = null;
                $service->alert_stage = 0;
                $service->last_alert_at = null;
            }
        });
    }

    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (!$user || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            // Current ownership is by the assigned IT user.
            $q->where('responsible_it_id', $user->id);

            // Legacy compatibility only: services created before individual
            // ownership was introduced may have no responsible IT and belong
            // to an unassigned customer in the user's legacy IT group.
            if ($user->user_group_id) {
                $q->orWhere(function (Builder $legacy) use ($user): void {
                    $legacy->whereNull('responsible_it_id')
                        ->whereHas('customer', function (Builder $customer) use ($user): void {
                            $customer->whereNull('responsible_it_id')
                                ->whereHas('groups', fn (Builder $g) => $g->whereKey($user->user_group_id));
                        });
                });
            }
        });
    }

    public function isVisibleTo(?User $user = null): bool
    {
        $user ??= auth()->user();
        return !$user
            || $user->isSuperAdmin()
            || (int) $this->responsible_it_id === (int) $user->id
            || ($user->user_group_id && $this->customer?->groups()->whereKey($user->user_group_id)->exists());
    }

    public function customer(): BelongsTo { return $this->belongsTo(ServiceCustomer::class); }
    public function serviceType(): BelongsTo { return $this->belongsTo(ServiceType::class); }
    public function provider(): BelongsTo { return $this->belongsTo(ServiceProvider::class); }
    public function alertPolicy(): BelongsTo { return $this->belongsTo(ServiceAlertPolicy::class); }

    public function alertEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ServiceAlertEvent::class);
    }
    public function responsibleIt(): BelongsTo { return $this->belongsTo(User::class, 'responsible_it_id'); }
}
