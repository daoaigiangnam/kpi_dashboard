<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobTitle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class JobTitleController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $jobTitles = JobTitle::withCount('users')->when($search !== '', function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('level', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%");
            });
        })->orderBy('name')->paginate(20)->withQueryString();
        return view('admin.job_titles.index', compact('jobTitles', 'search'));
    }

    public function create() { return view('admin.job_titles.form', ['jobTitle' => new JobTitle(['is_active' => true])]); }
    public function store(Request $request) { JobTitle::create($request->validate($this->rules())); return redirect()->route('admin.job_titles.index')->with('success', 'Job title created.'); }
    public function edit(JobTitle $jobTitle) { return view('admin.job_titles.form', compact('jobTitle')); }
    public function update(Request $request, JobTitle $jobTitle) { $jobTitle->update($request->validate($this->rules($jobTitle))); return redirect()->route('admin.job_titles.index')->with('success', 'Job title updated.'); }

    public function destroy(JobTitle $jobTitle)
    {
        if ($jobTitle->users()->exists()) return back()->withErrors('This job title is assigned to users and cannot be deleted. Deactivate it instead.');
        $jobTitle->delete();
        return back()->with('success', 'Job title deleted.');
    }

    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Job Titles');
        $sheet->fromArray([
            ['Code', 'Job Title', 'Level', 'Target Workload Point', 'Description', 'Status'],
            ['IT-HELPDESK-L1', 'IT HelpDesk L1', 'L1', 120, 'Example only - remove this row before import.', 'Active'],
        ], null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        foreach (range('A', 'F') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:F2');
        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) { $writer->save('php://output'); }, 'job-title-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $jobTitles = JobTitle::when($search !== '', function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('level', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%");
            });
        })->orderBy('name')->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Job Titles');
        $sheet->fromArray([['Code', 'Job Title', 'Level', 'Target Workload Point', 'Description', 'Status']], null, 'A1');
        $row = 2;
        foreach ($jobTitles as $jobTitle) {
            $sheet->fromArray([[$jobTitle->code, $jobTitle->name, $jobTitle->level, (float) $jobTitle->target_workload_point, $jobTitle->description, $jobTitle->is_active ? 'Active' : 'Inactive']], null, "A{$row}");
            $row++;
        }
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        foreach (range('A', 'F') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:F'.max(1, $row - 1));
        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) { $writer->save('php://output'); }, 'job-titles-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        } catch (\Throwable $e) { return back()->withErrors('The Excel file could not be read. Please use the Job Title export/template format.'); }
        if (count($rows) < 2) return back()->withErrors('The import file contains no data rows.');
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $rows[1]);
        $requiredHeaders = ['code', 'job title', 'level', 'target workload point', 'description', 'status'];
        $missing = array_values(array_diff($requiredHeaders, $headers));
        if ($missing) return back()->withErrors('Invalid template. Missing columns: '.implode(', ', $missing).'.');
        $columns = array_flip($headers); $errors = []; $prepared = []; $seenCodes = [];
        foreach (array_slice($rows, 1, null, true) as $rowNumber => $row) {
            $code = trim((string) ($row[$columns['code']] ?? '')); $name = trim((string) ($row[$columns['job title']] ?? '')); $level = trim((string) ($row[$columns['level']] ?? '')); $description = trim((string) ($row[$columns['description']] ?? '')); $target = $row[$columns['target workload point']] ?? null; $status = strtolower(trim((string) ($row[$columns['status']] ?? 'active')));
            if ($code === '' && $name === '' && ($target === null || $target === '')) continue;
            if ($code === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $code)) { $errors[] = "Row {$rowNumber}: Code is required and may contain only letters, numbers, hyphens and underscores."; continue; }
            if ($name === '') { $errors[] = "Row {$rowNumber}: Job Title is required."; continue; }
            if (!is_numeric($target) || (float) $target < 0 || (float) $target > 99999999.99) { $errors[] = "Row {$rowNumber}: Target Workload Point must be a number from 0 to 99,999,999.99."; continue; }
            if (!in_array($status, ['active', 'inactive'], true)) { $errors[] = "Row {$rowNumber}: Status must be Active or Inactive."; continue; }
            if (isset($seenCodes[$code])) { $errors[] = "Row {$rowNumber}: Duplicate Code '{$code}' in the import file."; continue; }
            $seenCodes[$code] = true;
            $prepared[] = ['code' => $code, 'name' => $name, 'level' => $level !== '' ? $level : null, 'description' => $description !== '' ? $description : null, 'target_workload_point' => round((float) $target, 2), 'is_active' => $status === 'active'];
        }
        if ($errors) return back()->withErrors(array_slice($errors, 0, 50))->with('import_error_count', count($errors));
        if (!$prepared) return back()->withErrors('The import file contains no valid data rows.');
        DB::transaction(function () use ($prepared) { foreach ($prepared as $data) JobTitle::updateOrCreate(['code' => $data['code']], $data); });
        return back()->with('success', count($prepared).' job title(s) imported successfully. Existing records with the same Code were updated.');
    }

    private function rules(?JobTitle $jobTitle = null): array
    {
        return ['code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('job_titles', 'code')->ignore($jobTitle?->id)], 'name' => 'required|string|max:150', 'level' => 'nullable|string|max:50', 'description' => 'nullable|string|max:255', 'target_workload_point' => 'required|numeric|min:0|max:99999999.99', 'is_active' => 'boolean'];
    }
}
