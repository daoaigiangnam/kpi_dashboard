<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PcAudit;
use App\Models\PcAuditCode;
use App\Models\PcAuditDetail;
use App\Services\PcAuditEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PcAuditApiController extends Controller
{
    public function validateCode(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:120']]);
        $code = PcAuditCode::with('branch.customer')->where('code', $data['code'])->where('is_active', true)->first();
        if (!$code) return response()->json(['ok' => false, 'message' => 'Mã Audit không hợp lệ hoặc đã bị khóa.'], 404);
        return response()->json(['ok' => true, 'data' => [
            'code' => $code->code,
            'customer' => ['id' => $code->branch->customer->id, 'code' => $code->branch->customer->code, 'name' => $code->branch->customer->name],
            'branch' => ['id' => $code->branch->id, 'code' => $code->branch->code, 'name' => $code->branch->name],
        ]]);
    }

    public function submit(Request $request, PcAuditEngine $engine): JsonResponse
    {
        $payload = $request->validate([
            'code' => ['required', 'string', 'max:120'],
            'department' => ['required', 'string', 'max:200'],
            'employee_name' => ['required', 'string', 'max:200'],
            'data' => ['required', 'array'],
        ]);
        $code = PcAuditCode::where('code', $payload['code'])->where('is_active', true)->first();
        if (!$code) return response()->json(['ok' => false, 'message' => 'Mã Audit không hợp lệ hoặc đã bị khóa.'], 404);

        $data = $payload['data'];
        $computer = is_array($data['computer'] ?? null) ? $data['computer'] : [];
        $windows = is_array($data['windows'] ?? null) ? $data['windows'] : [];
        $security = is_array($data['security'] ?? null) ? $data['security'] : [];
        $value = static fn(array $keys, $default = null) => self::firstValue($data, $keys, $default);
        $auditResult = $engine->evaluate($data);

        $audit = DB::transaction(function () use ($code, $payload, $data, $computer, $windows, $security, $value, $auditResult) {
            $audit = PcAudit::create([
                'pc_audit_code_id' => $code->id,
                'department' => $payload['department'],
                'employee_name' => $payload['employee_name'],
                'employee_username' => $computer['username'] ?? $value(['username', 'employee_username']),
                'domain' => $computer['domain'] ?? $value(['domain']),
                'computer_name' => $computer['computer_name'] ?? $value(['computer_name', 'computerName']),
                'manufacturer' => $computer['manufacturer'] ?? $value(['manufacturer']),
                'model' => $computer['model'] ?? $value(['model']),
                'serial_number' => $computer['serial_number'] ?? $value(['serial_number', 'serialNumber']),
                'asset_tag' => $computer['asset_tag'] ?? $value(['asset_tag', 'assetTag']),
                'mainboard' => $data['mainboard'] ?? null,
                'bios' => $data['bios'] ?? null,
                'operating_system' => $windows ?: ($data['operating_system'] ?? null),
                'windows_update' => $data['windows_update'] ?? null,
                'last_boot' => $windows['last_boot'] ?? $value(['last_boot']),
                'uptime' => $windows['uptime_hours'] ?? $value(['uptime']),
                'tpm' => $security['tpm'] ?? ($data['tpm'] ?? null),
                'secure_boot' => $security['secure_boot'] ?? ($data['secure_boot'] ?? null),
                'collected_at' => $value(['collected_at'], now()),
                'audit_status' => $auditResult['status'],
                'audit_score' => $auditResult['score'],
                'audit_results' => $auditResult,
                'audit_engine_version' => $auditResult['engine_version'],
                'raw_payload' => $data,
            ]);

            PcAuditDetail::create([
                'pc_audit_id' => $audit->id,
                'hardware' => $data['hardware'] ?? null,
                'cpu' => $data['cpu'] ?? null,
                'memory' => $data['memory'] ?? null,
                'storage' => $data['storage'] ?? null,
                'monitors' => $data['monitors'] ?? null,
                'gpu' => $data['gpu'] ?? null,
                'battery' => $data['battery'] ?? null,
                'windows' => $data['windows'] ?? null,
                'network' => $data['network'] ?? null,
                'security' => $security ?: null,
                'licenses' => $data['licenses'] ?? null,
                'software' => $data['software'] ?? null,
                'other' => $data['other'] ?? null,
            ]);

            self::insertRows($audit->id, $data['memory'] ?? [], 'pc_audit_memory', ['capacity','speed','slot','manufacturer','part_number','serial_number']);
            self::insertRows($audit->id, $data['storage'] ?? [], 'pc_audit_storage', ['drive','used_gb','total_gb']);
            self::insertRows($audit->id, $data['monitors'] ?? [], 'pc_audit_monitors', ['manufacturer','model','serial_number']);
            self::insertRows($audit->id, $data['gpu'] ?? [], 'pc_audit_gpu', ['name','vram','driver_version']);
            self::insertRows($audit->id, $data['battery'] ?? [], 'pc_audit_battery', ['name','status','charge_percent']);
            self::insertRows($audit->id, $data['network'] ?? [], 'pc_audit_network', ['type','name','description','ipv4','gateway','mac','dns','dhcp','connection_status','link_speed']);
            self::insertRows($audit->id, $security['antivirus'] ?? [], 'pc_audit_antivirus', ['display_name','status','executable_path','signature_version']);
            self::insertRows($audit->id, $security['bitlocker'] ?? [], 'pc_audit_bitlocker', ['mount_point','protection_status','volume_status','encryption_percent']);
            self::insertRows($audit->id, $security['firewall'] ?? [], 'pc_audit_firewall', ['profile','enabled']);
            self::insertRows($audit->id, self::typedLicenseRows($data['licenses']['windows'] ?? [], 'WINDOWS'), 'pc_audit_licenses', ['product_type','product_name','status','partial_product_key']);
            self::insertRows($audit->id, self::typedLicenseRows($data['licenses']['office'] ?? [], 'OFFICE'), 'pc_audit_licenses', ['product_type','product_name','status','partial_product_key']);
            self::insertRows($audit->id, $data['software'] ?? [], 'pc_audit_software', ['name','version','publisher','install_date','estimated_size']);
            return $audit;
        });
        return response()->json([
            'ok' => true,
            'id' => $audit->id,
            'audit_status' => $audit->audit_status,
            'audit_score' => $audit->audit_score,
            'audit_results' => $audit->audit_results,
            'message' => 'Audit đã được ghi nhận và đánh giá.',
        ], 201);
    }

    private static function typedLicenseRows(mixed $rows, string $type): array
    {
        if (!is_array($rows)) return [];
        if (!array_is_list($rows)) $rows = [$rows];
        return array_map(fn ($row) => is_array($row) ? array_merge($row, ['product_type' => $row['product_type'] ?? $type]) : [], $rows);
    }

    private static function firstValue(array $data, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) if (array_key_exists($key, $data)) return $data[$key];
        return $default;
    }

    private static function insertRows(int $auditId, mixed $rows, string $table, array $columns): void
    {
        if (!is_array($rows)) return;
        if ($rows === [] || !array_is_list($rows)) $rows = [$rows];
        $now = now(); $insert = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $item = ['pc_audit_id'=>$auditId,'created_at'=>$now,'updated_at'=>$now];
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                if ($column === 'capacity' && $value === null) $value = isset($row['capacity_gb']) ? $row['capacity_gb'].' GB' : null;
                if ($column === 'vram' && $value === null) $value = isset($row['vram_gb']) ? $row['vram_gb'].' GB' : null;
                $item[$column] = $value;
            }
            $insert[] = $item;
        }
        if ($insert) DB::table($table)->insert($insert);
    }
}
