<?php

namespace App\Services\ItTools;

use App\Models\ItToolAudit;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AuditExcelService
{
    public function export(?string $domain = null): Spreadsheet
    {
        $query = ItToolAudit::query()->latest();
        if ($domain !== null && trim($domain) !== '') {
            $query->where('domain', 'like', '%' . trim($domain) . '%');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('IT Audit Summary');

        // Bulk export intentionally contains summary fields only; DNS zone records are not expanded here.
        $headers = [
            'Checked At','Domain','WAN IP','Audit Status','Duration (ms)',
            'Domain Expiry','Domain Days Left','Domain Source',
            'Website','HTTPS Status','Online','Response (ms)',
            'SSL Vendor','SSL Subject','SSL Issuer','SSL Valid From','SSL Expiry','SSL Days Left','Hostname Match','TLS Version','Cipher',
            'Resolved IPv4','IP','IP ASN','IP Network','IP Organization','IP Provider',
            'DNS Provider','DNS Nameservers','DNSSEC','IPv4 Count','IPv6 Count','NS Count','MX Count','DNS Record Types',
            'Email Provider','SPF','DMARC','DKIM','MTA-STS','TLS-RPT',
            'CDN','WAF','Hosting Provider','Service Discovery','Error',
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $row = 2;
        foreach ($query->limit(5000)->get() as $audit) {
            $r = (array) ($audit->result ?? []);
            $d = (array) ($r['domain_audit'] ?? []);
            $s = (array) ($r['ssl_audit'] ?? []);
            $w = (array) ($r['website_audit']['https'] ?? []);
            $i = (array) ($r['ip_audit'] ?? []);
            $e = (array) ($r['email_audit'] ?? []);
            $dns = (array) ($r['dns_audit'] ?? []);
            $records = (array) ($dns['records'] ?? []);
            $p = (array) ($r['provider_detection'] ?? []);
            $services = (array) ($r['service_discovery'] ?? []);

            $types = collect($records)->filter(fn ($items) => !empty($items))->keys()->implode(', ');
            $nameservers = collect($dns['dns_nameservers'] ?? [])->filter()->implode(', ');
            $resolvedIpv4 = collect($r['resolved_ipv4'] ?? [])->filter()->implode(', ');
            $dkim = collect($e['dkim'] ?? [])->map(fn ($value, $selector) => $selector . ': ' . (!empty($value['present']) ? 'PASS' : 'MISSING'))->implode('; ');
            $serviceSummary = collect($services)->filter(fn ($value) => is_scalar($value))->map(fn ($value, $key) => $key . '=' . $value)->implode('; ');

            $values = [
                $audit->created_at?->toIso8601String(), $audit->domain, $audit->wan_ip, $audit->status, $audit->duration_ms,
                $d['expires_at'] ?? null, $d['days_remaining'] ?? null, $d['source'] ?? null,
                !empty($w['online']) ? 'ONLINE' : (!empty($w['status']) ? 'UNREACHABLE' : 'OFFLINE'), $w['status'] ?? null, isset($w['online']) ? ($w['online'] ? 'YES' : 'NO') : null, $w['response_time_ms'] ?? null,
                $s['vendor'] ?? null, $s['subject'] ?? null, $s['issuer'] ?? null, $s['valid_from'] ?? null, $s['valid_to'] ?? null, $s['days_remaining'] ?? null, isset($s['verify']) ? ($s['verify'] ? 'YES' : 'NO') : null, $s['tls_version'] ?? null, $s['cipher'] ?? null,
                $resolvedIpv4, $i['ip'] ?? null, $i['asn'] ?? null, $i['network'] ?? null, $i['organization'] ?? null, $i['provider'] ?? null,
                $dns['dns_provider'] ?? ($p['dns_provider'] ?? null), $nameservers, isset($dns['dnssec']) ? ($dns['dnssec'] ? 'DETECTED' : 'NOT DETECTED') : null, count($records['A'] ?? []), count($records['AAAA'] ?? []), count($records['NS'] ?? []), count($records['MX'] ?? []), $types,
                $e['provider'] ?? null, !empty($e['spf_present']) ? ($e['spf'] ?? 'PASS') : ($e['spf'] ?? 'MISSING'), !empty($e['dmarc_present']) ? ($e['dmarc'] ?? 'PASS') : ($e['dmarc'] ?? 'MISSING'), $dkim, !empty($e['mta_sts_present']) ? ($e['mta_sts'] ?? 'PASS') : ($e['mta_sts'] ?? 'MISSING'), !empty($e['tls_rpt_present']) ? ($e['tls_rpt'] ?? 'PASS') : ($e['tls_rpt'] ?? 'MISSING'),
                $p['cdn'] ?? null, $p['waf'] ?? null, $p['hosting_provider'] ?? null, $serviceSummary, $audit->error,
            ];

            foreach ($values as $col => $value) {
                $sheet->setCellValueByColumnAndRow($col + 1, $row, $value);
            }
            $row++;
        }

        $lastColumn = count($headers);
        $lastRow = max(1, $row - 1);
        $sheet->freezePane('A2');
        $sheet->setAutoFilterByColumnAndRow(1, 1, $lastColumn, $lastRow);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyleByColumnAndRow(1, 1, $lastColumn, 1)->getFont()->setBold(true);
        $sheet->getStyleByColumnAndRow(1, 1, $lastColumn, 1)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyleByColumnAndRow(1, 1, $lastColumn, 1)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9EAF7');
        $sheet->getStyleByColumnAndRow(1, 2, $lastColumn, $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);

        foreach (range(1, $lastColumn) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public function output(?string $domain = null): Xlsx
    {
        return new Xlsx($this->export($domain));
    }
}
