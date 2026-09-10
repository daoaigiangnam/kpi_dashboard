<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KpiSlaPriority;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $priority = strtoupper(trim((string) $request->query('priority', '')));
        $employeeId = $request->query('employee_id');
        $baseQuery = $this->ticketQuery($search, $priority, $employeeId);
        $ticketTotals = $this->ticketTotals(clone $baseQuery);
        $tickets = $baseQuery->paginate(25)->withQueryString();
        $priorities = KpiSlaPriority::orderBy('sort_order')->pluck('code');
        $employees = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);
        $totalTickets = Ticket::count();
        return view('admin.tickets.index', compact('tickets', 'search', 'priority', 'employeeId', 'priorities', 'employees', 'totalTickets', 'ticketTotals'));
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $priority = strtoupper(trim((string) $request->query('priority', '')));
        $employeeId = $request->query('employee_id');
        $query = $this->ticketQuery($search, $priority, $employeeId);
        $tickets = (clone $query)->get();
        $ticketTotals = $this->ticketTotals(clone $query);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ticket Data');
        $headers = ['ID','Priority (Ưu tiên)','Created on','Started on','Finished on','Pause(min)','Reopen','Company/Dept','Chi tiết nội dung đã xử lý','File chụp màn hình kết quả xử lý','Workload Point to Priority','Resolution (min)','SLA Target','SLA','Process','Started'];
        $sheet->fromArray([$headers], null, 'A1');
        $row = 2;
        foreach ($tickets as $ticket) {
            $sheet->fromArray([[
                $ticket->external_ticket_id,
                $ticket->priority,
                $ticket->created_on?->format('n/j/Y G:i'),
                $ticket->started_on?->format('n/j/Y G:i'),
                $ticket->finished_on?->format('n/j/Y G:i'),
                $ticket->pause_minutes,
                $ticket->reopen_count,
                $ticket->company_department ?: '',
                $ticket->resolution_detail ?: '',
                $ticket->result_screenshot ?: '',
                $ticket->workload_point !== null ? rtrim(rtrim(number_format((float) $ticket->workload_point, 2, '.', ''), '0'), '.') : '',
                $ticket->resolution_minutes ?? '',
                $ticket->sla_target_minutes ?? '',
                $ticket->sla_status ?: '',
                $ticket->process_status ?: '',
                $ticket->started_status ?: '',
            ]], null, 'A'.$row);
            $row++;
        }
        $totalRow = max(2, $row);
        $sheet->fromArray([[
            'Tổng',
            $ticketTotals['ticket_count'],
            '',
            '',
            $ticketTotals['finished_count'],
            $ticketTotals['pause_minutes'],
            $ticketTotals['reopen_ticket_count'],
            '',
            '',
            '',
            rtrim(rtrim(number_format($ticketTotals['workload_point'], 2, '.', ''), '0'), '.'),
            '',
            '',
            $ticketTotals['sla_met'],
            $ticketTotals['process_met'],
            $ticketTotals['started'],
        ]], null, 'A'.$totalRow);
        $sheet->getStyle('A1:P1')->getFont()->setBold(true);
        $sheet->getStyle('A1:P1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFB7DEE8');
        $sheet->getStyle('A'.$totalRow.':P'.$totalRow)->getFont()->setBold(true);
        $sheet->getStyle('A'.$totalRow.':P'.$totalRow)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFFF00');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:P'.max(1, $row - 1));
        foreach (range('A', 'P') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $filename = 'ticket-data-kpi-check-'.now()->format('Ymd-His').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function ticketQuery(string $search = '', string $priority = '', mixed $employeeId = null)
    {
        return Ticket::query()->with('employee')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('external_ticket_id', 'like', "%{$search}%")
                        ->orWhere('company_department', 'like', "%{$search}%")
                        ->orWhere('resolution_detail', 'like', "%{$search}%");
                });
            })
            ->when($priority !== '', fn ($q) => $q->where('priority', $priority))
            ->when($employeeId !== null && $employeeId !== '', fn ($q) => $q->where('employee_id', (int) $employeeId))
            ->orderByRaw('CAST(external_ticket_id AS UNSIGNED) ASC')
            ->orderBy('external_ticket_id');
    }

    private function ticketTotals($query): array
    {
        $completed = (clone $query)->whereNotNull('finished_on');
        return [
            'ticket_count' => (clone $query)->count(),
            'created_count' => (clone $query)->whereNotNull('created_on')->count(),
            'started_count' => (clone $query)->whereNotNull('started_on')->count(),
            'finished_count' => (clone $query)->whereNotNull('finished_on')->count(),
            'pause_minutes' => (int) ((clone $query)->sum('pause_minutes') ?? 0),
            'reopen_ticket_count' => (clone $query)->where('reopen_count', '>', 0)->count(),
            'company_department_count' => (clone $query)->whereNotNull('company_department')->where('company_department', '<>', '')->count(),
            'resolution_detail_count' => (clone $query)->whereNotNull('resolution_detail')->where('resolution_detail', '<>', '')->count(),
            'result_screenshot_count' => (clone $query)->whereNotNull('result_screenshot')->where('result_screenshot', '<>', '')->count(),
            'workload_point' => (float) ((clone $query)->sum('workload_point') ?? 0),
            'resolution_minutes' => (int) ($completed->sum('resolution_minutes') ?? 0),
            'sla_target_minutes' => (int) ($completed->sum('sla_target_minutes') ?? 0),
            'sla_met' => (clone $completed)->where('sla_status', 'Đạt')->count(),
            'sla_not_met' => (clone $completed)->where('sla_status', 'Không Đạt')->count(),
            'process_met' => (clone $completed)->where('process_status', 'Đạt')->count(),
            'started' => (clone $query)->where('started_status', 'Có')->count(),
        ];
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required','integer','exists:users,id'],
            'file' => ['required','file','max:20480', function ($attribute, $value, $fail) {
                $extension = strtolower((string) $value->getClientOriginalExtension());
                if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) $fail('The file field must be a file of type: xlsx, xls, csv.');
            }],
        ]);
        $employee = User::query()->where('id', $data['employee_id'])->where('is_active', true)->first();
        if (!$employee) return back()->withErrors(['employee_id' => 'The selected employee is not active.']);
        try {
            $rows = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null, true, true, true);
        } catch (\Throwable) {
            return back()->withErrors('The ticket file could not be read. Please use the Ticket import template.');
        }
        if (count($rows) < 2) return back()->withErrors('The import file contains no ticket data rows.');
        $headers = [];
        foreach ($rows[1] as $column => $header) {
            $normalized = $this->normalizeHeader($header);
            if ($normalized !== '') $headers[$normalized] = $column;
        }
        $columns = [
            'id' => $this->findHeader($headers, ['id','ticket id']),
            'priority' => $this->findHeader($headers, ['priority','priority uu tien']),
            'created_on' => $this->findHeader($headers, ['created on']),
            'started_on' => $this->findHeader($headers, ['started on']),
            'finished_on' => $this->findHeader($headers, ['finished on']),
            'pause_minutes' => $this->findHeader($headers, ['pause min','pause minutes','pause']),
            'reopen_count' => $this->findHeader($headers, ['reopen','reopen count']),
            'company_department' => $this->findHeader($headers, ['company dept','company department','company']),
            'resolution_detail' => $this->findHeader($headers, ['chi tiet noi dung da xu ly','resolution detail']),
            'result_screenshot' => $this->findHeader($headers, ['file chup man hinh ket qua xu ly','result screenshot']),
        ];
        foreach (['id','priority','created_on'] as $required) if (!$columns[$required]) return back()->withErrors("Invalid Ticket template. Missing required column: {$required}.");
        $priorityConfig = KpiSlaPriority::query()->get()->keyBy(fn ($item) => strtoupper(trim((string) $item->code)));
        if ($priorityConfig->isEmpty()) return back()->withErrors('No SLA Priority configuration is available. Please configure KPI Parameters first.');
        $errors = [];
        $prepared = [];
        $seen = [];
        $duplicateIds = [];
        $sourceFile = $request->file('file')->getClientOriginalName();
        foreach (array_slice($rows, 1, null, true) as $rowNumber => $row) {
            $value = fn (string $field): string => $columns[$field] ? trim((string) ($row[$columns[$field]] ?? '')) : '';
            $externalId = $value('id');
            $priorityKey = $this->resolvePriorityKey($value('priority'), $priorityConfig);
            if ($externalId === '' || in_array(mb_strtolower($externalId), ['tong','tổng','total'], true)) continue;
            if (isset($seen[$externalId])) { $duplicateIds[] = $externalId; continue; }
            $seen[$externalId] = true;
            if (Ticket::where('external_ticket_id', $externalId)->exists()) { $duplicateIds[] = $externalId; continue; }
            if ($priorityKey === null) { $errors[] = "Row {$rowNumber}: Priority '{$value('priority')}' is not configured in KPI Parameters."; continue; }
            try {
                $createdOn = $this->parseDate($value('created_on'));
                $startedOn = $this->parseDate($value('started_on'));
                $finishedOn = $this->parseDate($value('finished_on'));
            } catch (\Throwable) {
                $errors[] = "Row {$rowNumber}: Invalid date/time. Use the Bitrix date format (M/D/YYYY or M/D/YYYY HH:MM).";
                continue;
            }
            if (!$createdOn) { $errors[] = "Row {$rowNumber}: Created on is required."; continue; }
            $pause = $this->parseInteger($value('pause_minutes'));
            $reopen = $this->parseInteger($value('reopen_count'));
            if ($pause < 0 || $reopen < 0) { $errors[] = "Row {$rowNumber}: Pause(min) and Reopen cannot be negative."; continue; }
            $resolutionMinutes = null;
            if ($finishedOn) {
                $resolutionMinutes = (int) round(($finishedOn->timestamp - $createdOn->timestamp) / 60) - $pause;
                if ($resolutionMinutes < 0) { $errors[] = "Row {$rowNumber}: Resolution time becomes negative after Pause(min). Please check Created on, Finished on and Pause(min)."; continue; }
            }
            $config = $priorityConfig[$priorityKey];
            $companyDepartment = $value('company_department');
            $resolutionDetail = $value('resolution_detail');
            $resultScreenshot = $value('result_screenshot');
            $hasProcessData = $companyDepartment !== '' && $resolutionDetail !== '' && $resultScreenshot !== '';
            $slaStatus = $resolutionMinutes === null ? 'Không đủ dữ liệu' : ($resolutionMinutes <= (int) $config->resolution_minutes ? 'Đạt' : 'Không Đạt');
            $processStatus = $finishedOn ? ($hasProcessData ? 'Đạt' : 'Không Đạt') : 'Không đủ dữ liệu';
            $raw = [];
            foreach ($headers as $normalized => $column) $raw[$normalized] = $row[$column] ?? null;
            $prepared[] = [
                'external_ticket_id' => $externalId,
                'employee_id' => $employee->id,
                'priority' => $config->code,
                'created_on' => $createdOn,
                'started_on' => $startedOn,
                'finished_on' => $finishedOn,
                'pause_minutes' => $pause,
                'reopen_count' => $reopen,
                'company_department' => $companyDepartment !== '' ? $companyDepartment : null,
                'resolution_detail' => $resolutionDetail !== '' ? $resolutionDetail : null,
                'result_screenshot' => $resultScreenshot !== '' ? $resultScreenshot : null,
                'workload_point' => $config->workload_point,
                'resolution_minutes' => $resolutionMinutes,
                'sla_target_minutes' => $config->resolution_minutes,
                'sla_status' => $slaStatus,
                'process_status' => $processStatus,
                'started_status' => $startedOn ? 'Có' : 'Không',
                'source' => 'bitrix_excel',
                'source_file' => Str::limit($sourceFile, 255, ''),
                'source_payload' => $raw,
            ];
        }
        if ($errors) return back()->withErrors(array_slice($errors, 0, 50))->with('import_error_count', count($errors));
        if (!$prepared && !$duplicateIds) return back()->withErrors('The import file contains no valid ticket rows. The report Total row is ignored and is not stored.');
        $created = 0;
        DB::transaction(function () use ($prepared, &$created) {
            foreach ($prepared as $ticketData) { Ticket::create($ticketData); $created++; }
        });
        $duplicateCount = count($duplicateIds);
        $message = "Ticket import completed for {$employee->name}: {$created} new ticket(s).";
        if ($duplicateCount > 0) {
            $shown = implode(', ', array_slice($duplicateIds, 0, 20));
            $message .= " {$duplicateCount} duplicate Bitrix Ticket ID(s) were skipped: {$shown}";
            if ($duplicateCount > 20) $message .= ' ...';
        }
        return back()->with('success', $message.' The Excel Total row was not stored.');
    }

    private function resolvePriorityKey(string $value, $priorityConfig): ?string
    {
        $input = strtoupper(trim($value));
        if ($input === '') return null;
        foreach ($priorityConfig as $key => $config) {
            $configured = strtoupper(trim((string) $config->code));
            if ($input === $configured) return $key;
            if (preg_match('/^(P\d+)/', $input, $inputMatch) && preg_match('/^(P\d+)/', $configured, $configuredMatch) && $inputMatch[1] === $configuredMatch[1]) return $key;
        }
        return null;
    }

    private function normalizeHeader(mixed $value): string
    {
        $value = Str::ascii(trim((string) $value));
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
        return trim($value);
    }

    private function findHeader(array $headers, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            $normalized = $this->normalizeHeader($alias);
            if (isset($headers[$normalized])) return $headers[$normalized];
        }
        return null;
    }

    private function parseInteger(string $value): int
    {
        if ($value === '') return 0;
        return (int) round((float) str_replace([',', ' '], '', $value));
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') return null;

        // PhpSpreadsheet may return the raw Excel serial when the cell is a real Excel date.
        if (is_numeric($value) && (float) $value > 0) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
        }

        // Bitrix exports dates in US-style M/D/YYYY. Parse this explicitly before
        // generic Carbon parsing so values such as 9/1/2026 are never interpreted
        // as 1 September vs 9 January incorrectly.
        $formats = [
            'n/j/Y H:i:s',
            'n/j/Y H:i',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
            'n/j/Y',
            'm/d/Y',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
            'd-m-Y H:i:s',
            'd-m-Y H:i',
            'd-m-Y',
            'Y-m-d',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                // Try the next known format.
            }
        }

        return Carbon::parse($value);
    }
}
