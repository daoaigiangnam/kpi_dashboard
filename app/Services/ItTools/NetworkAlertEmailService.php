<?php

namespace App\Services\ItTools;

use App\Models\ServiceMonitorEmailLog;
use App\Models\ServiceMonitorEvent;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NetworkAlertEmailService
{
    public function process(): int
    {
        if (!$this->enabled()) return 0;

        $sent = 0;
        ServiceMonitorEvent::query()
            ->with(['service.customer', 'service.responsibleIt', 'service.provider'])
            ->where(function ($q) {
                $q->where('status', 'open')->orWhere('event_type', 'recovered');
            })
            ->orderBy('id')
            ->limit(1000)
            ->get()
            ->each(function (ServiceMonitorEvent $event) use (&$sent) {
                if ($event->status === 'open' && $this->sendLevel($event, 1, 'alert')) $sent++;
                if ($event->status === 'resolved' && $event->event_type === 'recovered' && $this->sendLevel($event, 1, 'resolution')) $sent++;
            });

        return $sent;
    }

    private function sendLevel(ServiceMonitorEvent $event, int $level, string $emailType): bool
    {
        if (!$this->levelEnabled($level)) return false;

        $recipient = $this->recipientForLevel($event, $level);
        if (!$recipient) return false;

        $alreadySent = ServiceMonitorEmailLog::query()
            ->where('service_monitor_event_id', $event->id)
            ->where('level', $level)
            ->where('recipient_email', $recipient['email'])
            ->where('email_type', $emailType)
            ->where('status', 'sent')
            ->exists();

        if ($alreadySent) return false;

        try {
            Mail::html($this->renderHtml($event, $emailType), function ($message) use ($recipient, $event, $emailType) {
                $prefix = $emailType === 'resolution' ? '[RESOLVED]' : '[FTTH DOWN]';
                $message->to($recipient['email'], $recipient['name'])
                    ->subject($prefix.' IT Monitoring - '.$event->service->service_name);
            });

            ServiceMonitorEmailLog::updateOrCreate(
                [
                    'service_monitor_event_id' => $event->id,
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
            ServiceMonitorEmailLog::updateOrCreate(
                [
                    'service_monitor_event_id' => $event->id,
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

    private function recipientForLevel(ServiceMonitorEvent $event, int $level): ?array
    {
        $service = $event->service;
        $customer = $service?->customer;

        if ($level === 1) {
            $user = $service?->responsibleIt;
            return $user?->email
                ? ['type' => 'responsible_it', 'email' => $user->email, 'name' => $user->name ?: 'Operations Staff']
                : null;
        }

        if ($level === 2) return $this->configuredRecipient('alert_email.it_lead_email', 'it_lead', 'IT Lead');
        if ($level === 3) return $this->configuredRecipient('alert_email.bod_email', 'bod_outsourcing', 'BOD Outsourcing');

        return $customer?->email
            ? ['type' => 'customer', 'email' => $customer->email, 'name' => $customer->contact_name ?: $customer->name]
            : null;
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

    private function enabled(): bool
    {
        return $this->settingBool('alert_email.enabled', false);
    }

    private function settingBool(string $key, bool $default): bool
    {
        return in_array((string) SystemSetting::value($key, $default ? '1' : '0'), ['1', 'true', 'on', 'yes'], true);
    }

    private function renderHtml(ServiceMonitorEvent $event, string $emailType): string
    {
        $service = $event->service;
        $customer = $service?->customer;
        $isResolved = $emailType === 'resolution';
        $status = $isResolved ? 'RESOLVED' : 'OFFLINE';
        $title = $isResolved ? 'Đường truyền FTTH đã khôi phục' : 'CẢNH BÁO FTTH / INTERNET OFFLINE';
        $message = $isResolved
            ? 'Hệ thống xác nhận đường truyền đã hoạt động trở lại.'
            : 'Hệ thống phát hiện đường truyền Internet/FTTH không thể kết nối theo phương thức giám sát đã cấu hình.';
        $started = optional($event->started_at)->format('d/m/Y H:i:s') ?: '-';
        $resolved = optional($event->resolved_at)->format('d/m/Y H:i:s') ?: '-';
        $duration = $event->duration_seconds !== null ? gmdate('H:i:s', max(0, (int) $event->duration_seconds)) : '-';
        $latency = $event->latency_ms !== null ? $event->latency_ms.' ms' : '-';
        $loss = $event->packet_loss_percent !== null ? $event->packet_loss_percent.'%' : '-';

        return '<!doctype html><html><body style="margin:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#172033">'
            .'<div style="max-width:760px;margin:0 auto;padding:28px 16px"><div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">'
            .'<div style="padding:24px 28px;border-bottom:1px solid #e5e7eb"><div style="font-size:12px;font-weight:700;letter-spacing:1px;color:#64748b">KPI DASHBOARD · IT MONITORING</div><h1 style="margin:8px 0 6px;font-size:24px">'.e($title).'</h1><div style="font-size:14px;color:#64748b">'.e($message).'</div></div>'
            .'<div style="padding:24px 28px"><div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px;margin-bottom:20px"><div style="font-size:12px;color:#64748b;text-transform:uppercase">SERVICE</div><div style="font-size:22px;font-weight:700;margin-top:4px">'.e($service->service_name).'</div><div style="font-size:14px;color:#475569;margin-top:3px">'.e($service->value ?: '-').'</div></div>'
            .'<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:14px">'
            .'<tr><td style="width:35%;border-bottom:1px solid #e5e7eb;color:#64748b">Customer</td><td style="border-bottom:1px solid #e5e7eb;font-weight:600">'.e($customer?->name ?: '-').'</td></tr>'
            .'<tr><td style="border-bottom:1px solid #e5e7eb;color:#64748b">Provider</td><td style="border-bottom:1px solid #e5e7eb">'.e($service->provider?->name ?: '-').'</td></tr>'
            .'<tr><td style="border-bottom:1px solid #e5e7eb;color:#64748b">Monitor Target</td><td style="border-bottom:1px solid #e5e7eb;font-weight:700">'.e($event->target).'</td></tr>'
            .'<tr><td style="border-bottom:1px solid #e5e7eb;color:#64748b">Check Method</td><td style="border-bottom:1px solid #e5e7eb">'.e(strtoupper($event->check_method)).'</td></tr>'
            .'<tr><td style="border-bottom:1px solid #e5e7eb;color:#64748b">Status</td><td style="border-bottom:1px solid #e5e7eb;font-weight:700">'.e($status).'</td></tr>'
            .'<tr><td style="border-bottom:1px solid #e5e7eb;color:#64748b">Latency</td><td style="border-bottom:1px solid #e5e7eb">'.e($latency).'</td></tr>'
            .'<tr><td style="border-bottom:1px solid #e5e7eb;color:#64748b">Packet Loss</td><td style="border-bottom:1px solid #e5e7eb">'.e($loss).'</td></tr>'
            .'<tr><td style="border-bottom:1px solid #e5e7eb;color:#64748b">Down Since</td><td style="border-bottom:1px solid #e5e7eb">'.e($started).'</td></tr>'
            .($isResolved ? '<tr><td style="border-bottom:1px solid #e5e7eb;color:#64748b">Resolved At</td><td style="border-bottom:1px solid #e5e7eb">'.e($resolved).'</td></tr><tr><td style="color:#64748b">Downtime</td><td>'.e($duration).'</td></tr>' : '<tr><td style="color:#64748b">Reason</td><td>'.e($event->error ?: 'Connectivity check failed.').'</td></tr>')
            .'</table></div><div style="padding:16px 28px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;color:#64748b">Email tự động từ KPI Dashboard · IT Monitoring.</div></div></div></body></html>';
    }
}
