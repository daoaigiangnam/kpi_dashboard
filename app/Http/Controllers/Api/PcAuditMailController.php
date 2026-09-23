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

        $code = PcAuditCode::where('code', $payload['code'])->where('is_active', true)->first();
        if (!$code) return response()->json(['ok' => false, 'message' => 'Audit Code không hợp lệ hoặc đã bị khóa.'], 404);

        $mailer = SystemSetting::value('mail.mailer', 'log');
        $to = SystemSetting::value('system.notification_email', '');
        if ($mailer !== 'smtp' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['ok' => false, 'message' => 'SMTP hoặc Email nhận chưa được cấu hình trên Admin.'], 409);
        }

        Mail::raw($payload['body'], function ($message) use ($payload, $to) {
            $message->to($to)->subject($payload['subject']);
        });

        return response()->json(['ok' => true, 'message' => 'Email đã được gửi.', 'to' => $to]);
    }
}
