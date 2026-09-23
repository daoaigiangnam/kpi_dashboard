<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PcAudit;
use App\Models\PcAuditCode;
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

        return response()->json([
            'ok' => true,
            'data' => [
                'code' => $code->code,
                'customer' => ['id' => $code->branch->customer->id, 'code' => $code->branch->customer->code, 'name' => $code->branch->customer->name],
                'branch' => ['id' => $code->branch->id, 'code' => $code->branch->code, 'name' => $code->branch->name],
            ],
        ]);
    }

    public function submit(Request $request): JsonResponse
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
        $value = static fn(array $keys, $default = null) => self::firstValue($data, $keys, $default);

        $audit = DB::transaction(function () use ($code, $payload, $data, $value) {
            $audit = PcAudit::create([
                'pc_audit_code_id' => $code->id,
                'department' => $payload['department'],
                'employee_name' => $payload['employee_name'],
                'employee_username' => $value(['username', 'employee_username']),
                'domain' => $value(['domain']),
                'computer_name' => $value(['computer_name', 'computerName']),
                'manufacturer' => $value(['manufacturer']),
                'model' => $value(['model']),
                'serial_number' => $value(['serial_number', 'serialNumber']),
                'asset_tag' => $value(['asset_tag', 'assetTag']),
                'mainboard' => $value(['mainboard']),
                'bios' => $value(['bios']),
                'operating_system' => $value(['operating_system', 'windows']),
                'windows_update' => $value(['windows_update']),
                'last_boot' => $value(['last_boot']),
                'uptime' => $value(['uptime']),
                'tpm' => $value(['tpm']),
                'secure_boot' => $value(['secure_boot']),
                'collected_at' => now(),
                'raw_payload' => $data,
            ]);

            self::insertRows($audit->id, $data['memory'] ?? [], 'pc_audit_memory', ['capacity','speed','slot','manufacturer','part_number','serial_number']);
            self::insertRows($audit->id, $data['storage'] ?? [], 'pc_audit_storage', ['drive','used_gb','total_gb']);
            self::insertRows($audit->id, $data['monitors'] ?? [], 'pc_audit_monitors', ['manufacturer','model','serial_number']);
            self::insertRows($audit->id, $data['gpu'] ?? [], 'pc_audit_gpu', ['name','vram','driver_version']);
            self::insertRows($audit->id, $data['battery'] ?? [], 'pc_audit_battery', ['name','status','charge_percent']);
            self::insertRows($audit->id, $data['network'] ?? [], 'pc_audit_network', ['type','name','description','ipv4','gateway','mac','dns','dhcp','connection_status','link_speed']);
            self::insertRows($audit->id, $data['antivirus'] ?? [], 'pc_audit_antivirus', ['display_name','status','executable_path','signature_version']);
            self::insertRows($audit->id, $data['bitlocker'] ?? [], 'pc_audit_bitlocker', ['mount_point','protection_status','volume_status','encryption_percent']);
            self::insertRows($audit->id, $data['firewall'] ?? [], 'pc_audit_firewall', ['profile','enabled']);
            self::insertRows($audit->id, $data['licenses'] ?? [], 'pc_audit_licenses', ['product_type','product_name','status','partial_product_key']);
            self::insertRows($audit->id, $data['software'] ?? [], 'pc_audit_software', ['name','version','publisher','install_date','estimated_size']);
            return $audit;
        });

        return response()->json(['ok' => true, 'id' => $audit->id, 'message' => 'Audit đã được ghi nhận.'], 201);
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
        $now = now();
        $insert = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $item = ['pc_audit_id' => $auditId, 'created_at' => $now, 'updated_at' => $now];
            foreach ($columns as $column) $item[$column] = $row[$column] ?? null;
            $insert[] = $item;
        }
        if ($insert) DB::table($table)->insert($insert);
    }
}
