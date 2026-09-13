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
        if (!$this->enabled()) return;
        $event->loadMissing(['service.customer', 'service.responsibleIt', 'service.serviceType', 'service.provider', 'alertPolicy']);
        $this->sendLevel($event, 1, 'alert');
    }

    public function processEscalations(): int
    {
        if (!$this->enabled()) return 0;
        $sent = 0;
        ServiceAlertEvent::query()->whereIn('status', ['open', 'acknowledged'])
            ->with(['service.customer', 'service.responsibleIt', 'service.serviceType', 'service.provider', 'alertPolicy'])
            ->orderBy('id')->limit(1000)->get()->each(function (ServiceAlertEvent $event) use (&$sent) {
                foreach ([2, 3, 4] as $level) {
                    $delay = $this->delayForLevel($level);
                    if ($delay === null || now()->lt($event->triggered_at->copy()->addMinutes($delay))) continue;
                    if ($this->sendLevel($event, $level, 'alert')) $sent++;
                }
            });
        return $sent;
    }

    public function notifyResolved(ServiceAlertEvent $event): int
    {
        if (!$this->enabled() || !$this->sendResolution()) return 0;
        $event->loadMissing(['service.customer', 'service.responsibleIt', 'service.serviceType', 'service.provider', 'alertPolicy', 'resolvedBy']);
        $sent = 0;
        foreach ([1, 2, 3, 4] as $level) {
            if ($this->levelEnabled($level) && $this->sendLevel($event, $level, 'resolution')) $sent++;
        }
        return $sent;
    }

    private function sendLevel(ServiceAlertEvent $event, int $level, string $emailType): bool
    {
        $recipient = $this->recipientForLevel($event, $level);
        if (!$recipient || !$this->levelEnabled($level)) return false;
        $alreadySent = ServiceAlertEmailLog::query()->where('service_alert_event_id', $event->id)->where('level', $level)->where('recipient_email', $recipient['email'])->where('email_type', $emailType)->where('status', 'sent')->exists();
        if ($alreadySent) return false;
        try {
            Mail::html($this->renderHtml($event, $level, $emailType), function ($message) use ($recipient, $event, $emailType) {
                $subject = $emailType === 'resolution' ? '[RESOLVED] IT Monitoring - '.$event->service->service_name : '[ALERT '.$event->alert_stage.'] IT Monitoring - '.$event->service->service_name;
                $message->to($recipient['email'], $recipient['name'])->subject($subject);
            });
            ServiceAlertEmailLog::updateOrCreate(['service_alert_event_id' => $event->id, 'level' => $level, 'recipient_email' => $recipient['email'], 'email_type' => $emailType], ['recipient_type' => $recipient['type'], 'sent_at' => now(), 'status' => 'sent', 'error' => null]);
            return true;
        } catch (Throwable $e) {
            ServiceAlertEmailLog::updateOrCreate(['service_alert_event_id' => $event->id, 'level' => $level, 'recipient_email' => $recipient['email'], 'email_type' => $emailType], ['recipient_type' => $recipient['type'], 'sent_at' => null, 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000)]);
            return false;
        }
    }

    private function recipientForLevel(ServiceAlertEvent $event, int $level): ?array
    {
        $service = $event->service;
        $customer = $service?->customer;
        if ($level === 1) {
            $user = $service?->responsibleIt;
            if (!$user?->email) return null;
            return ['type' => 'responsible_it', 'email' => $user->email, 'name' => $user->name ?: 'Operations Staff'];
        }
        if ($level === 2) return $this->configuredRecipient('alert_email.it_lead_email', 'it_lead', 'IT Lead');
        if ($level === 3) return $this->configuredRecipient('alert_email.bod_email', 'bod_outsourcing', 'BOD Outsourcing');
        if (!$customer?->email) return null;
        return ['type' => 'customer', 'email' => $customer->email, 'name' => $customer->contact_name ?: $customer->name];
    }

    private function configuredRecipient(string $key, string $type, string $name): ?array
    {
        $email = trim((string) SystemSetting::value($key, ''));
        return $email !== '' ? ['type' => $type, 'email' => $email, 'name' => $name] : null;
    }

    private function levelEnabled(int $level): bool
    {
        return match ($level) { 1 => $this->settingBool('alert_email.operator_enabled', true), 2 => $this->settingBool('alert_email.it_lead_enabled', true), 3 => $this->settingBool('alert_email.bod_enabled', true), 4 => $this->settingBool('alert_email.customer_enabled', true), default => false };
    }

    private function delayForLevel(int $level): ?int
    {
        if (!$this->levelEnabled($level)) return null;
        $key = match ($level) { 2 => 'alert_email.it_lead_delay_minutes', 3 => 'alert_email.bod_delay_minutes', 4 => 'alert_email.customer_delay_minutes', default => null };
        return $key ? max(0, (int) SystemSetting::value($key, 0)) : null;
    }

    private function enabled(): bool { return $this->settingBool('alert_email.enabled', false); }
    private function sendResolution(): bool { return $this->settingBool('alert_email.send_resolution', true); }
    private function settingBool(string $key, bool $default): bool { return in_array((string) SystemSetting::value($key, $default ? '1' : '0'), ['1', 'true', 'on', 'yes'], true); }

    private function renderHtml(ServiceAlertEvent $event, int $level, string $emailType): string
    {
        $service = $event->service;
        $customer = $service->customer;
        $days = $event->expiry_date ? max(0, now()->startOfDay()->diffInDays($event->expiry_date->copy()->startOfDay(), false)) : null;
        $remaining = number_format((float) $event->remaining_percent, 2).'%';
        $expiry = optional($event->expiry_date)->format('d/m/Y') ?: '-';
        $triggered = optional($event->triggered_at)->format('d/m/Y H:i') ?: '-';
        $resolvedBy = $event->resolvedBy?->name ?: '-';
        $resolvedAt = optional($event->resolved_at)->format('d/m/Y H:i') ?: '-';
        $status = $emailType === 'resolution' ? 'RESOLVED' : strtoupper($event->status);
        $daysText = $days === null ? '-' : ($days === 0 ? 'HÔM NAY' : ($days < 0 ? 'ĐÃ HẾT HẠN' : $days.' ngày'));
        $alertTitle = $emailType === 'resolution' ? 'Service Alert đã được xử lý' : 'CẢNH BÁO DỊCH VỤ IT';
        $alertMessage = $emailType === 'resolution' ? 'Dịch vụ đã được xử lý/Resolve. Vui lòng xem thông tin hoàn tất bên dưới.' : 'Hệ thống phát hiện dịch vụ đang tiến gần ngày hết hạn. Vui lòng kiểm tra và xử lý theo SLA.';
        return '<!doctype html><html><body style="margin:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#172033">'
            .'<div style="max-width:760px;margin:0 auto;padding:28px 16px"><div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">'
            .'<div style="padding:24px 28px;border-bottom:1px solid #e5e7eb"><div style="font-size:12px;font-weight:700;letter-spacing:1px;color:#64748b">KPI DASHBOARD · IT MONITORING</div><h1 style="margin:8px 0 6px;font-size:24px">'.e($alertTitle).'</h1><div style="font-size:14px;color:#64748b">'.$alertMessage.'</div></div>'
            .'<div style="padding:24px 28px"><div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px;margin-bottom:20px"><div style="font-size:12px;color:#64748b;text-transform:uppercase">SERVICE</div><div style="font-size:22px;font-weight:700;margin-top:4px">'.e($service->service_name).'</div><div style="font-size:14px;color:#475569;margin-top:3px">'.e($service->value ?: '-').'</div></div>'
            .'<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:14px">'
            .'<tr><td style="width:35%;border-bottom:1px solid #e5e7eb;color:#64748b">Customer</td><td style="border-bottom:1px solid #e5e7eb;font-weight:600">'.e($customer?->name ?: '-').'</td></tr>'
            .'<tr><td style="border-bottom:1px solid #e5e7eb;color:#64748b">Service Type</td><td style="border-bottom:1px solid #e5e7eb">'.e($service->serviceType?->name ?: '-').'</td></tr>'
            .'<tr><td style="border-bottom:1px solid #e5e7eb;color:#64748b">Provider</td><td style="border-bottom:1px solid #e5e7eb">'.e($service->provider?->name ?: '-').'</td></tr>'
            .'<tr><td style="border-bottom:1px solid #e5e7eb;color:#64748b">Service Term</td><td style="border-bottom:1px solid #e5e7eb">'.e($service->service_term_months ? $service->service_term_months.' tháng' : '-').'</td></tr>'
            .'<tr><td style="border-bottom:1px solid #e5e7eb;color:#64748b">Expiry Date</td><td style="border-bottom:1px solid #e5e7eb;font-weight:700">'.e($expiry).'</td></tr>'
            .'<tr><td style="border-bottom:1px solid #e5e7eb;color:#64748b">Còn lại</td><td style="border-bottom:1px solid #e5e7eb;font-weight:700">'.e($daysText).' ('.e($remaining).')</td></tr>'
            .'<tr><td style="border-bottom:1px solid #e5e7eb;color:#64748b">Alert Level</td><td style="border-bottom:1px solid #e5e7eb;font-weight:700">Alert '.e((string) $event->alert_stage).'</td></tr>'
            .'<tr><td style="border-bottom:1px solid #e5e7eb;color:#64748b">Status</td><td style="border-bottom:1px solid #e5e7eb">'.e($status).'</td></tr>'
            .'<tr><td style="color:#64748b">Triggered At</td><td>'.e($triggered).'</td></tr>'
            .($emailType === 'resolution' ? '<tr><td style="color:#64748b">Resolved By</td><td>'.e($resolvedBy).'</td></tr><tr><td style="color:#64748b">Resolved At</td><td>'.e($resolvedAt).'</td></tr>' : '')
            .'</table><div style="margin-top:22px;padding:14px 16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;font-size:13px"><strong>Hành động đề nghị:</strong> kiểm tra dịch vụ và thực hiện gia hạn/xử lý trước ngày <strong>'.e($expiry).'</strong>.</div></div>'
            .'<div style="padding:16px 28px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;color:#64748b">Email tự động từ KPI Dashboard · IT Monitoring. Vui lòng không reply email này.</div></div></div></body></html>';
    }
}
