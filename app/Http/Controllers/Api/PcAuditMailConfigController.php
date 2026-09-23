<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PcAuditCode;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PcAuditMailConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:120']]);

        $code = PcAuditCode::query()
            ->with(['branch.customer.alertRecipients'])
            ->where('code', $data['code'])
            ->where('is_active', true)
            ->first();

        if (!$code) {
            return response()->json(['ok' => false, 'message' => 'Audit Code không hợp lệ hoặc đã bị khóa.'], 404);
        }

        if (SystemSetting::value('mail.mailer', 'log') !== 'smtp') {
            return response()->json(['ok' => false, 'message' => 'Hệ thống KPI chưa được cấu hình SMTP.'], 409);
        }

        $customer = $code->branch?->customer;
        $recipients = $customer?->alertRecipients
            ->where('is_active', true)
            ->pluck('recipient_email')
            ->filter()
            ->unique()
            ->values() ?? collect();

        return response()->json([
            'ok' => true,
            'data' => [
                'customer_id' => $customer?->id,
                'customer_name' => $customer?->name,
                'from_email' => SystemSetting::value('mail.from_address', ''),
                'from_name' => SystemSetting::value('mail.from_name', 'KPI Dashboard System'),
                'recipients' => $recipients,
            ],
        ]);
    }
}
