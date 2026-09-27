<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PcAudit;
use App\Models\ServiceCustomer;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PcAuditController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $customerId = $request->query('customer_id');
        $customers = ServiceCustomer::query()->orderBy('name')->get(['id','code','name']);
        $audits = PcAudit::query()->with(['auditCode.branch.customer'])
            ->when($customerId, fn($q) => $q->whereHas('auditCode.branch', fn($b) => $b->where('customer_id',$customerId)))
            ->when($search !== '', function($q) use ($search) {
                $like = "%{$search}%";
                $q->where(function($x) use ($like) {
                    $x->where('computer_name','like',$like)
                        ->orWhere('serial_number','like',$like)
                        ->orWhere('employee_name','like',$like)
                        ->orWhere('department','like',$like)
                        ->orWhere('manufacturer','like',$like)
                        ->orWhere('model','like',$like)
                        ->orWhereHas('auditCode',fn($c)=>$c->where('code','like',$like));
                });
            })->latest('collected_at')->paginate(25)->withQueryString();
        return view('admin.pc-audit.index', compact('audits','search','customerId','customers'));
    }

    public function show(PcAudit $pcAudit)
    {
        $pcAudit->load(['auditCode.branch.customer','details','memory','storage','monitors','gpu','batteries','network','antivirus','bitlocker','firewall','licenses','software']);
        return view('admin.pc-audit.show',['audit'=>$pcAudit]);
    }

    public function export(Request $request): StreamedResponse
    {
        $ids = collect($request->input('ids', []))
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()->take(200)->values();

        abort_if($ids->isEmpty(), 422, 'Chưa chọn máy để xuất Excel.');

        $audits = PcAudit::with([
            'auditCode.branch.customer','details','memory','storage','monitors','gpu',
            'batteries','network','antivirus','bitlocker','firewall','licenses','software'
        ])->whereIn('id', $ids)->get();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $headers = [
            'Họ Tên','Username','Domain','Tên máy tính','Manufacturer','Model','Serial Number','Asset Tag',
            'Mainboard','BIOS','CPU','RAM','HDD','Monitor','VGA','Battery','OS','Windows Update','Last Boot',
            'Uptime','LAN','WIFI','MODEM','IP','MAC','Gateway','DNS','DHCP','Connection Status','Link Speed',
            'Antivirus','BitLocker','Firewall','TPM','Secure Boot','Windows Activation','Office Activation',
            'Ngày thu thập','SOFTWARE'
        ];

        foreach ($audits as $audit) {
            $sheet = $spreadsheet->createSheet();
            $baseName = (string) ($audit->computer_name ?: 'PC-' . $audit->id);
            $safeName = str_replace(['\\', '/', '?', '*', '[', ']', ':'], '_', $baseName);
            $safeName = mb_substr($safeName, 0, 25) . '-' . $audit->id;
            $sheet->setTitle(mb_substr($safeName, 0, 31));

            $rows = $this->inventoryRows($audit);
            $sheet->fromArray([$headers, ...$rows], null, 'A1');

            $lastRow = max(1, count($rows) + 1);
            $lastCol = count($headers);

            // Header: clean, readable, filterable.
            $sheet->getStyle('A1:' . $this->columnLetter($lastCol) . '1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '126B6F']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0D5255']]],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(34);

            $sheet->getStyle('A2:' . $this->columnLetter($lastCol) . $lastRow)->applyFromArray([
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2E3']]],
            ]);

            if ($lastRow > 1) {
                for ($row = 2; $row <= $lastRow; $row++) {
                    if ($row % 2 === 0) {
                        $sheet->getStyle(
    'A' . $row . ':' . $this->columnLetter($lastCol) . $row
)
                            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5FAFA');
                    }
                    $sheet->getRowDimension($row)->setRowHeight(42);
                }
            }

            // Highlight the Software column so the long inventory remains easy to scan.
            $sheet->getStyle(
    $this->columnLetter($lastCol) . '2:' .
    $this->columnLetter($lastCol) . $lastRow
)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAF8F4');

            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:' . $this->columnLetter($lastCol) . $lastRow);
            $sheet->getPageSetup()->setOrientation('landscape');
            $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageMargins()->setTop(0.3)->setBottom(0.3)->setLeft(0.25)->setRight(0.25);

            $widths = [18,20,20,18,18,20,18,16,34,25,38,45,32,32,42,22,38,28,22,18,48,48,30,28,28,32,38,25,24,20,38,34,24,18,18,38,42,22,65];
            foreach ($widths as $i => $width) {
                $sheet->getColumnDimension($this->columnLetter($i + 1))->setWidth($width);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            fn() => $writer->save('php://output'),
            'PC_Audit_Inventory_' . now()->format('Ymd-His') . '.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]
        );
    }

    /**
     * Build the export in the same flat inventory layout as the provided sample:
     * one PC per sheet, with repeated machine information and one software item per row.
     */
    private function inventoryRows(PcAudit $audit): array
    {
        $raw = is_array($audit->raw_payload) ? $audit->raw_payload : [];
        $details = $audit->details;

        $hardware = is_array($raw['hardware'] ?? null) ? $raw['hardware'] : [];
        $cpu = is_array($raw['cpu'] ?? null) ? $raw['cpu'] : [];
        $windows = is_array($raw['windows'] ?? null) ? $raw['windows'] : (is_array($audit->operating_system) ? $audit->operating_system : []);
        $security = is_array($raw['security'] ?? null) ? $raw['security'] : [];
        $licenses = is_array($raw['licenses'] ?? null) ? $raw['licenses'] : [];
        $software = $this->listFrom($raw['software'] ?? $audit->software->toArray());

        $memory = $this->listFrom($raw['memory'] ?? $audit->memory->toArray());
        $storage = $this->listFrom($raw['storage'] ?? $audit->storage->toArray());
        $monitors = $this->listFrom($raw['monitors'] ?? $audit->monitors->toArray());
        $gpu = $this->listFrom($raw['gpu'] ?? $audit->gpu->toArray());
        $battery = $this->listFrom($raw['battery'] ?? $audit->batteries->toArray());
        $network = $this->listFrom($raw['network'] ?? $audit->network->toArray());
        $antivirus = $this->listFrom($security['antivirus'] ?? $audit->antivirus->toArray());
        $bitlocker = $this->listFrom($security['bitlocker'] ?? $audit->bitlocker->toArray());
        $firewall = $this->listFrom($security['firewall'] ?? $audit->firewall->toArray());

        $maxRows = max(1, count($software), count($memory), count($storage), count($monitors), count($gpu), count($battery), count($network), count($antivirus), count($bitlocker), count($firewall));
        $rows = [];

        for ($i = 0; $i < $maxRows; $i++) {
            $net = $network[$i] ?? [];
            $wifi = $this->findNetwork($network, ['wifi','wi-fi','wireless']);
            $lan = $this->findNetwork($network, ['ethernet','lan']);

            $rows[] = [
                $audit->employee_name,
                $audit->employee_username,
                $audit->domain,
                $audit->computer_name,
                $audit->manufacturer,
                $audit->model,
                $i === 0 ? $audit->serial_number : '',
                $i === 0 ? $audit->asset_tag : '',
                $i === 0 ? $this->fmtMainboard($audit->mainboard) : '',
                $i === 0 ? $this->fmtBios($audit->bios) : '',
                $i === 0 ? $this->fmtCpu($cpu) : '',
                $this->fmtMemory($memory[$i] ?? null),
                $this->fmtStorage($storage[$i] ?? null),
                $this->fmtMonitor($monitors[$i] ?? null),
                $this->fmtGpu($gpu[$i] ?? null),
                $this->fmtBattery($battery[$i] ?? null),
                $i === 0 ? $this->fmtWindows($windows) : '',
                $this->fmtWindowsUpdate($audit->windows_update, $windows),
                $i === 0 ? $audit->last_boot : '',
                $i === 0 ? $this->formatUptime($audit->uptime) : '',
                $this->fmtNetwork($lan),
                $this->fmtNetwork($wifi),
                '',
                $this->fmtField($net, 'ipv4'),
                $this->fmtField($net, 'mac'),
                $this->fmtField($net, 'gateway'),
                $this->fmtField($net, 'dns'),
                $this->fmtField($net, 'dhcp'),
                $this->fmtField($net, 'connection_status'),
                $this->fmtField($net, 'link_speed'),
                $this->fmtAntivirus($antivirus[$i] ?? ($antivirus[0] ?? null)),
                $this->fmtBitlocker($bitlocker[$i] ?? ($bitlocker[0] ?? null)),
                $this->fmtFirewall($firewall[$i] ?? ($firewall[0] ?? null)),
                $i === 0 ? $this->fmtValue($security['tpm'] ?? $audit->tpm) : '',
                $i === 0 ? ($audit->secure_boot === null ? '' : ($audit->secure_boot ? 'True' : 'False')) : '',
                $this->fmtLicense($licenses['windows'] ?? ($audit->licenses->where('product_type','WINDOWS')->toArray())),
                $this->fmtLicense($licenses['office'] ?? ($audit->licenses->where('product_type','OFFICE')->toArray())),
                $i === 0 ? optional($audit->collected_at)->format('Y-m-d H:i:s') : optional($audit->collected_at)->format('Y-m-d H:i:s'),
                $this->fmtSoftware($software[$i] ?? null),
            ];
        }

        return $rows;
    }

    private function listFrom(mixed $value): array
    {
        if (!is_array($value) || $value === []) return [];
        return array_is_list($value) ? $value : [$value];
    }

    private function findNetwork(array $rows, array $terms): array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $text = strtolower((string)($row['type'] ?? '') . ' ' . ($row['name'] ?? '') . ' ' . ($row['description'] ?? ''));
            foreach ($terms as $term) if (str_contains($text, strtolower($term))) return $row;
        }
        return [];
    }

    private function fmtMainboard(mixed $v): string { return $this->fmtValue($v); }
    private function fmtBios(mixed $v): string { return $this->fmtValue($v); }
    private function fmtCpu(array $v): string { return $this->fmtValue($v); }
    private function fmtValue(mixed $v): string
    {
        if ($v === null || $v === '') return '';
        if (!is_array($v)) return (string)$v;
        return implode(' | ', array_filter(array_map(function($k,$x){ return $this->fmtPart($k,$x); }, array_keys($v), array_values($v))));
    }

    private function fmtPart(string|int $key, mixed $value): string
    {
        if ($value === null || $value === '') return '';
        if (is_array($value)) $value = implode(', ', array_map(fn($x) => is_scalar($x) ? (string)$x : json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $value));
        elseif (is_object($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        return is_string($key) ? ucfirst(str_replace('_',' ',$key)).': '.$value : (string)$value;
    }

    private function fmtMemory(?array $v): string
    {
        if (!$v) return '';
        $map = ['capacity','speed','slot','manufacturer','part_number','serial_number'];
        return implode(' | ', array_filter(array_map(fn($k) => $this->fmtPart($k, $v[$k] ?? null), $map)));
    }

    private function fmtStorage(?array $v): string
    {
        if (!$v) return '';
        return $this->fmtParts($v, ['drive','used_gb','total_gb'], ['used_gb'=>'Used GB','total_gb'=>'Total GB']);
    }

    private function fmtMonitor(?array $v): string { return $v ? $this->fmtParts($v, ['manufacturer','model','serial_number']) : ''; }
    private function fmtGpu(?array $v): string { return $v ? $this->fmtParts($v, ['name','vram','driver_version']) : ''; }
    private function fmtBattery(?array $v): string { return $v ? $this->fmtParts($v, ['name','status','charge_percent']) : ''; }

    private function fmtParts(array $v, array $keys, array $labels = []): string
    {
        $out=[];
        foreach ($keys as $k) if (($v[$k] ?? null) !== null && ($v[$k] ?? '') !== '') $out[] = ($labels[$k] ?? ucfirst(str_replace('_',' ',$k))).': '.$this->scalarText($v[$k]);
        return implode(' | ', $out);
    }

    private function fmtWindows(array $v): string
    {
        if (!$v) return '';
        return $this->fmtParts($v, ['name','version','build','architecture'], ['name'=>'','version'=>'Version','build'=>'Build','architecture'=>'Architecture']);
    }

    private function fmtWindowsUpdate(mixed $auditUpdate, array $windows): string
    {
        $v = $auditUpdate ?: ($windows['windows_update'] ?? null);
        if (is_array($v)) return $this->fmtValue($v);
        return $this->scalarText($v);
    }

    private function formatUptime(mixed $v): string
    {
        if ($v === null || $v === '') return '';
        return is_numeric($v) ? number_format((float)$v, 2, '.', '') . ' hours' : (string)$v;
    }

    private function fmtNetwork(array $v): string
    {
        if (!$v) return '';
        $type = $v['type'] ?? $v['name'] ?? '';
        $parts = array_filter([
            $type,
            isset($v['name']) && $v['name'] !== $type ? $v['name'] : null,
            isset($v['description']) ? $v['description'] : null,
            isset($v['ipv4']) ? 'IP: '.$this->scalarText($v['ipv4']) : null,
            isset($v['gateway']) ? 'GW: '.$this->scalarText($v['gateway']) : null,
            isset($v['mac']) ? 'MAC: '.$this->scalarText($v['mac']) : null,
            isset($v['connection_status']) ? 'Status: '.$this->scalarText($v['connection_status']) : null,
        ]);
        return implode(' | ', $parts);
    }

    private function fmtField(array $v, string $key): string { return isset($v[$key]) ? $this->scalarText($v[$key]) : ''; }
    private function fmtAntivirus(?array $v): string { return $v ? $this->fmtParts($v,['display_name','status','executable_path','signature_version']) : ''; }
    private function fmtBitlocker(?array $v): string { return $v ? $this->fmtParts($v,['mount_point','protection_status','volume_status','encryption_percent']) : ''; }
    private function fmtFirewall(?array $v): string { return $v ? $this->fmtParts($v,['profile','enabled']) : ''; }

    private function fmtLicense(mixed $v): string
    {
        $rows=$this->listFrom($v); if (!$rows) return '';
        return implode(' || ', array_map(fn($r) => is_array($r) ? $this->fmtParts($r,['product_name','status','partial_product_key','product_type']) : (string)$r, $rows));
    }

    private function fmtSoftware(?array $v): string
    {
        if (!$v) return '';
        return $this->fmtParts($v,['name','version','publisher','install_date','estimated_size'], ['name'=>'','version'=>'Version','publisher'=>'Publisher','install_date'=>'Install','estimated_size'=>'Size']);
    }

    private function scalarText(mixed $v): string
    {
        if ($v === null || $v === '') return '';
        if (is_bool($v)) return $v ? 'True' : 'False';
        if (is_scalar($v)) return (string)$v;
        return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function columnLetter(int $number): string
    {
        $letter='';
        while ($number > 0) { $number--; $letter=chr(65 + ($number % 26)).$letter; $number=intdiv($number,26); }
        return $letter;
    }
}
