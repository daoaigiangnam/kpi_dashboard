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

        $tickets = Ticket::query()
            ->with('employee')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('external_ticket_id', 'like', "%{$search}%")
                        ->orWhere('company_department', 'like', "%{$search}%")
                        ->orWhere('resolution_detail', 'like', "%{$search}%");
                });
            })
            ->when($priority !== '', fn ($q) => $q->where('priority', $priority))
            ->when($employeeId !== null && $employeeId !== '', fn ($q) => $q->where('employee_id', (int) $employeeId))
            ->orderByDesc('created_on')
            ->paginate(25)
            ->withQueryString();

        $priorities = KpiSlaPriority::orderBy('sort_order')->pluck('code');
        $employees = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);
        $totalTickets = Ticket::count();

        return view('admin.tickets.index', compact(
            'tickets', 'search', 'priority', 'employeeId', 'priorities', 'employees', 'totalTickets'
        ));
    }

    public function template()
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->setTitle('Tickets');

        // Import template contains only raw input fields. Calculated KPI fields
        // are intentionally excluded because the application generates them.
        $sheet->fromArray([
            [
                'ID', 'Priority (Ưu tiên)', 'Created on', 'Started on', 'Finished on',
                'Pause(min)', 'Reopen', 'Company/Dept', 'Chi tiết nội dung đã xử lý',
                'File chụp màn hình kết quả xử lý',
            ],
            ['1001', 'P1', '2025-09-01 08:00', '2025-09-01 08:05', '2025-09-01 10:00', 0, 0, 'HelpDesk', 'Có', 'Có'],
            ['1002', 'P2', '2025-09-02 09:00', '2025-09-02 09:30', '2025-09-02 15:00', 60, 1, 'HelpDesk', 'Có', 'Có'],
            ['1003', 'P3', '2025-09-03 09:00', '2025-09-03 10:40', '2025-09-03 12:00', 0, 0, 'HelpDesk', 'Có', 'Có'],
            ['1004', 'P4', '2025-09-04 09:00', '2025-09-05 11:10', '2025-09-05 14:00', 0, 2, 'HelpDesk', 'Có', 'Có'],
            ['1005', 'P2', '2025-09-05 09:00', '2025-09-05 08:30', '2025-09-05 17:00', 0, 0, 'HelpDesk', 'Có', 'Có'],
            ['1006', 'P3', '2025-09-06 09:00', '2025-09-06 10:40', '2025-09-06 13:00', 120, 0, 'HelpDesk', 'Có', 'Có'],
            ['1007', 'P1', '2025-09-07 09:00', '2025-09-07 08:50', '2025-09-07 20:00', 180, 3, 'HelpDesk', 'Có', 'Có'],
            ['1008', 'P4', '2025-09-08 09:00', '2025-09-08 09:24', '2025-09-08 15:00', 0, 0, 'HelpDesk', 'Có', 'Có'],
            ['1009', 'P3', '2025-09-09 09:00', '2025-09-09 09:20', '2025-09-09 18:00', 0, 0, 'HelpDesk', 'Có', 'Có'],
            ['1010', 'P2', '2025-09-10 09:00', '2025-09-10 09:10', '2025-09-10 16:00', 60, 0, 'HelpDesk', 'Có', 'Có'],
        ], null, 'A1');

        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:J11');

        $writer = new Xlsx($sheet->getParent());

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            'ticket-import-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $employee = User::query()->where('id', $data['employee_id'])->where('is_active', true)->first();
        if (!$employee) {
            return back()->withErrors(['employee_id' => 'The selected employee is not active.']);
        }

        try {
            $rows = IOFactory::load($request->file('file')->getRealPath())
                ->getActiveSheet()
                ->toArray(null, true, true, true);
        } catch (\Throwable) {
            return back()->withErrors('The ticket file could not be read. Please use the Ticket import template.');
        }

        if (count($rows) < 2) {
            return back()->withErrors('The import file contains no ticket data rows.');
        }

        $headers = [];
        foreach ($rows[1] as $column => $header) {
            $normalized = $this->normalizeHeader($header);
            if ($normalized !== '') {
                $headers[$normalized] = $column;
            }
        }

        $columns = [
            'id' => $this->findHeader($headers, ['id', 'ticket id']),
            'priority' => $this->findHeader($headers, ['priority', 'priority uu tien']),
            'created_on' => $this->findHeader($headers, ['created on']),
            'started_on' => $this->findHeader($headers, ['started on']),
            'finished_on' => $this->findHeader($headers, ['finished on']),
            'pause_minutes' => $this->findHeader($headers, ['pause min', 'pause minutes', 'pause']),
            'reopen_count' => $this->findHeader($headers, ['reopen', 'reopen count']),
            'company_department' => $this->findHeader($headers, ['company dept', 'company department']),
            'resolution_detail' => $this->findHeader($headers, ['chi tiet noi dung da xu ly', 'resolution detail']),
            'result_screenshot' => $this->findHeader($headers, ['file chup man hinh ket qua xu ly', 'result screenshot']),
        ];

        foreach (['id', 'priority', 'created_on'] as $required) {
            if (!$columns[$required]) {
                return back()->withErrors("Invalid Ticket template. Missing required column: {$required}.");
            }
        }

        $priorityConfig = KpiSlaPriority::get()->keyBy(fn ($item) => strtoupper($item->code));
        if ($priorityConfig->isEmpty()) {
            return back()->withErrors('No SLA Priority configuration is available. Please configure KPI Parameters first.');
        }

        $errors = [];
        $prepared = [];
        $seen = [];
        $duplicateIds = [];
        $sourceFile = $request->file('file')->getClientOriginalName();

        foreach (array_slice($rows, 1, null, true) as $rowNumber => $row) {
            $value = fn (string $field): string => $columns[$field] ? trim((string) ($row[$columns[$field]] ?? '')) : '';

            $externalId = $value('id');
            $priorityCode = strtoupper($value('priority'));

            if ($externalId === '' || in_array(mb_strtolower($externalId), ['tong', 'tổng', 'total'], true)) {
                continue;
            }

            if (isset($seen[$externalId])) {
                $duplicateIds[] = $externalId;
                continue;
            }
            $seen[$externalId] = true;

            if (Ticket::where('external_ticket_id', $externalId)->exists()) {
                $duplicateIds[] = $externalId;
                continue;
            }

            if (!isset($priorityConfig[$priorityCode])) {
                $errors[] = "Row {$rowNumber}: Priority '{$priorityCode}' is not configured in KPI Parameters.";
                continue;
            }

            try {
                $createdOn = $this->parseDate($value('created_on'));
                $startedOn = $this->parseDate($value('started_on'));
                $finishedOn = $this->parseDate($value('finished_on'));
            } catch (\Throwable) {
                $errors[] = "Row {$rowNumber}: Invalid date/time. Use YYYY-MM-DD HH:MM or the Excel date format.";
                continue;
            }

            if (!$createdOn) {
                $errors[] = "Row {$rowNumber}: Created on is required.";
                continue;
            }

            $pause = $this->parseInteger($value('pause_minutes'));
            $reopen = $this->parseInteger($value('reopen_count'));

            if ($pause < 0 || $reopen < 0) {
                $errors[] = "Row {$rowNumber}: Pause(min) and Reopen cannot be negative.";
                continue;
            }

            $resolutionMinutes = null;
            if ($finishedOn) {
                $resolutionMinutes = (int) round(($finishedOn->timestamp - $createdOn->timestamp) / 60) - $pause;
                if ($resolutionMinutes < 0) {
                    $errors[] = "Row {$rowNumber}: Resolution time becomes negative after Pause(min).";
                    continue;
                }
            }

            $config = $priorityConfig[$priorityCode];
            $companyDepartment = $value('company_department');
            $resolutionDetail = $value('resolution_detail');
            $resultScreenshot = $value('result_screenshot');
            $hasProcessData = $companyDepartment !== '' && $resolutionDetail !== '' && $resultScreenshot !== '';
            $slaStatus = $resolutionMinutes === null
                ? 'Không đủ dữ liệu'
                : ($resolutionMinutes <= (int) $config->resolution_minutes ? 'Đạt' : 'Không Đạt');

            $raw = [];
            foreach ($headers as $normalized => $column) {
                $raw[$normalized] = $row[$column] ?? null;
            }

            $prepared[] = [
                'external_ticket_id' => $externalId,
                'employee_id' => $employee->id,
                'priority' => $priorityCode,
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
                'process_status' => $hasProcessData ? 'Đạt' : 'Không Đạt',
                'started_status' => $startedOn ? 'Có' : 'Không',
                'source' => 'bitrix_excel',
                'source_file' => Str::limit($sourceFile, 255, ''),
                'source_payload' => $raw,
            ];
        }

        if ($errors) {
            return back()
                ->withErrors(array_slice($errors, 0, 50))
                ->with('import_error_count', count($errors));
        }

        if (!$prepared && !$duplicateIds) {
            return back()->withErrors('The import file contains no valid ticket rows. The report Total row is ignored and is not stored.');
        }

        $created = 0;
        DB::transaction(function () use ($prepared, &$created) {
            foreach ($prepared as $ticketData) {
                Ticket::create($ticketData);
                $created++;
            }
        });

        $duplicateCount = count($duplicateIds);
        $message = "Ticket import completed for {$employee->name}: {$created} new ticket(s).";
        if ($duplicateCount > 0) {
            $shown = implode(', ', array_slice($duplicateIds, 0, 20));
            $message .= " {$duplicateCount} duplicate Bitrix Ticket ID(s) were skipped: {$shown}";
            if ($duplicateCount > 20) {
                $message .= ' ...';
            }
        }
        $message .= ' The Excel Total row was not stored.';

        return back()->with('success', $message);
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
            if (isset($headers[$normalized])) {
                return $headers[$normalized];
            }
        }

        return null;
    }

    private function parseInteger(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        return (int) round((float) str_replace([',', ' '], '', $value));
    }

    private function parseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 0) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd-m-Y H:i:s', 'd-m-Y H:i', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
            }
        }

        return Carbon::parse($value);
    }
}
