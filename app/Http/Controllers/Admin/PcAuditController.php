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
        $customers = ServiceCustomer::query()->orderBy('name')->get(['id', 'code', 'name']);

        $audits = PcAudit::query()
            ->with(['auditCode.branch.customer'])
            ->when($customerId, fn($q) => $q->whereHas('auditCode.branch', fn($b) => $b->where('customer_id', $customerId)))
            ->when($search !== '', function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where(function ($x) use ($like) {
                    $x->where('computer_name', 'like', $like)
                        ->orWhere('serial_number', 'like', $like)
                        ->orWhere('employee_name', 'like', $like)
                        ->orWhere('department', 'like', $like)
                        ->orWhere('manufacturer', 'like', $like)
                        ->orWhere('model', 'like', $like)
                        ->orWhereHas('auditCode', fn($c) => $c->where('code', 'like', $like));
                });
            })
            ->latest('collected_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.pc-audit.index', compact('audits', 'search', 'customerId', 'customers'));
    }

    public function show(PcAudit $pcAudit)
    {
        $pcAudit->load([
            'auditCode.branch.customer',
            'details',
            'memory',
            'storage',
            'monitors',
            'gpu',
            'batteries',
            'network',
            'antivirus',
            'bitlocker',
            'firewall',
            'licenses',
            'software',
        ]);

        return view('admin.pc-audit.show', ['audit' => $pcAudit]);
    }

    public function export(Request $request): StreamedResponse
    {
        $ids = collect($request->input('ids', []))
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->take(200)
            ->values();

        abort_if($ids->isEmpty(), 422, 'Chưa chọn máy để xuất Excel.');

        $audits = PcAudit::with([
            'auditCode.branch.customer',
            'details',
            'memory',
            'storage',
            'monitors',
            'gpu',
            'batteries',
            'network',
            'antivirus',
            'bitlocker',
            'firewall',
            'licenses',
            'software',
        ])->whereIn('id', $ids)->get();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $headers = [
            'Họ Tên', 'Username', 'Domain', 'Tên máy tính', 'Manufacturer', 'Model', 'Serial Number', 'Asset Tag',
            'Mainboard', 'BIOS', 'CPU', 'RAM', 'HDD', 'Monitor', 'VGA', 'Battery', 'OS', 'Windows Update', 'Last Boot',
            'Uptime', 'LAN', 'WIFI', 'MODEM', 'IP', 'MAC', 'Gateway', 'DNS', 'DHCP', 'Connection Status', 'Link Speed',
            'Antivirus', 'BitLocker', 'Firewall', 'TPM', 'Secure Boot', 'Windows Activation', 'Office Activation',
            'Ngày thu thập', 'SOFTWARE',
        ];

        foreach ($audits as $audit) {
            $sheet = $spreadsheet->createSheet();
            $baseName = (string) ($audit->computer_name ?: 'PC-' . $audit->id);
            $safeName = str_replace(['\\', '/', '?', '*', '[', ']', ':'], '_', $baseName);
            $safeName = mb_substr($safeName, 0, 25) . '-' . $audit->id;
            $sheet->setTitle(mb_substr($safeName, 0, 31));

            // One PC = exactly one Excel row. Multi-value inventory is combined inside each cell.
            $row = $this->inventoryRow($audit);
            $sheet->fromArray([$headers, $row], null, 'A1');

            $lastRow = 2;
            $lastCol = count($headers);
            $lastColumn = $this->columnLetter($lastCol);

            $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '126B6F']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => [
                    'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0D5255']],
                ],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(34);

            $sheet->getStyle('A2:' . $lastColumn . '2')->applyFromArray([
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2E3']],
                ],
            ]);

            $sheet->getStyle('A2:' . $lastColumn . '2')
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setRGB('F5FAFA');

            // Highlight Software column.
            $sheet->getStyle($lastColumn . '2')
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setRGB('EAF8F4');

            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:' . $lastColumn . '2');
            $sheet->getPageSetup()->setOrientation('landscape');
            $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageMargins()->setTop(0.3)->setBottom(0.3)->setLeft(0.25)->setRight(0.25);
            $sheet->getRowDimension(2)->setRowHeight(120);

            $widths = [18, 20, 20, 18, 18, 20, 18, 16, 34, 25, 38, 45, 32, 32, 42, 22, 38, 28, 22, 18, 48, 48, 30, 32, 32, 38, 42, 25, 24, 20, 38, 34, 24, 18, 18, 38, 42, 22, 65];
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

    private function inventoryRow(PcAudit $audit): array
    {
        $raw = is_array($audit->raw_payload) ? $audit->raw_payload : [];
        $cpu = is_array($raw['cpu'] ?? null) ? $raw['cpu'] : [];
        $windows = is_array($raw['windows'] ?? null)
            ? $raw['windows']
            : (is_array($audit->operating_system) ? $audit->operating_system : []);
        $security = is_array($raw['security'] ?? null) ? $raw['security'] : [];
        $licenses = is_array($raw['licenses'] ?? null) ? $raw['licenses'] : [];

        $memory = $this->listFrom($raw['memory'] ?? $audit->memory->toArray());
        $storage = $this->listFrom($raw['storage'] ?? $audit->storage->toArray());
        $monitors = $this->listFrom($raw['monitors'] ?? $audit->monitors->toArray());
        $gpu = $this->listFrom($raw['gpu'] ?? $audit->gpu->toArray());
        $battery = $this->listFrom($raw['battery'] ?? $audit->batteries->toArray());
        $network = $this->listFrom($raw['network'] ?? $audit->network->toArray());
        $software = $this->listFrom($raw['software'] ?? $audit->software->toArray());
        $antivirus = $this->listFrom($security['antivirus'] ?? $audit->antivirus->toArray());
        $bitlocker = $this->listFrom($security['bitlocker'] ?? $audit->bitlocker->toArray());
        $firewall = $this->listFrom($security['firewall'] ?? $audit->firewall->toArray());

        return [
            $audit->employee_name,
            $audit->employee_username,
            $audit->domain,
            $audit->computer_name,
            $audit->manufacturer,
            $audit->model,
            $audit->serial_number,
            $audit->asset_tag,
            $this->fmtMainboard($audit->mainboard),
            $this->fmtBios($audit->bios),
            $this->fmtCpu($cpu),
            $this->fmtMemoryList($memory),
            $this->fmtStorageList($storage),
            $this->fmtMonitorList($monitors),
            $this->fmtGpuList($gpu),
            $this->fmtBatteryList($battery),
            $this->fmtWindows($windows),
            $this->fmtWindowsUpdate($audit->windows_update, $windows),
            $audit->last_boot,
            $this->formatUptime($audit->uptime),
            $this->fmtNetworkList($network, ['ethernet', 'lan']),
            $this->fmtNetworkList($network, ['wifi', 'wi-fi', 'wireless']),
            $this->fmtNetworkList($network, ['modem']),
            $this->fmtNetworkFieldList($network, 'ipv4'),
            $this->fmtNetworkFieldList($network, 'mac'),
            $this->fmtNetworkFieldList($network, 'gateway'),
            $this->fmtNetworkFieldList($network, 'dns'),
            $this->fmtNetworkFieldList($network, 'dhcp'),
            $this->fmtNetworkFieldList($network, 'connection_status'),
            $this->fmtNetworkFieldList($network, 'link_speed'),
            $this->fmtList($antivirus, fn($v) => $this->fmtAntivirus($v)),
            $this->fmtList($bitlocker, fn($v) => $this->fmtBitlocker($v)),
            $this->fmtList($firewall, fn($v) => $this->fmtFirewall($v)),
            $this->fmtValue($security['tpm'] ?? $audit->tpm),
            $audit->secure_boot === null ? '' : ($audit->secure_boot ? 'True' : 'False'),
            $this->fmtLicense($licenses['windows'] ?? $audit->licenses->where('product_type', 'WINDOWS')->toArray()),
            $this->fmtLicense($licenses['office'] ?? $audit->licenses->where('product_type', 'OFFICE')->toArray()),
            optional($audit->collected_at)->format('Y-m-d H:i:s'),
            $this->fmtList($software, fn($v) => $this->fmtSoftware($v)),
        ];
    }

    private function listFrom(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            return [];
        }

        return array_is_list($value) ? $value : [$value];
    }

    private function fmtList(array $rows, callable $formatter): string
    {
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $row = ['value' => $row];
            }
            $text = trim((string) $formatter($row));
            if ($text !== '' && !in_array($text, $items, true)) {
                $items[] = $text;
            }
        }

        return implode("\n", $items);
    }

    private function fmtNetworkList(array $rows, array $terms): string
    {
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $text = strtolower((string) ($row['type'] ?? '') . ' ' . ($row['name'] ?? '') . ' ' . ($row['description'] ?? ''));
            $matched = false;
            foreach ($terms as $term) {
                if (str_contains($text, strtolower($term))) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }

            $formatted = trim($this->fmtNetwork($row));
            if ($formatted !== '' && !in_array($formatted, $items, true)) {
                $items[] = $formatted;
            }
        }

        return implode("\n", $items);
    }

    private function fmtNetworkFieldList(array $rows, string $key): string
    {
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                continue;
            }
            $text = trim($this->scalarText($row[$key]));
            if ($text !== '' && !in_array($text, $items, true)) {
                $items[] = $text;
            }
        }

        return implode("\n", $items);
    }

    private function fmtMainboard(mixed $v): string
    {
        return $this->fmtValue($v);
    }

    private function fmtBios(mixed $v): string
    {
        return $this->fmtValue($v);
    }

    private function fmtCpu(array $v): string
    {
        return $this->fmtValue($v);
    }

    private function fmtValue(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        if (!is_array($v)) {
            return (string) $v;
        }

        return implode(' | ', array_filter(array_map(
            fn($k, $x) => $this->fmtPart($k, $x),
            array_keys($v),
            array_values($v)
        )));
    }

    private function fmtPart(string|int $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_array($value)) {
            $value = implode(', ', array_map(
                fn($x) => is_scalar($x) ? (string) $x : (json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
                $value
            ));
        } elseif (is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return is_string($key)
            ? ucfirst(str_replace('_', ' ', $key)) . ': ' . $value
            : (string) $value;
    }

    private function fmtMemoryList(array $rows): string
    {
        return $this->fmtList($rows, function ($v) {
            $map = ['capacity', 'speed', 'slot', 'manufacturer', 'part_number', 'serial_number'];
            return implode(' | ', array_filter(array_map(fn($k) => $this->fmtPart($k, $v[$k] ?? null), $map)));
        });
    }

    private function fmtStorageList(array $rows): string
    {
        return $this->fmtList($rows, fn($v) => $this->fmtStorage($v));
    }

    private function fmtMonitorList(array $rows): string
    {
        return $this->fmtList($rows, fn($v) => $this->fmtMonitor($v));
    }

    private function fmtGpuList(array $rows): string
    {
        return $this->fmtList($rows, fn($v) => $this->fmtGpu($v));
    }

    private function fmtBatteryList(array $rows): string
    {
        return $this->fmtList($rows, fn($v) => $this->fmtBattery($v));
    }

    private function fmtStorage(array $v): string
    {
        return $this->fmtParts($v, ['drive', 'used_gb', 'total_gb'], ['used_gb' => 'Used GB', 'total_gb' => 'Total GB']);
    }

    private function fmtMonitor(array $v): string
    {
        return $this->fmtParts($v, ['manufacturer', 'model', 'serial_number']);
    }

    private function fmtGpu(array $v): string
    {
        return $this->fmtParts($v, ['name', 'vram', 'driver_version']);
    }

    private function fmtBattery(array $v): string
    {
        return $this->fmtParts($v, ['name', 'status', 'charge_percent']);
    }

    private function fmtParts(array $v, array $keys, array $labels = []): string
    {
        $out = [];
        foreach ($keys as $k) {
            if (($v[$k] ?? null) !== null && ($v[$k] ?? '') !== '') {
                $label = $labels[$k] ?? ucfirst(str_replace('_', ' ', $k));
                $value = $this->scalarText($v[$k]);
                $out[] = $label === '' ? $value : $label . ': ' . $value;
            }
        }

        return implode(' | ', $out);
    }

    private function fmtWindows(array $v): string
    {
        if (!$v) {
            return '';
        }

        return $this->fmtParts($v, ['name', 'version', 'build', 'architecture'], [
            'name' => '',
            'version' => 'Version',
            'build' => 'Build',
            'architecture' => 'Architecture',
        ]);
    }

    private function fmtWindowsUpdate(mixed $auditUpdate, array $windows): string
    {
        $v = $auditUpdate ?: ($windows['windows_update'] ?? null);
        if (is_array($v)) {
            return $this->fmtValue($v);
        }

        return $this->scalarText($v);
    }

    private function formatUptime(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }

        return is_numeric($v)
            ? number_format((float) $v, 2, '.', '') . ' hours'
            : (string) $v;
    }

    private function fmtNetwork(array $v): string
    {
        if (!$v) {
            return '';
        }

        $type = $v['type'] ?? $v['name'] ?? '';
        $parts = array_filter([
            $type,
            isset($v['name']) && $v['name'] !== $type ? $v['name'] : null,
            isset($v['description']) ? $v['description'] : null,
            isset($v['ipv4']) ? 'IP: ' . $this->scalarText($v['ipv4']) : null,
            isset($v['gateway']) ? 'GW: ' . $this->scalarText($v['gateway']) : null,
            isset($v['mac']) ? 'MAC: ' . $this->scalarText($v['mac']) : null,
            isset($v['connection_status']) ? 'Status: ' . $this->scalarText($v['connection_status']) : null,
        ]);

        return implode(' | ', $parts);
    }

    private function fmtAntivirus(array $v): string
    {
        return $this->fmtParts($v, ['display_name', 'status', 'executable_path', 'signature_version']);
    }

    private function fmtBitlocker(array $v): string
    {
        return $this->fmtParts($v, ['mount_point', 'protection_status', 'volume_status', 'encryption_percent']);
    }

    private function fmtFirewall(array $v): string
    {
        return $this->fmtParts($v, ['profile', 'enabled']);
    }

    private function fmtLicense(mixed $v): string
    {
        $rows = $this->listFrom($v);
        if (!$rows) {
            return '';
        }

        $items = [];
        foreach ($rows as $row) {
            $text = is_array($row)
                ? $this->fmtParts($row, ['product_name', 'status', 'partial_product_key', 'product_type'])
                : (string) $row;
            if ($text !== '' && !in_array($text, $items, true)) {
                $items[] = $text;
            }
        }

        return implode("\n", $items);
    }

    private function fmtSoftware(array $v): string
    {
        return $this->fmtParts($v, ['name', 'version', 'publisher', 'install_date', 'estimated_size'], [
            'name' => '',
            'version' => 'Version',
            'publisher' => 'Publisher',
            'install_date' => 'Install',
            'estimated_size' => 'Size',
        ]);
    }

    private function scalarText(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 'True' : 'False';
        }
        if (is_scalar($v)) {
            return (string) $v;
        }

        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function columnLetter(int $number): string
    {
        $letter = '';
        while ($number > 0) {
            $number--;
            $letter = chr(65 + ($number % 26)) . $letter;
            $number = intdiv($number, 26);
        }

        return $letter;
    }
}
