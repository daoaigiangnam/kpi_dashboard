<?php

namespace App\Services\ItTools;

use App\Models\ItToolAudit;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AuditExcelService
{
    public function export(?string $domain = null): Spreadsheet
    {
        $query = ItToolAudit::query()->latest();
        if ($domain !== null && trim($domain) !== '') {
            $query->where('domain', 'like', '%' . trim($domain) . '%');
        }

        $sheet = (new Spreadsheet())->getActiveSheet();
        $headers = ['Checked At','Domain','WAN IP','Status','Duration ms','Domain Expiry','Domain Days Left','SSL Vendor','SSL Expiry','SSL Days Left','HTTPS Status','HTTPS Online','Response ms','IP','IP Provider','Email Provider','SPF','DMARC','MTA-STS','TLS-RPT'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $header);
        }

        $row = 2;
        foreach ($query->limit(5000)->get() as $audit) {
            $r = (array) ($audit->result ?? []);
            $d = (array) ($r['domain_audit'] ?? []);
            $s = (array) ($r['ssl_audit'] ?? []);
            $w = (array) ($r['website_audit']['https'] ?? []);
            $i = (array) ($r['ip_audit'] ?? []);
            $e = (array) ($r['email_audit'] ?? []);

            $values = [
                $audit->created_at?->toIso8601String(), $audit->domain, $audit->wan_ip, $audit->status, $audit->duration_ms,
                $d['expires_at'] ?? null, $d['days_remaining'] ?? null,
                $s['vendor'] ?? null, $s['valid_to'] ?? null, $s['days_remaining'] ?? null,
                $w['status'] ?? null, isset($w['online']) ? ($w['online'] ? 'YES' : 'NO') : null, $w['response_time_ms'] ?? null,
                $i['ip'] ?? null, $i['provider'] ?? ($i['organization'] ?? null), $e['provider'] ?? null,
                isset($e['spf_present']) ? ($e['spf_present'] ? 'PASS' : 'MISSING') : null,
                isset($e['dmarc_present']) ? ($e['dmarc_present'] ? 'PASS' : 'MISSING') : null,
                isset($e['mta_sts_present']) ? ($e['mta_sts_present'] ? 'PASS' : 'MISSING') : null,
                isset($e['tls_rpt_present']) ? ($e['tls_rpt_present'] ? 'PASS' : 'MISSING') : null,
            ];
            foreach ($values as $col => $value) {
                $sheet->setCellValueByColumnAndRow($col + 1, $row, $value);
            }
            $row++;
        }

        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        return $sheet->getParent();
    }

    public function output(?string $domain = null): Xlsx
    {
        return new Xlsx($this->export($domain));
    }
}
