<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PcAuditSetting;
use Illuminate\Http\JsonResponse;

class PcAuditConfigController extends Controller
{
    public function show(): JsonResponse
    {
        $setting = PcAuditSetting::query()->first();

        if (!$setting) {
            $setting = PcAuditSetting::create([
                'api_base_url' => config('app.url') . '/api',
                'tool_version' => '1.0.0',
                'minimum_tool_version' => '1.0.0',
                'enabled' => true,
            ]);
        }

        return response()->json([
            'enabled' => $setting->enabled,
            'api_base_url' => rtrim($setting->api_base_url, '/'),
            'tool_version' => $setting->tool_version,
            'minimum_tool_version' => $setting->minimum_tool_version,
            'download_url' => $setting->download_url,
            'disabled_message' => $setting->disabled_message,
        ]);
    }
}
