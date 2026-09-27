<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Evaluates the security/health signals collected by the PC Audit PowerShell tool.
 * Missing/unavailable telemetry is REVIEW rather than FAIL so a collector limitation
 * never becomes a false security finding.
 */
class PcAuditEngine
{
    public function evaluate(array $data): array
    {
        $security = is_array($data['security'] ?? null) ? $data['security'] : [];
        $windows = is_array($data['windows'] ?? null) ? $data['windows'] : [];
        $results = [];

        $this->add($results, 'windows', 'Windows', $this->windowsResult($windows));
        $this->add($results, 'windows_license', 'Windows License', $this->licenseResult($data['licenses']['windows'] ?? []));
        $this->add($results, 'antivirus', 'Antivirus', $this->antivirusResult($security['antivirus'] ?? []));
        $this->add($results, 'bitlocker', 'BitLocker', $this->bitlockerResult($security['bitlocker'] ?? []));
        $this->add($results, 'firewall', 'Firewall', $this->firewallResult($security['firewall'] ?? []));
        $this->add($results, 'tpm', 'TPM', $this->tpmResult($security['tpm'] ?? null));
        $this->add($results, 'secure_boot', 'Secure Boot', $this->booleanResult($security['secure_boot'] ?? null, 'Secure Boot'));
        $this->add($results, 'storage', 'Storage', $this->storageResult($data['storage'] ?? []));
        $this->add($results, 'network', 'Network', $this->networkResult($data['network'] ?? []));
        $this->add($results, 'uptime', 'Uptime', $this->uptimeResult($windows['uptime_hours'] ?? null));

        $pass = count(array_filter($results, fn ($r) => $r['status'] === 'PASS'));
        $review = count(array_filter($results, fn ($r) => $r['status'] === 'REVIEW'));
        $fail = count(array_filter($results, fn ($r) => $r['status'] === 'FAIL'));
        $total = count($results);
        $score = $total > 0 ? (int) round(($pass / $total) * 100) : 0;
        $status = $fail > 0 ? 'FAIL' : ($review > 0 ? 'REVIEW' : 'PASS');

        return [
            'status' => $status,
            'score' => $score,
            'summary' => ['total' => $total, 'pass' => $pass, 'review' => $review, 'fail' => $fail],
            'results' => array_values($results),
            'engine_version' => '1.0.0',
        ];
    }

    private function add(array &$results, string $key, string $label, array $result): void
    {
        $results[] = array_merge(['key' => $key, 'label' => $label], $result);
    }

    private function windowsResult(array $windows): array
    {
        if (!$windows) return $this->review('Không thu thập được thông tin Windows.');
        return !empty($windows['caption']) ? $this->pass($windows['caption']) : $this->review('Thiếu tên hệ điều hành.');
    }

    private function licenseResult(mixed $licenses): array
    {
        if (!is_array($licenses) || !$licenses) return $this->review('Không có dữ liệu license Windows.');
        $valid = array_filter($licenses, fn ($x) => is_array($x) && (string)($x['status'] ?? '') === '1');
        return $valid ? $this->pass('Windows đã kích hoạt.') : $this->fail('Không tìm thấy license Windows đang ở trạng thái kích hoạt.');
    }

    private function antivirusResult(mixed $items): array
    {
        if (!is_array($items) || !$items) return $this->review('Không thu thập được Antivirus từ SecurityCenter2.');
        return $this->pass('Đã phát hiện Antivirus: '.count($items).' sản phẩm.');
    }

    private function bitlockerResult(mixed $items): array
    {
        if (!is_array($items) || !$items) return $this->review('Không có dữ liệu BitLocker.');
        $bad = array_filter($items, function ($x) {
            if (!is_array($x)) return true;
            $protection = strtolower((string)($x['protection_status'] ?? ''));
            $volume = strtolower((string)($x['volume_status'] ?? ''));
            return !str_contains($protection, 'on') && !str_contains($protection, '1')
                || (!empty($volume) && !str_contains($volume, 'fullyencrypted'));
        });
        return $bad ? $this->fail('Có volume BitLocker chưa được bảo vệ/ mã hóa đầy đủ.') : $this->pass('Các volume BitLocker đã thu thập đều đang bảo vệ.');
    }

    private function firewallResult(mixed $items): array
    {
        if (!is_array($items) || !$items) return $this->review('Không có dữ liệu Firewall profile.');
        $disabled = array_filter($items, fn ($x) => is_array($x) && array_key_exists('enabled', $x) && !$x['enabled']);
        return $disabled ? $this->fail('Có Firewall profile đang tắt.') : $this->pass('Các Firewall profile đều đang bật.');
    }

    private function tpmResult(mixed $tpm): array
    {
        if (!is_array($tpm) || !array_key_exists('present', $tpm)) return $this->review('Không có dữ liệu TPM.');
        if ($tpm['present'] === true && $tpm['ready'] === true) return $this->pass('TPM hiện diện và sẵn sàng.');
        if ($tpm['present'] === false) return $this->fail('Không phát hiện TPM.');
        return $this->review('TPM chưa ở trạng thái sẵn sàng.');
    }

    private function booleanResult(mixed $value, string $label): array
    {
        if ($value === null) return $this->review("Không xác định được {$label}.");
        return $value === true ? $this->pass("{$label} đang bật.") : $this->fail("{$label} đang tắt hoặc không đạt yêu cầu.");
    }

    private function storageResult(mixed $items): array
    {
        if (!is_array($items) || !$items) return $this->review('Không có dữ liệu Disk.');
        $low = array_filter($items, function ($x) {
            if (!is_array($x)) return false;
            $total = (float)($x['total_gb'] ?? 0);
            $used = (float)($x['used_gb'] ?? 0);
            return $total > 0 && (($total - $used) / $total) < 0.10;
        });
        return $low ? $this->review('Có ổ đĩa còn dưới 10% dung lượng trống.') : $this->pass('Dung lượng trống của các ổ đĩa ở mức chấp nhận được.');
    }

    private function networkResult(mixed $items): array
    {
        if (!is_array($items) || !$items) return $this->review('Không có adapter mạng đang sử dụng.');
        $valid = array_filter($items, fn ($x) => is_array($x) && !empty($x['ipv4']));
        return $valid ? $this->pass('Đã phát hiện adapter mạng có IPv4.') : $this->review('Không tìm thấy IPv4 hợp lệ.');
    }

    private function uptimeResult(mixed $hours): array
    {
        if (!is_numeric($hours)) return $this->review('Không có dữ liệu uptime.');
        return (float)$hours <= 168 ? $this->pass('Uptime không vượt quá 7 ngày.') : $this->review('Uptime vượt quá 7 ngày; nên restart máy.');
    }

    private function pass(string $message): array { return ['status' => 'PASS', 'message' => $message]; }
    private function review(string $message): array { return ['status' => 'REVIEW', 'message' => $message]; }
    private function fail(string $message): array { return ['status' => 'FAIL', 'message' => $message]; }
}
