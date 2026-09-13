<?php

namespace App\Services\ItTools;

use App\Models\ServiceAlertEmailLog;
use App\Models\ServiceAlertEvent;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ServiceAlertEmailService
{
    public function notifyNewAlert(ServiceAlertEvent $event): void
    {
        if (!$this->enabled()) {
            return;
        }

        $event->loadMissing(['service.customer.alertRecipients', 'service.serviceType', 'service.provider', 'alertPolicy']);
        $this->sendLevel($event, 1, 'alert');
    }

    public function processEscalations(): int
    {
        if (!$this->enabled()) {
            return 0;
        }

        $sent = 0;
        ServiceAlertEvent::query()
            ->whereIn('status', ['open', 'acknowledged'])
            ->with(['service.customer.alertRecipients', 'service.serviceType', 'service.provider', 'alertPolicy'])
            ->orderBy('id')
            ->limit(1000)
            ->get()
            ->each(function (ServiceAlertEvent $event) use (&$sent) {
                foreach ([2, 3, 4] as $level) {
                    $delay = $this->delayForLevel($level);
                    if ($delay === null) {
                        continue;
                    }

                    if (now()->lt($event->triggered_at->copy()->addMinutes($delay))) {
                        continue;
                    }

                    if ($this->sendLevel($event, $level, 'alert')) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    public function notifyResolved(ServiceAlertEvent $event): int
    {
        if (!$this->enabled() || !$this->sendResolution()) {
            return 0;
        }

        $event->loadMissing(['service.customer.alertRecipients', 'service.serviceType', 'service.provider', 'alertPolicy', 'resolvedBy']);
        $sent = 0;
        foreach ([1, 2, 3, 4] as $level) {
            if (!$this->levelEnabled($level)) {
                continue;
            }
            if ($this->sendLevel($event, $level, 'resolution')) {
                $sent++;
            }
        }

        return $sent;
    }

    private function sendLevel(ServiceAlertEvent $event, int $level, string $emailType): bool
    {
        $recipient = $this->recipientForLevel($event, $level);
        if (!$recipient || !$this->levelEnabled($level)) {
            return false;
        }

        $alreadySent = ServiceAlertEmailLog::query()
            ->where('service_alert_event_id', $event->id)
            ->where('level', $level)
            ->where('recipient_email', $recipient['email'])
            ->where('email_type', $emailType)
            ->where('status', 'sent')
            ->exists();

        if ($alreadySent) {
            return false;
        }

        try {
            Mail::html($this->renderHtml($event, $level, $emailType), function ($message) use ($recipient, $event, $emailType) {
                $subject = $emailType === 'resolution'
                    ? '[RESOLVED] IT Monitoring - '.$event->service->service_name
                    : '[ALERT '.$event->alert_stage.'] IT Monitoring - '.$event->service->service_name;
                $message->to($recipient['email'], $recipient['name'])->subject($subject);
            });

            ServiceAlertEmailLog::updateOrCreate(
                [
                    'service_alert_event_id' => $event->id,
                    'level' => $level,
                    'recipient_email' => $recipient['email'],
                    'email_type' => $emailType,
                ],
                [
                    'recipient_type' => $recipient['type'],
                    'sent_at' => now(),
                    'status' => 'sent',
                    'error' => null,
                ]
            );

            return true;
        } catch (Throwable $e) {
            ServiceAlertEmailLog::updateOrCreate(
                [
                    'service_alert_event_id' => $event->id,
                    'level' => $level,
                    'recipient_email' => $recipient['email'],
                    'email_type' => $emailType,
                ],
                [
                    'recipient_type' => $recipient['type'],
                    'sent_at' => null,
                    'status' => 'failed',
                    'error' => mb_substr($e->getMessage(), 0, 2000),
                ]
            );

            return false;
        }
    }

    private function recipientForLevel(ServiceAlertEvent $event, int $level): ?array
    {
        $service = $event->service;
        $customer = $service?->customer;

        if ($level === 1) {
            $recipient = $customer?->alertRecipients?->firstWhere('level', 1);
            if (!$recipient || !$recipient->is_active || !$recipient->recipient_email) {
                return null;
            }

            return [
                'type' => 'customer_operations',
                'email' => $recipient->recipient_email,
                'name' => $recipient->recipient_name ?: $customer->name,
            ];
        }

        if ($level === 2) {
            return $this->configuredRecipient('alert_email.it_lead_email', 'it_lead', 'IT Lead');
        }

        if ($level === 3) {
            return $this->configuredRecipient('alert_email.bod_email', 'bod_outsourcing', 'BOD Outsourcing');
        }

        if (!$customer?->email) {
            return null;
        }

        return [
            'type' => 'customer',
            'email' => $customer->email,
            'name' => $customer->contact_name ?: $customer->name,
        ];
    }

    private function configuredRecipient(string $key, string $type, string $name): ?array
    {
        $email = trim((string) SystemSetting::value($key, ''));
        return $email !== '' ? ['type' => $type, 'email' => $email, 'name' => $name] : null;
    }

    private function levelEnabled(int $level): bool
    {
        return match ($level) {
            1 => $this->settingBool('alert_email.operator_enabled', true),
            2 => $this->settingBool('alert_email.it_lead_enabled', true),
            3 => $this->settingBool('alert_email.bod_enabled', true),
            4 => $this->settingBool('alert_email.customer_enabled', true),
            default => false,
        };
    }

    private function delayForLevel(int $level): ?int
    {
        if (!$this->levelEnabled($level)) {
            return null;
        }

        $key = match ($level) {
            2 => 'alert_email.it_lead_delay_minutes',
            3 => 'alert_email.bod_delay_minutes',
            4 => 'alert_email.customer_delay_minutes',
            default => null,
        };

        return $key ? max(0, (int) SystemSetting::value($key, 0)) : null;
    }

    private function enabled(): bool
    {
        return $this->settingBool('alert_email.enabled', false);
    }

    private function sendResolution(): bool
    {
        return $this->settingBool('alert_email.send_resolution', true);
    }

    private function settingBool(string $key, bool $default): bool
    {
        $value = SystemSetting::value($key, $default ? '1' : '0');
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    private function renderHtml(ServiceAlertEvent $event, int $level, string $emailType): string
    {
        $service = $event->service;
        $status = $emailType === 'resolution' ? 'RESOLVED' : strtoupper($event->status);
        $remaining = number_format((float) $event->remaining_percent, 2).'%';
        $expiry = optional($event->expiry_date)->format('d/m/Y') ?: '-';
        $resolvedBy = $event->resolvedBy?->name ?: '-';
        $resolvedAt = optional($event->resolved_at)->format('d/m/Y H:i') ?: '-';

        return '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.5">'
            .'<h2 style="margin-bottom:6px">IT Monitoring - '.e($status).'</h2>'
            .'<p>Service: <strong>'.e($service->service_name).'</strong></p>'
            .'<table cellpadding="7" cellspacing="0" border="1" style="border-collapse:collapse;border-color:#d1d5db">'
            .'<tr><td>Customer</td><td>'.e($service->customer?->name ?: '-').'</td></tr>'
            .'<tr><td>Service Type</td><td>'.e($service->serviceType?->name ?: '-').'</td></tr>'
            .'<tr><td>Provider</td><td>'.e($service->provider?->name ?: '-').'</td></tr>'
            .'<tr><td>Alert Level</td><td>Alert '.e((string) $event->alert_stage).'</td></tr>'
            .'<tr><td>Remaining</td><td>'.e($remaining).'</td></tr>'
            .'<tr><td>Expiry</td><td>'.e($expiry).'</td></tr>'
            .'<tr><td>Triggered</td><td>'.e(optional($event->triggered_at)->format('d/m/Y H:i') ?: '-').'</td></tr>'
            .($emailType === 'resolution' ? '<tr><td>Resolved By</td><td>'.e($resolvedBy).'</td></tr><tr><td>Resolved At</td><td>'.e($resolvedAt).'</td></tr>' : '')
            .'</table>'
            .'<p style="margin-top:18px">This email was generated automatically by IT Monitoring.</p>'
            .'</body></html>';
    }
}
