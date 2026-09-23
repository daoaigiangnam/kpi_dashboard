<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PcAuditMailSetting;
use Illuminate\Http\JsonResponse;

class PcAuditMailConfigController extends Controller
{
    public function show(): JsonResponse
    {
        $setting = PcAuditMailSetting::query()->first();

        if (!$setting) {
            return response()->json(['enabled' => false], 404);
        }

        return response()->json([
            'enabled' => $setting->enabled,
            'smtp_host' => $setting->smtp_host,
            'smtp_port' => $setting->smtp_port,
            'smtp_encryption' => $setting->smtp_encryption,
            'smtp_username' => $setting->smtp_username,
            'from_email' => $setting->from_email,
            'from_name' => $setting->from_name,
        ]);
    }
}
