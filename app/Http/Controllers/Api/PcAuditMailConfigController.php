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
        $code = PcAuditCode::where('code', $data['code'])->where('is_active', true)->first();
        if (!$code) return response()->json(['ok' => false, 'message' => 'Audit Code không hợp lệ hoặc đã bị khóa.'], 404);
        if (SystemSetting::value('mail.mailer', 'log') !== 'smtp') {
            return response()->json(['ok' => false, 'message' => 'SMTP chưa được cấu hình trên Admin.'], 409);
        }

        return response()->json(['ok' => true, 'data' => [
            'from_email' => SystemSetting::value('mail.from_address', ''),
            'from_name' => SystemSetting::value('mail.from_name', 'KPI Dashboard System'),
            'to_email' => SystemSetting::value('system.notification_email', ''),
        ]]);
    }
}
