<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerBranch;
use App\Models\PcAudit;
use App\Models\PcAuditCode;
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
        $branchId = $request->query('branch_id');
        $customers = ServiceCustomer::query()->orderBy('name')->get(['id', 'code', 'name']);
        // Load branch options for the dependent customer/branch dropdown.
        // The browser filters these options whenever the selected customer changes.
        $branches = CustomerBranch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'customer_id', 'name']);

        $audits = $this->filteredAuditsQuery($request)
            ->with(['auditCode.branch.customer'])
            ->latest('collected_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.pc-audit.index', compact('audits', 'search', 'customerId', 'branchId', 'customers', 'branches'));
    }

    private function filteredAuditsQuery(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $customerId = $request->input('customer_id');
        $branchId = $request->input('branch_id');

        return PcAudit::query()
            ->when($customerId, fn ($q) => $q->whereHas('auditCode.branch', fn ($b) => $b->where('customer_id', $customerId)))
            ->when($branchId, fn ($q) => $q->whereHas('auditCode', fn ($c) => $c->where('branch_id', $branchId)))
            ->when($search !== '', function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where(function ($x) use ($like) {
                    $x->where('computer_name', 'like', $like)
                        ->orWhere('serial_number', 'like', $like)
                        ->orWhere('employee_name', 'like', $like)
                        ->orWhere('department', 'like', $like)
                        ->orWhere('manufacturer', 'like', $like)
                        ->orWhere('model', 'like', $like)
                        ->orWhereHas('auditCode', fn ($c) => $c->where('code', 'like', $like));
                });
            });
    }

    public function show(PcAudit $pcAudit)
    {
        $pcAudit->load([
            'auditCode.branch.customer', 'details', 'memory', 'storage', 'monitors', 'gpu',
            'batteries', 'network', 'antivirus', 'bitlocker', 'firewall', 'licenses', 'software',
        ]);

        return view('admin.pc-audit.show', ['audit' => $pcAudit]);
    }

    public function edit(PcAudit $pcAudit)
    {
        $pcAudit->load('auditCode.branch.customer');

        $customers = ServiceCustomer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $branches = CustomerBranch::query()
            ->with('customer:id,code,name')
            ->where('is_active', true)
            ->whereHas('customer', fn ($q) => $q->where('is_active', true))
            ->orderBy('customer_id')
            ->orderBy('name')
            ->get(['id', 'customer_id', 'code', 'name']);

        return view('admin.pc-audit.edit', compact('pcAudit', 'customers', 'branches'));
    }

    public function update(Request $request, PcAudit $pcAudit)
    {
        $data = $request->validate([
            'employee_name' => ['nullable', 'string', 'max:150'],
            'customer_id' => ['required', 'integer', 'exists:service_customers,id'],
            'branch_id' => ['required', 'integer', 'exists:customer_branches,id'],
        ]);

        $customer = ServiceCustomer::query()
            ->whereKey($data['customer_id'])
            ->where('is_active', true)
            ->first();

        if (!$customer) {
            return back()->withInput()->withErrors([
                'customer_id' => 'Customer không tồn tại hoặc đang không hoạt động.',
            ]);
        }

        $branch = CustomerBranch::query()
            ->whereKey($data['branch_id'])
            ->where('customer_id', $customer->id)
            ->where('is_active', true)
            ->whereHas('customer', fn ($q) => $q->where('is_active', true))
            ->first();

        if (!$branch) {
            return back()->withInput()->withErrors([
                'branch_id' => 'Chi nhánh không thuộc Customer đã chọn hoặc đang không hoạt động.',
            ]);
        }

        $auditCode = PcAuditCode::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (!$auditCode) {
            return back()->withInput()->withErrors([
                'branch_id' => 'Chi nhánh này chưa có Audit Code đang hoạt động. Vui lòng tạo Audit Code trước.',
            ]);
        }

        $pcAudit->update([
            'employee_name' => trim((string) ($data['employee_name'] ?? '')) ?: null,
            'pc_audit_code_id' => $auditCode->id,
        ]);

        return redirect()
            ->route('admin.pc_audit.show', $pcAudit)
            ->with('success', 'Đã cập nhật Họ tên, Customer và Chi nhánh cho PC Audit.');
    }

    public function export(Request $request): StreamedResponse
    {
        $allFiltered = $request->boolean('all_filtered');
        $ids = collect($request->input('ids', []))
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()->take(200)->values();

        abort_if(!$allFiltered && $ids->isEmpty(), 422, 'Chọn máy hoặc bật Xuất toàn bộ kết quả theo bộ lọc.');

        $auditsQuery = $allFiltered
            ? $this->filteredAuditsQuery($request)
            : PcAudit::query()->whereIn('id', $ids);

        $audits = $auditsQuery->with([
            'auditCode.branch.customer', 'details', 'memory', 'storage', 'monitors', 'gpu',
            'batteries', 'network', 'antivirus', 'bitlocker', 'firewall', 'licenses', 'software',
        ])->get();

        abort_if($audits->isEmpty(), 404, 'Không tìm thấy dữ liệu PC Audit phù hợp với bộ lọc.');

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $headers = [
            'Họ Tên', 'Username', 'Domain', 'Tên máy tính', 'Manufacturer', 'Model', 'Serial Number', 'Asset Tag',
            'Mainboard', 'BIOS', 'CPU', 'RAM', 'HDD', 'Monitor', 'VGA', 'Battery', 'OS', 'Windows Update',
            'Last Boot', 'Uptime', 'LAN', 'WIFI', 'MODEM', 'IP', 'MAC', 'Gateway', 'DNS', 'DHCP',
            'Connection Status', 'Link Speed', 'Antivirus', 'BitLocker', 'Firewall', 'TPM', 'Secure Boot',
            'Windows Activation', 'Office Activation', 'Ngày thu thập', 'SOFTWARE',
        ];

        foreach ($audits as $audit) {
            $sheet = $spreadsheet->createSheet();
            $baseName = (string) ($audit->computer_name ?: 'PC-' . $audit->id);
            $safeName = str_replace(['\\', '/', '?', '*', '[', ']', ':'], '_', $baseName);
            $safeName = mb_substr($safeName, 0, 25) . '-' . $audit->id;
            $sheet->setTitle(mb_substr($safeName, 0, 31));

            $row = $this->inventoryRow($audit);
            $sheet->fromArray([$headers, $row], null, 'A1');

            $lastCol = count($headers);
            $lastColumn = $this->columnLetter($lastCol);

            $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '126B6F']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0D5255']]],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(36);

            $sheet->getStyle('A2:' . $lastColumn . '2')->applyFromArray([
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2E3']]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5FAFA']],
            ]);

            $sheet->getStyle($lastColumn . '2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAF8F4');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:' . $lastColumn . '2');
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageMargins()->setTop(0.3)->setBottom(0.3)->setLeft(0.25)->setRight(0.25);
            $sheet->getRowDimension(2)->setRowHeight(180);

            $widths = [18,20,20,18,18,20,18,16,34,25,42,58,40,40,48,28,70,38,24,18,55,55,35,40,32,45,48,25,28,24,42,40,32,28,20,70,70,22,75];
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

    public function exportSingleSheet(Request $request): StreamedResponse
    {
        $allFiltered = $request->boolean('all_filtered');
        $ids = collect($request->input('ids', []))
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()->take(200)->values();

        abort_if(!$allFiltered && $ids->isEmpty(), 422, 'Chọn máy hoặc bật Xuất toàn bộ kết quả theo bộ lọc.');

        $auditsQuery = $allFiltered
            ? $this->filteredAuditsQuery($request)
            : PcAudit::query()->whereIn('id', $ids);

        $audits = $auditsQuery->with([
            'auditCode.branch.customer', 'details', 'memory', 'storage', 'monitors', 'gpu',
            'batteries', 'network', 'antivirus', 'bitlocker', 'firewall', 'licenses', 'software',
        ])->orderByDesc('collected_at')->get();

        abort_if($audits->isEmpty(), 404, 'Không tìm thấy dữ liệu PC Audit phù hợp với bộ lọc.');

        abort_if($audits->isEmpty(), 404, 'Không tìm thấy dữ liệu PC Audit đã chọn.');

        $headers = [
            'Họ Tên', 'Username', 'Domain', 'Tên máy tính', 'Manufacturer', 'Model', 'Serial Number', 'Asset Tag',
            'Mainboard', 'BIOS', 'CPU', 'RAM', 'HDD', 'Monitor', 'VGA', 'Battery', 'OS', 'Windows Update',
            'Last Boot', 'Uptime', 'LAN', 'WIFI', 'MODEM', 'IP', 'MAC', 'Gateway', 'DNS', 'DHCP',
            'Connection Status', 'Link Speed', 'Antivirus', 'BitLocker', 'Firewall', 'TPM', 'Secure Boot',
            'Windows Activation', 'Office Activation', 'Ngày thu thập', 'SOFTWARE',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('PC Audit');
        $sheet->fromArray($headers, null, 'A1');

        $rowNumber = 2;
        foreach ($audits as $audit) {
            $sheet->fromArray($this->inventoryRow($audit), null, 'A' . $rowNumber);
            $rowNumber++;
        }

        $lastColumn = $this->columnLetter(count($headers));
        $lastRow = $rowNumber - 1;

        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '126B6F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0D5255']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(36);

        if ($lastRow >= 2) {
            $sheet->getStyle('A2:' . $lastColumn . $lastRow)->applyFromArray([
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2E3']]],
            ]);
            $sheet->getStyle('A2:' . $lastColumn . $lastRow)
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
            $sheet->getStyle($lastColumn . '2:' . $lastColumn . $lastRow)
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAF8F4');

            for ($row = 2; $row <= $lastRow; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(90);
            }
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:' . $lastColumn . $lastRow);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.3)->setBottom(0.3)->setLeft(0.25)->setRight(0.25);

        $widths = [18,20,20,22,18,20,18,16,34,25,42,58,40,40,48,28,70,38,24,18,55,55,35,40,32,45,48,25,28,24,42,40,32,28,20,70,70,22,75];
        foreach ($widths as $i => $width) {
            $sheet->getColumnDimension($this->columnLetter($i + 1))->setWidth($width);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            fn() => $writer->save('php://output'),
            'PC_Audit_Inventory_Combined_' . now()->format('Ymd-His') . '.xlsx',
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
        $cpu = $this->listFrom($raw['cpu'] ?? []);
        $windows = is_array($raw['windows'] ?? null) ? $raw['windows'] : (is_array($audit->operating_system) ? $audit->operating_system : []);
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

        $windowsLicenses = $this->listFrom($licenses['windows'] ?? $audit->licenses->where('product_type', 'WINDOWS')->toArray());
        $officeLicenses = $this->listFrom($licenses['office'] ?? $audit->licenses->where('product_type', 'OFFICE')->toArray());

        return [
            $audit->employee_name,
            $audit->employee_username,
            $audit->domain,
            $audit->computer_name,
            $audit->manufacturer,
            $audit->model,
            $audit->serial_number,
            $audit->asset_tag,
            $this->fmtValue($audit->mainboard),
            $this->fmtValue($audit->bios),
            $this->fmtList($cpu, fn($v) => $this->fmtCpu($v)),
            $this->fmtMemoryList($memory),
            $this->fmtStorageList($storage),
            $this->fmtMonitorList($monitors),
            $this->fmtGpuList($gpu),
            $this->fmtBatteryList($battery),
            $this->fmtWindows($windows),
            $this->fmtValue($audit->windows_update),
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
            $this->fmtList($antivirus, fn($v) => $this->fmtValue($v)),
            $this->fmtList($bitlocker, fn($v) => $this->fmtValue($v)),
            $this->fmtList($firewall, fn($v) => $this->fmtValue($v)),
            $this->fmtValue($security['tpm'] ?? $audit->tpm),
            $audit->secure_boot === null ? '' : ($audit->secure_boot ? 'True' : 'False'),
            $this->fmtLicenseList($windowsLicenses),
            $this->fmtLicenseList($officeLicenses),
            optional($audit->collected_at)->format('Y-m-d H:i:s'),
            $this->fmtSoftwareList($software),
        ];
    }

    private function listFrom(mixed $value): array
    {
        if (!is_array($value) || $value === []) return [];
        return array_is_list($value) ? $value : [$value];
    }

    private function fmtList(array $rows, callable $formatter): string
    {
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) $row = ['value' => $row];
            $text = trim((string) $formatter($row));
            if ($text !== '' && !in_array($text, $items, true)) $items[] = $text;
        }
        return implode("\n", $items);
    }

    private function fmtMemoryList(array $rows): string
    {
        return $this->fmtList($rows, function ($v) {
            $parts = [];
            $fields = [
                'capacity_gb' => 'Capacity', 'capacity' => 'Capacity', 'speed' => 'Speed',
                'configured_clock_speed' => 'Configured Speed', 'slot' => 'Slot', 'bank_label' => 'Bank',
                'manufacturer' => 'Manufacturer', 'part_number' => 'Part Number', 'serial_number' => 'Serial',
                'form_factor' => 'Form Factor', 'memory_type' => 'Memory Type', 'data_width' => 'Data Width',
                'total_width' => 'Total Width',
            ];
            foreach ($fields as $key => $label) {
                if (array_key_exists($key, $v) && $v[$key] !== null && $v[$key] !== '') {
                    $value = $v[$key];
                    if ($key === 'capacity_gb') $value .= ' GB';
                    if ($key === 'speed' || $key === 'configured_clock_speed') $value .= ' MHz';
                    if ($key === 'data_width' || $key === 'total_width') $value .= ' bit';
                    $parts[] = $label . ': ' . $value;
                }
            }
            return implode(' | ', $parts);
        });
    }

    private function fmtStorageList(array $rows): string
    {
        return $this->fmtList($rows, function ($v) {
            $parts = [];
            foreach (['drive'=>'Drive','volume_name'=>'Volume','used_gb'=>'Used GB','free_gb'=>'Free GB','total_gb'=>'Total GB','filesystem'=>'File System'] as $key=>$label) {
                if (array_key_exists($key,$v) && $v[$key] !== null && $v[$key] !== '') $parts[] = $label . ': ' . $v[$key];
            }
            return implode(' | ', $parts);
        });
    }

    private function fmtMonitorList(array $rows): string
    {
        return $this->fmtList($rows, fn($v) => $this->fmtParts($v, ['manufacturer','model','serial_number']));
    }

    private function fmtGpuList(array $rows): string
    {
        return $this->fmtList($rows, fn($v) => $this->fmtParts($v, ['name','vram_gb','vram','driver_version']));
    }

    private function fmtBatteryList(array $rows): string
    {
        return $this->fmtList($rows, fn($v) => $this->fmtParts($v, ['name','status','charge_percent']));
    }

    private function fmtCpu(array $v): string
    {
        return $this->fmtParts($v, ['name','cores','threads','max_clock']);
    }

    private function fmtWindows(array $v): string
    {
        $labels = [
            'caption'=>'Edition', 'version'=>'Version', 'build'=>'Build', 'architecture'=>'Architecture',
            'install_date'=>'Install Date', 'registered_user'=>'Registered User', 'organization'=>'Organization',
            'serial_number'=>'OS Serial', 'product_type'=>'Product Type', 'sku'=>'SKU', 'language'=>'Language',
            'csd_version'=>'Service Pack', 'windows_directory'=>'Windows Dir', 'system_directory'=>'System Dir',
            'system_drive'=>'System Drive', 'manufacturer'=>'Manufacturer', 'status'=>'Status',
            'total_visible_memory_gb'=>'Visible RAM GB', 'last_boot'=>'Last Boot', 'uptime_hours'=>'Uptime Hours',
        ];
        return $this->fmtParts($v, array_keys($labels), $labels);
    }

    private function fmtLicenseList(array $rows): string
    {
        return $this->fmtList($rows, function ($v) {
            $labels = [
                'product_name'=>'Product', 'status_text'=>'Status', 'status'=>'Status Code',
                'partial_product_key'=>'Partial Key', 'description'=>'Description', 'license_family'=>'License Family',
                'application_id'=>'Application ID', 'product_key_channel'=>'Product Key Channel',
                'grace_period_remaining'=>'Grace Period',
            ];
            return $this->fmtParts($v, array_keys($labels), $labels);
        });
    }

    private function fmtSoftwareList(array $rows): string
    {
        return $this->fmtList($rows, function ($v) {
            $name = trim((string)($v['name'] ?? ''));
            if ($name === '') return '';
            $parts = [$name];
            if (!empty($v['version'])) $parts[] = 'Version: ' . $v['version'];
            if (!empty($v['publisher'])) $parts[] = 'Publisher: ' . $v['publisher'];
            if (!empty($v['install_date'])) $parts[] = 'Install: ' . $v['install_date'];
            return implode(' | ', $parts);
        });
    }

    private function fmtNetworkList(array $rows, array $terms): string
    {
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $haystack = strtolower((string)($row['type'] ?? '') . ' ' . ($row['name'] ?? '') . ' ' . ($row['description'] ?? ''));
            foreach ($terms as $term) {
                if (str_contains($haystack, strtolower($term))) {
                    $text = $this->fmtNetwork($row);
                    if ($text !== '' && !in_array($text, $items, true)) $items[] = $text;
                    break;
                }
            }
        }
        return implode("\n", $items);
    }

    private function fmtNetwork(array $v): string
    {
        return $this->fmtParts($v, ['type','name','description','ipv4','mac','gateway','dns','dhcp','connection_status','link_speed']);
    }

    private function fmtNetworkFieldList(array $rows, string $key): string
    {
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) continue;
            $text = $this->scalarText($row[$key]);
            if ($text !== '' && !in_array($text, $items, true)) $items[] = $text;
        }
        return implode("\n", $items);
    }

    private function fmtParts(array $v, array $keys, array $labels = []): string
    {
        $parts = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $v) || $v[$key] === null || $v[$key] === '') continue;
            $value = $this->scalarText($v[$key]);
            if ($value === '') continue;
            $parts[] = ($labels[$key] ?? ucfirst(str_replace('_',' ',$key))) . ': ' . $value;
        }
        return implode(' | ', $parts);
    }

    private function fmtValue(mixed $v): string
    {
        if ($v === null || $v === '') return '';
        if (!is_array($v)) return (string)$v;
        $parts = [];
        foreach ($v as $key => $value) {
            if ($value === null || $value === '') continue;
            $text = $this->scalarText($value);
            if ($text !== '') $parts[] = ucfirst(str_replace('_',' ',(string)$key)) . ': ' . $text;
        }
        return implode(' | ', $parts);
    }

    private function scalarText(mixed $value): string
    {
        if ($value === null || $value === '') return '';
        if (is_bool($value)) return $value ? 'True' : 'False';
        if (is_array($value)) return implode(', ', array_map(fn($v) => $this->scalarText($v), $value));
        if (is_object($value)) return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return (string)$value;
    }

    private function formatUptime(mixed $value): string
    {
        if ($value === null || $value === '') return '';
        return is_numeric($value) ? round((float)$value, 2) . ' hours' : (string)$value;
    }

    private function columnLetter(int $column): string
    {
        $letter = '';
        while ($column > 0) {
            $mod = ($column - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $column = intdiv($column - $mod, 26);
        }
        return $letter;
    }
}
