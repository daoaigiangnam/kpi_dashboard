<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PcAuditCode;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PcAuditMailController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'code' => ['required', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:1000000'],
        ]);

        $code = PcAuditCode::query()
            ->with(['branch.customer.alertRecipients'])
            ->where('code', $payload['code'])
            ->where('is_active', true)
            ->first();

        if (!$code) {
            return response()->json(['ok' => false, 'message' => 'Audit Code không hợp lệ hoặc đã bị khóa.'], 404);
        }

        if (SystemSetting::value('mail.mailer', 'log') !== 'smtp') {
            return response()->json(['ok' => false, 'message' => 'SMTP chưa được cấu hình trên Admin.'], 409);
        }

        $customer = $code->branch?->customer;
        $recipients = $customer?->alertRecipients
            ->where('is_active', true)
            ->pluck('recipient_email')
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all() ?? [];

        if (!$recipients) {
            return response()->json(['ok' => false, 'message' => 'Customer chưa có Email nhận Audit đang hoạt động.'], 409);
        }

        Mail::raw($payload['body'], function ($message) use ($payload, $recipients) {
            $message->to($recipients)->subject($payload['subject']);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Email đã được gửi.',
            'to' => $recipients,
            'recipient_count' => count($recipients),
        ]);
    }
}
