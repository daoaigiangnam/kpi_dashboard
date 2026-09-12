<?php

namespace App\Services\ItTools;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AuditExcelService
{
    /** Build an Excel workbook from exactly the rows currently rendered by Check Domain. */
    public function outputRows(array $items): Spreadsheet
    {
        $headers = ['Checked At','Domain','WAN IP','Status','Domain Expiry','Domain Days','Domain Source','HTTP Status','HTTP Online','HTTP Final URL','HTTP ms','HTTPS Status','HTTPS Online','HTTPS Final URL','SSL Vendor','SSL Expiry','SSL Days','Hostname','Primary IP','Network','ASN','IP Provider','DNS Provider','Nameservers','DNSSEC','Record Types','MX','Mail Provider','SPF','DMARC','DKIM','MTA-STS','TLS-RPT','CDN','WAF','Hosting Provider','Services Checked','Services Online','Services Not Found','Service Summary','Error'];
        $groups = [['Audit',1,4],['Domain',5,7],['Website / HTTP',8,11],['Website / HTTPS',12,14],['SSL / TLS',15,18],['IP / Hosting',19,22],['DNS Summary',23,27],['Email Security',28,33],['Provider / Service Discovery',34,40],['Error',41,41]];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Check Domain');
        foreach ($groups as [$title,$start,$end]) {
            $a=$this->columnLetter($start); $b=$this->columnLetter($end);
            if($start!==$end) $sheet->mergeCells($a.'1:'.$b.'1');
            $sheet->setCellValue($a.'1',$title);
        }
        foreach($headers as $n=>$header) $sheet->setCellValue($this->columnLetter($n+1).'2',$header);
        $row=3;
        foreach($items as $item){
            $item=is_array($item)?$item:[]; $a=is_array($item['audit']??null)?$item['audit']:$item;
            $d=(array)($a['domain_audit']??[]); $s=(array)($a['ssl_audit']??[]); $w=(array)($a['website_audit']??[]);
            $h=(array)($w['http']??[]); $hs=(array)($w['https']??[]); $i=(array)($a['ip_audit']??[]); $e=(array)($a['email_audit']??[]);
            $dns=(array)($a['dns_audit']??[]); $p=(array)($a['provider_detection']??[]); $sv=(array)($a['service_discovery']??[]);
            $services=collect($sv['services']??[])->filter(fn($v)=>is_array($v));
            $dkim=collect($e['dkim']??[])->map(fn($v,$selector)=>$selector.': '.(!empty($v['present'])?'PASS':'MISSING'))->implode('; ');
            $serviceSummary=$services->map(fn($v)=>($v['hostname']??($v['label']??'unknown')).'='.strtoupper((string)($v['status']??'unknown')))->implode('; ');
            $values=[
                $a['checked_at']??null,$item['domain']??($a['domain']??null),$item['wan_ip']??($a['wan_ip_supplied']??null),strtoupper((string)($item['status']??'ok')),
                $d['expires_at']??'N/A',$this->days($d['days_remaining']??null),$d['source']??'—',$h['status']??'—',$this->bool($h['online']??null),$h['final_url']??'—',$h['response_time_ms']??'—',
                $hs['status']??'—',$this->bool($hs['online']??null),$hs['final_url']??'—',$s['vendor']??'N/A',$s['valid_to']??'N/A',$this->days($s['days_remaining']??null),$this->bool($s['verify']??null),
                $i['ip']??'N/A',$i['network']??'—',$i['asn']??'—',$i['provider']??'—',$dns['dns_provider']??($p['dns_provider']??'N/A'),collect($dns['dns_nameservers']??[])->filter()->implode(', ')?:'N/A',
                !empty($dns['dnssec'])?'DETECTED':'NOT DETECTED',collect($dns['record_types_found']??[])->filter()->implode(', ')?:'—',count($e['mx']??[]),$e['provider']??'N/A',
                !empty($e['spf_present'])?($e['spf']??'PASS'):($e['spf']??'MISSING'),!empty($e['dmarc_present'])?($e['dmarc']??'PASS'):($e['dmarc']??'MISSING'),$dkim?:'Not checked',
                !empty($e['mta_sts_present'])?($e['mta_sts']??'PASS'):($e['mta_sts']??'MISSING'),!empty($e['tls_rpt_present'])?($e['tls_rpt']??'PASS'):($e['tls_rpt']??'MISSING'),
                $p['cdn']??'—',$p['waf']??'—',$p['hosting_provider']??'—',$sv['checked_hosts']??$services->count(),$services->where('status','online')->count(),$services->where('status','not_found')->count(),$serviceSummary?:'—',$item['error']??''
            ];
            foreach($values as $n=>$value) $sheet->setCellValue($this->columnLetter($n+1).$row,$value); $row++;
        }
        $last=$this->columnLetter(count($headers)); $lastRow=max(2,$row-1);
        $sheet->freezePane('A3'); $sheet->setAutoFilter('A2:'.$last.$lastRow); $sheet->getRowDimension(1)->setRowHeight(25); $sheet->getRowDimension(2)->setRowHeight(42);
        $sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFF');
        $sheet->getStyle('A1:'.$last.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('17365D');
        $sheet->getStyle('A1:'.$last.'1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A2:'.$last.'2')->getFont()->setBold(true); $sheet->getStyle('A2:'.$last.'2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9EAF7');
        $sheet->getStyle('A2:'.$last.'2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A1:'.$last.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('D9E1E8');
        $sheet->getStyle('A3:'.$last.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        foreach(range(1,count($headers)) as $col) $sheet->getColumnDimension($this->columnLetter($col))->setWidth(16);
        foreach([2,5,7,15,16,19,20,21,22,23,28,34,35,36] as $col) $sheet->getColumnDimension($this->columnLetter($col))->setWidth(20);
        foreach([10,14,26,31,40,41] as $col) $sheet->getColumnDimension($this->columnLetter($col))->setWidth(30);
        $sheet->getColumnDimension('A')->setWidth(23); $sheet->getColumnDimension('B')->setWidth(28); $sheet->getColumnDimension('AK')->setWidth(40);
        return $spreadsheet;
    }
    public function output(array $items): Spreadsheet { return $this->outputRows($items); }
    private function bool($value): string { return $value===true?'YES':($value===false?'NO':'—'); }
    private function days($value): string { if($value===null||$value==='')return'N/A'; $number=(float)$value; return is_finite($number)?number_format((int)floor($number)).' days':(string)$value; }
    private function columnLetter(int $column): string { $letter=''; while($column>0){$remainder=($column-1)%26;$letter=chr(65+$remainder).$letter;$column=intdiv($column-1,26);} return $letter; }
}
