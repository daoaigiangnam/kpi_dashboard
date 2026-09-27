<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PcAuditEngine;
use PHPUnit\Framework\TestCase;

class PcAuditEngineTest extends TestCase
{
    public function test_secure_pc_is_pass_or_review_only_when_telemetry_is_missing(): void
    {
        $data = [
            'windows' => ['caption' => 'Microsoft Windows 11 Pro', 'uptime_hours' => 12],
            'licenses' => ['windows' => [['status' => 1]]],
            'security' => [
                'antivirus' => [['display_name' => 'Microsoft Defender']],
                'bitlocker' => [['protection_status' => 'On', 'volume_status' => 'FullyEncrypted']],
                'firewall' => [['profile' => 'Domain', 'enabled' => true], ['profile' => 'Private', 'enabled' => true]],
                'tpm' => ['present' => true, 'ready' => true],
                'secure_boot' => true,
            ],
            'storage' => [['total_gb' => 500, 'used_gb' => 200]],
            'network' => [['ipv4' => ['192.168.1.10']]],
        ];

        $result = (new PcAuditEngine())->evaluate($data);

        self::assertSame('REVIEW', $result['status']);
        self::assertSame(9, $result['summary']['pass']);
        self::assertSame(1, $result['summary']['review']);
        self::assertSame(0, $result['summary']['fail']);
    }

    public function test_disabled_firewall_is_fail(): void
    {
        $result = (new PcAuditEngine())->evaluate([
            'windows' => ['caption' => 'Windows 11', 'uptime_hours' => 1],
            'licenses' => ['windows' => [['status' => 1]]],
            'security' => [
                'antivirus' => [['display_name' => 'Defender']],
                'bitlocker' => [['protection_status' => 'On', 'volume_status' => 'FullyEncrypted']],
                'firewall' => [['profile' => 'Domain', 'enabled' => false]],
                'tpm' => ['present' => true, 'ready' => true],
                'secure_boot' => true,
            ],
            'storage' => [['total_gb' => 500, 'used_gb' => 100]],
            'network' => [['ipv4' => ['10.0.0.10']]],
        ]);

        self::assertSame('FAIL', $result['status']);
        self::assertSame(1, $result['summary']['fail']);
    }
}
