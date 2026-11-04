<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerBranch;
use App\Models\PcAudit;
use App\Models\PcAuditCode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AssetController extends Controller
{
    private function scopedAssets($user)
    {
        abort_unless($user?->isCustomerAccount() && $user->service_customer_id, 403);

        return PcAudit::query()->whereHas('auditCode.branch', function ($branchQuery) use ($user): void {
            $branchQuery->where('customer_id', $user->service_customer_id);
            if (!$user->can_access_all_branches) {
                $branchQuery->whereIn('customer_branches.id', $user->permittedBranches()->select('customer_branches.id'));
            }
        });
    }

    private function filteredAssets(Request $request, $user)
    {
        return $this->scopedAssets($user)
            ->when(trim((string)$request->query('q')) !== '', function ($q) use ($request): void {
                $term = '%' . addcslashes(trim((string)$request->query('q')), '%_') . '%';
                $q->where(function ($x) use ($term): void {
                    $x->where('computer_name', 'like', $term)
                        ->orWhere('asset_tag', 'like', $term)
                        ->orWhere('serial_number', 'like', $term)
                        ->orWhere('employee_name', 'like', $term)
                        ->orWhere('manufacturer', 'like', $term)
                        ->orWhere('model', 'like', $term);
                });
            })
            ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('auditCode.branch', fn ($b) => $b->whereKey($request->integer('branch_id'))))
            ->when($request->filled('status'), fn ($q) => $q->where('audit_status', $request->query('status')))
            ->when($request->filled('manufacturer'), fn ($q) => $q->where('manufacturer', $request->query('manufacturer')));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.view'), 403);
        $branches = CustomerBranch::query()->where('customer_id', $user->service_customer_id)
            ->where('is_active', true)
            ->where(function ($q) use ($user): void {
                if (!$user->can_access_all_branches) {
                    $q->whereIn('id', $user->permittedBranches()->select('customer_branches.id'));
                }
            })->orderBy('name')->get(['id', 'name', 'code']);

        $assets = $this->filteredAssets($request, $user)
            ->with(['auditCode.branch.customer'])
            ->latest('collected_at')->latest('id')->paginate(25)->withQueryString();

        $statuses = ['PASS', 'FAIL', 'REVIEW'];
        $manufacturers = $this->scopedAssets($user)->whereNotNull('manufacturer')->where('manufacturer', '<>', '')
            ->distinct()->orderBy('manufacturer')->pluck('manufacturer');

        return view('customer.assets.index', compact('assets', 'branches', 'statuses', 'manufacturers'));
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.export'), 403);

        $allFiltered = $request->boolean('all_filtered');
        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values();
        abort_if(!$allFiltered && $ids->isEmpty(), 422, 'Hãy chọn tài sản hoặc chọn xuất toàn bộ kết quả theo bộ lọc.');

        $query = $allFiltered
            ? $this->filteredAssets($request, $user)
            : $this->scopedAssets($user)->whereIn('id', $ids);

        $assets = $query->with([
            'auditCode.branch.customer', 'details', 'memory', 'storage', 'monitors', 'gpu',
            'batteries', 'network', 'antivirus', 'bitlocker', 'firewall', 'licenses', 'software',
        ])->orderByDesc('collected_at')->orderByDesc('id')->get();

        abort_if($assets->isEmpty(), 404, 'Không có tài sản phù hợp để xuất.');

        // Giữ nguyên cấu trúc 44 cột theo mẫu PC_Audit_Inventory_Combined.
        $headers = [
            'Khách hàng', 'Chi nhánh', 'Phòng ban', 'Họ Tên', 'Di động', 'Email', 'Username', 'Domain',
            'Tên máy tính', 'Manufacturer', 'Model', 'Serial Number', 'Asset Tag', 'Mainboard', 'BIOS', 'CPU',
            'RAM', 'HDD', 'Monitor', 'VGA', 'Battery', 'OS', 'Windows Update', 'Last Boot', 'Uptime', 'LAN',
            'WIFI', 'MODEM', 'IP', 'MAC', 'Gateway', 'DNS', 'DHCP', 'Connection Status', 'Link Speed',
            'Antivirus', 'BitLocker', 'Firewall', 'TPM', 'Secure Boot', 'Windows Activation',
            'Office Activation', 'Ngày thu thập', 'SOFTWARE',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('PC Audit');
        $sheet->fromArray($headers, null, 'A1');
        $rowNumber = 2;
        foreach ($assets as $asset) {
            $sheet->fromArray([$this->inventoryRow($asset)], null, 'A' . $rowNumber++);
        }

        $lastColumn = $this->columnLetter(count($headers));
        $lastRow = $rowNumber - 1;
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '126B6F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0D5255']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->getStyle('A2:' . $lastColumn . $lastRow)->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2E3']]],
        ]);
        $sheet->getStyle('A2:' . $lastColumn . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        $sheet->getStyle($lastColumn . '2:' . $lastColumn . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAF8F4');
        for ($row = 2; $row <= $lastRow; $row++) $sheet->getRowDimension($row)->setRowHeight(90);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:' . $lastColumn . $lastRow);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.3)->setBottom(0.3)->setLeft(0.25)->setRight(0.25);
        $widths = [32,28,24,18,18,30,20,20,22,18,20,18,16,34,25,42,58,40,40,48,28,70,38,24,18,55,55,35,40,32,45,48,25,28,24,42,40,32,28,20,70,70,22,75];
        foreach ($widths as $i => $width) $sheet->getColumnDimension($this->columnLetter($i + 1))->setWidth($width);

        $filename = 'PC_Audit_Inventory_Combined_' . now()->format('Ymd-His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]
        );
    }

    public function create(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.create'), 403);
        $branches = CustomerBranch::query()->where('customer_id', $user->service_customer_id)
            ->where('is_active', true)
            ->when(!$user->can_access_all_branches, fn ($q) => $q->whereIn('id', $user->permittedBranches()->select('customer_branches.id')))
            ->with(['pcAuditCodes' => fn ($q) => $q->orderBy('code')])->orderBy('name')->get();
        return view('customer.assets.form', ['asset' => new PcAudit(), 'branches' => $branches]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.create'), 403);
        $data = $request->validate([
            'pc_audit_code_id' => ['required', 'integer', 'exists:pc_audit_codes,id'],
            'computer_name' => ['required', 'string', 'max:255'],
            'asset_tag' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'employee_name' => ['nullable', 'string', 'max:255'],
            'employee_mobile' => ['nullable', 'string', 'max:50'],
            'employee_email' => ['nullable', 'email', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
        ]);
        $this->assertAuditCodeInScope($user, (int)$data['pc_audit_code_id']);
        $asset = PcAudit::create($data + [
            'asset_lookup_token' => bin2hex(random_bytes(24)),
            'audit_status' => 'REVIEW',
            'collected_at' => now(),
        ]);
        return redirect()->route('customer.assets.show', $asset->asset_lookup_token)->with('success', 'Đã tạo tài sản.');
    }

    public function edit(Request $request, string $token)
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.edit'), 403);
        $asset = $this->scopedAssets($user)->where('asset_lookup_token', $token)->firstOrFail();
        $branches = CustomerBranch::query()->where('customer_id', $user->service_customer_id)
            ->where('is_active', true)
            ->when(!$user->can_access_all_branches, fn ($q) => $q->whereIn('id', $user->permittedBranches()->select('customer_branches.id')))
            ->with(['pcAuditCodes' => fn ($q) => $q->orderBy('code')])->orderBy('name')->get();
        return view('customer.assets.form', compact('asset', 'branches'));
    }

    public function update(Request $request, string $token)
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.edit'), 403);
        $asset = $this->scopedAssets($user)->where('asset_lookup_token', $token)->firstOrFail();
        $data = $request->validate([
            'pc_audit_code_id' => ['required', 'integer', 'exists:pc_audit_codes,id'],
            'computer_name' => ['required', 'string', 'max:255'],
            'asset_tag' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'employee_name' => ['nullable', 'string', 'max:255'],
            'employee_mobile' => ['nullable', 'string', 'max:50'],
            'employee_email' => ['nullable', 'email', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
        ]);
        $this->assertAuditCodeInScope($user, (int)$data['pc_audit_code_id']);
        $asset->update($data);
        return redirect()->route('customer.assets.show', $asset->asset_lookup_token)->with('success', 'Đã cập nhật tài sản.');
    }

    public function destroy(Request $request, string $token)
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.delete'), 403);
        $asset = $this->scopedAssets($user)->where('asset_lookup_token', $token)->firstOrFail();
        $asset->delete();
        return redirect()->route('customer.assets.index')->with('success', 'Đã xóa tài sản.');
    }

    public function show(Request $request, string $token)
    {
        $user = $request->user();
        abort_unless($user?->isCustomerAccount() && ($user->hasPermission('customer.assets.view') || $user->hasPermission('customer.audit.view')), 403);
        $asset = $this->scopedAssets($user)->where('asset_lookup_token', $token)
            ->with(['auditCode.branch.customer', 'details', 'memory', 'storage', 'monitors', 'gpu', 'batteries', 'network', 'antivirus', 'bitlocker', 'firewall', 'licenses', 'software'])
            ->firstOrFail();
        return view('customer.assets.show', compact('asset'));
    }

    public function publicShow(string $token)
    {
        $asset = PcAudit::query()->where('asset_lookup_token', $token)
            ->whereHas('auditCode.branch.customer', fn ($q) => $q->where('is_active', true))
            ->with([
                'auditCode.branch.customer', 'details', 'memory', 'storage', 'monitors', 'gpu',
                'batteries', 'network', 'antivirus', 'bitlocker', 'firewall', 'licenses', 'software',
            ])
            ->firstOrFail();

        // QR is read-only. Do not expose raw_payload or credential material.
        return response()->view('customer.assets.public-show', compact('asset'))
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function qr(Request $request, string $token)
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.qr'), 403);
        $asset = $this->scopedAssets($user)->where('asset_lookup_token', $token)->with('auditCode.branch.customer')->firstOrFail();
        $url = route('assets.public.show', ['token' => $asset->asset_lookup_token]);
        return view('customer.assets.qr', compact('asset', 'url'));
    }

    private function assertAuditCodeInScope($user, int $codeId): void
    {
        $valid = PcAuditCode::query()->whereKey($codeId)->whereHas('branch', function ($q) use ($user): void {
            $q->where('customer_id', $user->service_customer_id);
            if (!$user->can_access_all_branches) {
                $q->whereIn('customer_branches.id', $user->permittedBranches()->select('customer_branches.id'));
            }
        })->exists();
        abort_unless($valid, 403, 'Audit Code không thuộc phạm vi tài khoản.');
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
            $audit->auditCode?->branch?->customer?->name,
            $audit->auditCode?->branch?->name,
            $audit->department,
            $audit->employee_name,
            $audit->employee_mobile,
            $audit->employee_email,
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
