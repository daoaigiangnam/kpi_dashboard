<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PcAuditSetting;
use Illuminate\Http\JsonResponse;

class PcAuditConfigController extends Controller
{

    public function agentConfig(Request $request): JsonResponse
    {
        $input = $request->validate(['code' => ['required', 'string', 'max:120']]);
        $code = PcAuditCode::query()
            ->where('code', strtoupper(trim($input['code'])))
            ->where('is_active', true)
            ->whereHas('branch', fn ($q) => $q->where('is_active', true)->whereHas('customer', fn ($c) => $c->where('is_active', true)))
            ->first();

        if (!$code) {
            return response()->json(['ok' => false, 'message' => 'Audit Code không hợp lệ hoặc không hoạt động.'], 404);
        }

        $setting = PcAuditSetting::query()->first();
        if (!$setting) {
            return response()->json(['ok' => false, 'message' => 'Chưa có cấu hình PC Audit trên Server.'], 503);
        }

        return response()->json([
            'ok' => true,
            'code' => $code->code,
            'server_time' => now()->toIso8601String(),
            'enabled' => (bool) $setting->enabled,
            'schedule' => [
                'enabled' => (bool) ($setting->agent_schedule_enabled ?? true),
                'interval_minutes' => (int) ($setting->agent_interval_minutes ?? 360),
                'run_on_start' => (bool) ($setting->agent_run_on_start ?? true),
                'updated_at' => $setting->updated_at?->toIso8601String(),
            ],
        ]);
    }

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
