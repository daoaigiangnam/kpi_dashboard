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

    public function export(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('customer.assets.export'), 403);
        $allFiltered = $request->boolean('all_filtered');
        $ids = collect($request->input('ids', []))->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->take(5000)->values();
        abort_if(!$allFiltered && $ids->isEmpty(), 422, 'Hãy chọn tài sản hoặc chọn xuất toàn bộ kết quả theo bộ lọc.');

        $query = $allFiltered ? $this->filteredAssets($request, $user) : $this->scopedAssets($user)->whereIn('id', $ids);
        $assets = $query->with(['auditCode.branch.customer','details','memory','storage','monitors','gpu','batteries','network','antivirus','bitlocker','firewall','licenses','software'])->orderBy('id')->get();
        abort_if($assets->isEmpty(), 404, 'Không có tài sản phù hợp để xuất.');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Danh sách máy');
        $headers = ['STT','Khách hàng','Chi nhánh','Phòng ban','Họ tên','Di động','Email','Username','Domain','Tên máy tính','Hãng','Model','Serial Number','Asset Tag','Audit Code','Trạng thái Audit','Điểm Audit','Ngày kiểm kê','CPU','RAM','Ổ đĩa','Hệ điều hành','IP','MAC','Antivirus','BitLocker','TPM','Secure Boot','Số phần mềm'];
        $sheet->fromArray($headers, null, 'A1');
        $detailSheet = $spreadsheet->createSheet();
        $detailSheet->setTitle('Chi tiết cấu hình');
        $detailSheet->fromArray(['Tên máy tính','Nhóm thông tin','Trường','Giá trị'], null, 'A1');

        $display = static function ($value): string {
            if (is_array($value) || is_object($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '';
            if (is_bool($value)) return $value ? 'Có' : 'Không';
            return $value === null ? '' : (string) $value;
        };
        $flatten = static function ($value, string $prefix = '') use (&$flatten, $display): array {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $value = $decoded;
            }
            if (!is_array($value)) return $value === null || $value === '' ? [] : [[$prefix ?: 'Giá trị', $display($value)]];
            $rows = [];
            foreach ($value as $key => $item) {
                if (in_array((string) $key, ['raw_payload','password','token','secret','credential'], true)) continue;
                $name = $prefix === '' ? (string) $key : $prefix.' / '.(string) $key;
                if (is_array($item)) {
                    foreach ($flatten($item, $name) as $row) $rows[] = $row;
                } elseif ($item !== null && $item !== '') {
                    $rows[] = [$name, $display($item)];
                }
            }
            return $rows;
        };
        $row = 2;
        $detailRow = 2;
        foreach ($assets as $index => $asset) {
            $net = $asset->network;
            $networkValue = fn ($field) => $net->pluck($field)->filter(fn ($v) => $v !== null && $v !== '')->unique()->implode("\n");
            $sheet->fromArray([[
                $index + 1, $asset->auditCode?->branch?->customer?->name, $asset->auditCode?->branch?->name,
                $asset->department, $asset->employee_name, $asset->employee_mobile, $asset->employee_email,
                $asset->employee_username, $asset->domain, $asset->computer_name, $asset->manufacturer, $asset->model,
                $asset->serial_number, $asset->asset_tag, $asset->auditCode?->code, $asset->audit_status,
                $asset->audit_score, $asset->collected_at?->format('d/m/Y H:i:s'),
                $display($asset->details?->cpu),
                $net->isNotEmpty() ? $asset->memory->map(fn ($m) => ($m->capacity ?? '').' '.$m->unit.' / '.($m->speed ?? '').' MHz')->implode("\n") : $asset->memory->map(fn ($m) => $display($m->getAttributes()))->implode("\n"),
                $asset->storage->map(fn ($s) => ($s->drive ?? '').' '.($s->used_gb ?? '').' / '.($s->total_gb ?? '').' GB')->implode("\n"),
                $display($asset->operating_system), $networkValue('ipv4'), $networkValue('mac'),
                $asset->antivirus->map(fn ($v) => $v->display_name ?? $display($v->getAttributes()))->implode("\n"),
                $asset->bitlocker->map(fn ($v) => ($v->mount_point ?? '').': '.($v->protection_status ?? '').' / '.($v->encryption_percent ?? '').'%')->implode("\n"),
                $display($asset->tpm), $display($asset->secure_boot), $asset->software->count(),
            ]], null, 'A'.$row);
            $detailGroups = [
                'Thông tin người sử dụng' => ['Phòng ban'=>$asset->department,'Họ tên'=>$asset->employee_name,'Di động'=>$asset->employee_mobile,'Email'=>$asset->employee_email,'Username'=>$asset->employee_username,'Domain'=>$asset->domain],
                'Thông tin máy' => ['Computer Name'=>$asset->computer_name,'Manufacturer'=>$asset->manufacturer,'Model'=>$asset->model,'Serial Number'=>$asset->serial_number,'Asset Tag'=>$asset->asset_tag,'Audit Code'=>$asset->auditCode?->code,'Ngày kiểm kê'=>$asset->collected_at?->format('d/m/Y H:i:s')],
                'Thông tin hệ thống' => ['Mainboard'=>$asset->mainboard,'BIOS'=>$asset->bios,'Operating System'=>$asset->operating_system,'Windows Update'=>$asset->windows_update,'Last Boot'=>$asset->last_boot,'Uptime'=>$asset->uptime,'TPM'=>$asset->tpm,'Secure Boot'=>$asset->secure_boot],
                'Kết quả Audit' => ['Trạng thái'=>$asset->audit_status,'Điểm'=>$asset->audit_score,'Kết quả'=>$asset->audit_results],
                'RAM' => ['RAM'=>$asset->memory->map(fn ($v) => $v->getAttributes())->all()],
                'Storage' => ['Storage'=>$asset->storage->map(fn ($v) => $v->getAttributes())->all()],
                'Monitors' => ['Monitors'=>$asset->monitors->map(fn ($v) => $v->getAttributes())->all()],
                'GPU' => ['GPU'=>$asset->gpu->map(fn ($v) => $v->getAttributes())->all()],
                'Battery' => ['Battery'=>$asset->batteries->map(fn ($v) => $v->getAttributes())->all()],
                'Network' => ['Network'=>$asset->network->map(fn ($v) => $v->getAttributes())->all()],
                'Antivirus' => ['Antivirus'=>$asset->antivirus->map(fn ($v) => $v->getAttributes())->all()],
                'BitLocker' => ['BitLocker'=>$asset->bitlocker->map(fn ($v) => $v->getAttributes())->all()],
                'Firewall' => ['Firewall'=>$asset->firewall->map(fn ($v) => $v->getAttributes())->all()],
                'Licenses' => ['Licenses'=>$asset->licenses->map(fn ($v) => $v->getAttributes())->all()],
                'Software' => ['Software'=>$asset->software->map(fn ($v) => $v->getAttributes())->all()],
                'Chi tiết Audit bổ sung' => $asset->details ? $asset->details->only(['hardware','cpu','memory','storage','monitors','gpu','battery','windows','network','security','licenses','software','other']) : [],
            ];
            foreach ($detailGroups as $group => $fields) {
                foreach ($fields as $label => $value) {
                    $flat = $flatten($value, $label);
                    if (!$flat) $flat = [[$label, '']];
                    foreach ($flat as [$field, $content]) {
                        $detailSheet->fromArray([[$asset->computer_name, $group, $field, $content]], null, 'A'.$detailRow++);
                    }
                }
            }
            $row++;
        }
        foreach ([$sheet, $detailSheet] as $ws) {
            $lastCol = $ws->getHighestDataColumn();
            $lastRow = $ws->getHighestDataRow();
            $ws->getStyle('A1:'.$lastCol.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '145A45']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
            $ws->getRowDimension(1)->setRowHeight(32);
            $ws->freezePane('A2');
            $ws->setAutoFilter('A1:'.$lastCol.$lastRow);
            $ws->getStyle('A2:'.$lastCol.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
            $ws->getStyle('A1:'.$lastCol.$lastRow)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR);
            foreach (range('A', $lastCol) as $column) {
                $ws->getColumnDimension($column)->setWidth($ws === $detailSheet ? (['A'=>26,'B'=>24,'C'=>34,'D'=>58][$column] ?? 22) : (['A'=>7,'B'=>28,'C'=>22,'D'=>18,'E'=>25,'F'=>17,'G'=>30,'H'=>20,'I'=>18,'J'=>23,'K'=>18,'L'=>18,'M'=>22,'N'=>18,'O'=>16,'P'=>16,'Q'=>13,'R'=>20,'S'=>28,'T'=>24,'U'=>24,'V'=>36,'W'=>22,'X'=>22,'Y'=>28,'Z'=>25,'AA'=>25,'AB'=>18,'AC'=>14][$column] ?? 20));
            }
            if ($ws === $sheet) {
                foreach (['A'=>7,'B'=>28,'C'=>22,'D'=>18,'E'=>25,'F'=>17,'G'=>30,'H'=>20,'I'=>18,'J'=>23,'K'=>18,'L'=>18,'M'=>22,'N'=>18,'O'=>16,'P'=>16,'Q'=>13,'R'=>20,'S'=>28,'T'=>24,'U'=>24,'V'=>36,'W'=>22,'X'=>22,'Y'=>28,'Z'=>25,'AA'=>25,'AB'=>18,'AC'=>14] as $column => $width) $ws->getColumnDimension($column)->setWidth($width);
            }
        }
        $filename = 'PC_Audit_Inventory_'.now()->format('Ymd_His').'.xlsx';
        $temporaryFile = tempnam(sys_get_temp_dir(), 'pc-audit-xlsx-');
        abort_if($temporaryFile === false, 500, 'Không tạo được file Excel tạm thời.');
        try {
            (new Xlsx($spreadsheet))->save($temporaryFile);
        } catch (\\Throwable $exception) {
            @unlink($temporaryFile);
            report($exception);
            abort(500, 'Không thể tạo file Excel. Vui lòng kiểm tra log máy chủ.');
        }
        return response()->download($temporaryFile, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
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
}
