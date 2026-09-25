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
        'customer_id', 'service_type_id', 'provider_id', 'service_name', 'value',
        'cost_amount', 'cost_currency', 'cost_billing_cycle',
        'payment_due_day', 'payment_alert_percent',
        'service_term_months', 'expiry_date', 'alert_policy_id', 'responsible_it_id',
        'status', 'auto_renew', 'note', 'alert_stage', 'last_alert_at',
        'monitor_check_method', 'monitor_target', 'monitor_port',
        'monitor_interval_seconds', 'monitor_timeout_seconds',
        'monitor_status', 'monitor_last_latency_ms', 'monitor_packet_loss_percent',
        'monitor_failure_count', 'monitor_last_checked_at', 'monitor_down_since',
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
        'monitor_interval_seconds' => 'integer',
        'monitor_timeout_seconds' => 'integer',
        'monitor_last_latency_ms' => 'decimal:1',
        'monitor_packet_loss_percent' => 'decimal:2',
        'monitor_failure_count' => 'integer',
        'monitor_last_checked_at' => 'datetime',
        'monitor_down_since' => 'datetime',
    ];

    /**
     * Limit service data to the services managed by the current user.
     * Super Admin is intentionally unrestricted.
     */
    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (!$user || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('responsible_it_id', $user->id);
    }

    public function isVisibleTo(?User $user = null): bool
    {
        $user ??= auth()->user();

        return !$user || $user->isSuperAdmin() || (int) $this->responsible_it_id === (int) $user->id;
    }

    public function customer(): BelongsTo { return $this->belongsTo(ServiceCustomer::class); }
    public function serviceType(): BelongsTo { return $this->belongsTo(ServiceType::class); }
    public function provider(): BelongsTo { return $this->belongsTo(ServiceProvider::class); }
    public function alertPolicy(): BelongsTo { return $this->belongsTo(ServiceAlertPolicy::class); }
    public function responsibleIt(): BelongsTo { return $this->belongsTo(User::class, 'responsible_it_id'); }
}
