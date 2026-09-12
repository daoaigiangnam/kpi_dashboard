<?php

namespace App\Services\ItTools;

use App\Models\ItToolAudit;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AuditExcelService
{
    private int $exportLimit = 1000;

    public function setExportLimit(int $limit): self
    {
        $this->exportLimit = min(max($limit, 1), 1000);
        return $this;
    }

    public function export(?string $domain = null): Spreadsheet
    {
        $query = ItToolAudit::query()->latest();
        if ($domain !== null && trim($domain) !== '') {
            $query->where('domain', 'like', '%' . trim($domain) . '%');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('IT Audit Summary');

        $headers = [
            'Checked At','Domain','WAN IP','Audit Status','Duration (ms)',
            'Domain Expiry','Domain Days Left','Domain Source',
            'HTTP Status','HTTP Online','HTTP Final URL','HTTP Response (ms)','HTTP Content-Type','HTTP Server','HTTP HSTS',
            'HTTPS Status','HTTPS Online','HTTPS Final URL','HTTPS Response (ms)','HTTPS Content-Type','HTTPS Server','HTTPS HSTS','HTTPS Transport Verified',
            'SSL Vendor','SSL Subject','SSL Issuer','SSL Valid From','SSL Expiry','SSL Days Left','Hostname Match','TLS Version','Cipher','SAN',
            'Resolved IPv4','Primary IP','IP ASN','IP Network','IP Organization','IP Provider',
            'DNS Provider','DNS Nameservers','DNSSEC','IPv4 Count','IPv6 Count','NS Count','MX Count','DNS Record Types','DNS Record Count',
            'Email Provider','SPF','DMARC','DKIM','MTA-STS','TLS-RPT',
            'CDN','WAF','Hosting Provider','Services Checked','Services Online','Services DNS Only','Services Not Found','Service Summary',
            'Error',
        ];

        $groups = [
            ['Audit', 1, 5], ['Domain', 6, 8], ['Website / HTTP', 9, 15],
            ['Website / HTTPS', 16, 23], ['SSL / TLS', 24, 33], ['IP / Hosting', 34, 39],
            ['DNS Summary', 40, 47], ['Email Security', 48, 53],
            ['Provider / Service Discovery', 54, 61], ['Error', 62, 62],
        ];

        foreach ($groups as [$title, $start, $end]) {
            $startLetter = $this->columnLetter($start);
            $endLetter = $this->columnLetter($end);
            if ($start !== $end) {
                $sheet->mergeCells($startLetter . '1:' . $endLetter . '1');
            }
            $sheet->setCellValue($startLetter . '1', $title);
        }
        foreach ($headers as $index => $header) {
            $sheet->setCellValue($this->columnLetter($index + 1) . '2', $header);
        }

        $row = 3;
        foreach ($query->limit($this->exportLimit)->get() as $audit) {
            $r = (array) ($audit->result ?? []);
            $d = (array) ($r['domain_audit'] ?? []);
            $s = (array) ($r['ssl_audit'] ?? []);
            $website = (array) ($r['website_audit'] ?? []);
            $http = (array) ($website['http'] ?? []);
            $https = (array) ($website['https'] ?? []);
            $i = (array) ($r['ip_audit'] ?? []);
            $e = (array) ($r['email_audit'] ?? []);
            $dns = (array) ($r['dns_audit'] ?? []);
            $records = (array) ($dns['records'] ?? []);
            $p = (array) ($r['provider_detection'] ?? []);
            $services = (array) ($r['service_discovery'] ?? []);
            $serviceRows = collect($services['services'] ?? [])->filter(fn ($v) => is_array($v));

            $types = collect($records)->filter(fn ($items) => !empty($items))->keys()->implode(', ');
            $recordCount = collect($records)->sum(fn ($items) => is_array($items) ? count($items) : 0);
            $nameservers = collect($dns['dns_nameservers'] ?? [])->filter()->implode(', ');
            $resolvedIpv4 = collect($r['resolved_ipv4'] ?? [])->filter()->implode(', ');
            $san = collect($s['san'] ?? [])->filter()->implode(', ');
            $dkim = collect($e['dkim'] ?? [])->map(fn ($value, $selector) => $selector . ': ' . (!empty($value['present']) ? 'PASS' : 'MISSING'))->implode('; ');
            $serviceOnline = $serviceRows->where('status', 'online')->count();
            $serviceDnsOnly = $serviceRows->where('status', 'dns_only')->count();
            $serviceNotFound = $serviceRows->where('status', 'not_found')->count();
            $serviceSummary = $serviceRows->map(function ($service) {
                $hostname = $service['hostname'] ?? ($service['label'] ?? 'unknown');
                $status = strtoupper((string) ($service['status'] ?? 'unknown'));
                $ips = collect($service['ips'] ?? [])->filter()->implode(',');
                return $hostname . '=' . $status . ($ips !== '' ? ' [' . $ips . ']' : '');
            })->implode('; ');

            $values = [
                $audit->created_at?->toIso8601String(), $audit->domain, $audit->wan_ip, strtoupper((string) $audit->status), $audit->duration_ms,
                $d['expires_at'] ?? null, isset($d['days_remaining']) ? (int) floor((float) $d['days_remaining']) : null, $d['source'] ?? null,
                $http['status'] ?? null, isset($http['online']) ? ($http['online'] ? 'YES' : 'NO') : null, $http['final_url'] ?? null, $http['response_time_ms'] ?? null, $http['content_type'] ?? null, $http['server'] ?? null, isset($http['hsts']) ? ($http['hsts'] ? 'YES' : 'NO') : null,
                $https['status'] ?? null, isset($https['online']) ? ($https['online'] ? 'YES' : 'NO') : null, $https['final_url'] ?? null, $https['response_time_ms'] ?? null, $https['content_type'] ?? null, $https['server'] ?? null, isset($https['hsts']) ? ($https['hsts'] ? 'YES' : 'NO') : null, isset($https['transport_verified']) ? ($https['transport_verified'] ? 'YES' : 'NO') : null,
                $s['vendor'] ?? null, $s['subject'] ?? null, $s['issuer'] ?? null, $s['valid_from'] ?? null, $s['valid_to'] ?? null, isset($s['days_remaining']) ? (int) floor((float) $s['days_remaining']) : null, isset($s['verify']) ? ($s['verify'] ? 'YES' : 'NO') : null, $s['tls_version'] ?? null, $s['cipher'] ?? null, $san,
                $resolvedIpv4, $i['ip'] ?? null, $i['asn'] ?? null, $i['network'] ?? null, $i['organization'] ?? null, $i['provider'] ?? null,
                $dns['dns_provider'] ?? ($p['dns_provider'] ?? null), $nameservers, isset($dns['dnssec']) ? ($dns['dnssec'] ? 'DETECTED' : 'NOT DETECTED') : null, count($records['A'] ?? []), count($records['AAAA'] ?? []), count($records['NS'] ?? []), count($records['MX'] ?? []), $types, $recordCount,
                $e['provider'] ?? null, !empty($e['spf_present']) ? ($e['spf'] ?? 'PASS') : ($e['spf'] ?? 'MISSING'), !empty($e['dmarc_present']) ? ($e['dmarc'] ?? 'PASS') : ($e['dmarc'] ?? 'MISSING'), $dkim, !empty($e['mta_sts_present']) ? ($e['mta_sts'] ?? 'PASS') : ($e['mta_sts'] ?? 'MISSING'), !empty($e['tls_rpt_present']) ? ($e['tls_rpt'] ?? 'PASS') : ($e['tls_rpt'] ?? 'MISSING'),
                $p['cdn'] ?? null, $p['waf'] ?? null, $p['hosting_provider'] ?? null, $services['checked_hosts'] ?? $serviceRows->count(), $serviceOnline, $serviceDnsOnly, $serviceNotFound, $serviceSummary,
                $audit->error,
            ];

            foreach ($values as $col => $value) {
                $sheet->setCellValue($this->columnLetter($col + 1) . $row, $value);
            }
            $row++;
        }

        $lastColumn = count($headers);
        $lastRow = max(2, $row - 1);
        $lastColumnLetter = $this->columnLetter($lastColumn);
        $sheet->freezePane('A3');
        $sheet->setAutoFilter('A2:' . $lastColumnLetter . $lastRow);
        $sheet->getRowDimension(1)->setRowHeight(25);
        $sheet->getRowDimension(2)->setRowHeight(42);
        $sheet->getStyle('A1:' . $lastColumnLetter . '1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFF');
        $sheet->getStyle('A1:' . $lastColumnLetter . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('17365D');
        $sheet->getStyle('A1:' . $lastColumnLetter . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A2:' . $lastColumnLetter . '2')->getFont()->setBold(true);
        $sheet->getStyle('A2:' . $lastColumnLetter . '2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9EAF7');
        $sheet->getStyle('A2:' . $lastColumnLetter . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A1:' . $lastColumnLetter . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('D9E1E8');
        $sheet->getStyle('A3:' . $lastColumnLetter . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);

        foreach (range(1, $lastColumn) as $col) {
            $sheet->getColumnDimension($this->columnLetter($col))->setWidth(16);
        }
        foreach ([2, 6, 8, 24, 25, 26, 28, 35, 36, 37, 38, 39, 48, 54, 55, 56, 57, 58] as $col) {
            $sheet->getColumnDimension($this->columnLetter($col))->setWidth(20);
        }
        foreach ([11, 18, 33, 40, 41, 47, 50, 51, 52, 60, 61, 62] as $col) {
            $sheet->getColumnDimension($this->columnLetter($col))->setWidth(30);
        }
        $sheet->getColumnDimension('A')->setWidth(23);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension($this->columnLetter(62))->setWidth(40);

        return $spreadsheet;
    }

    public function output(?string $domain = null): Xlsx
    {
        return new Xlsx($this->export($domain));
    }

    private function columnLetter(int $column): string
    {
        $letter = '';
        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $letter = chr(65 + $remainder) . $letter;
            $column = intdiv($column - 1, 26);
        }
        return $letter;
    }
}