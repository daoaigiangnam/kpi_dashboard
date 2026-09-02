<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $showDeleted = $request->boolean('deleted');
        $query = $showDeleted ? Department::withTrashed() : Department::query();

        $departments = $query
            ->withCount('users')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($x) use ($search) {
                    $x->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($showDeleted, fn ($q) => $q->whereNotNull('deleted_at'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.departments.index', compact('departments', 'search', 'showDeleted'));
    }

    public function create()
    {
        return view('admin.departments.form', ['department' => new Department()]);
    }

    public function store(Request $request)
    {
        Department::create($request->validate($this->rules()));
        return redirect()->route('admin.departments.index')->with('success', 'Department created.');
    }

    public function edit(Department $department)
    {
        return view('admin.departments.form', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $department->update($request->validate($this->rules($department)));
        return redirect()->route('admin.departments.index')->with('success', 'Department updated.');
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return back()->with('success', 'Department deleted. The record was retained for history.');
    }

    public function restore(int $department)
    {
        Department::withTrashed()->findOrFail($department)->restore();
        return back()->with('success', 'Department restored.');
    }

    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Departments');
        $sheet->fromArray([
            ['Code', 'Department Name', 'Description'],
            ['DEPT-001', 'Example Department', 'Example only - remove this row before import.'],
        ], null, 'A1');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        foreach (range('A', 'C') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:C2');
        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(fn () => $writer->save('php://output'), 'department-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $showDeleted = $request->boolean('deleted');
        $query = $showDeleted ? Department::withTrashed() : Department::query();

        $departments = $query
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($x) use ($search) {
                    $x->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($showDeleted, fn ($q) => $q->whereNotNull('deleted_at'))
            ->orderBy('name')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Departments');
        $sheet->fromArray([['Code', 'Department Name', 'Description']], null, 'A1');
        $row = 2;
        foreach ($departments as $department) {
            $sheet->fromArray([[$department->code, $department->name, $department->description]], null, "A{$row}");
            $row++;
        }
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        foreach (range('A', 'C') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:C' . max(1, $row - 1));
        $writer = new Xlsx($spreadsheet);
        $suffix = $showDeleted ? '-deleted' : '';
        return response()->streamDownload(fn () => $writer->save('php://output'), 'departments' . $suffix . '-' . now()->format('Ymd-His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        try {
            $rows = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null, true, true, true);
        } catch (\Throwable $e) {
            return back()->withErrors('The Excel file could not be read. Please use the Department export/template format.');
        }

        if (count($rows) < 2) return back()->withErrors('The import file contains no data rows.');
        $headers = array_map(fn ($v) => strtolower(trim((string) $v)), $rows[1]);
        $required = ['code', 'department name', 'description'];
        $missing = array_values(array_diff($required, $headers));
        if ($missing) return back()->withErrors('Invalid template. Missing columns: ' . implode(', ', $missing) . '.');

        $columns = array_flip($headers);
        $errors = [];
        $prepared = [];
        $seen = [];
        foreach (array_slice($rows, 1, null, true) as $n => $row) {
            $v = fn ($h) => trim((string) ($row[$columns[$h]] ?? ''));
            $code = $v('code'); $name = $v('department name'); $description = $v('description');
            if ($code === '' && $name === '' && $description === '') continue;
            if ($code === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $code)) { $errors[] = "Row {$n}: Code is required and may contain only letters, numbers, hyphens and underscores."; continue; }
            if ($name === '') { $errors[] = "Row {$n}: Department Name is required."; continue; }
            if (isset($seen[$code])) { $errors[] = "Row {$n}: Duplicate Code '{$code}' in the import file."; continue; }
            $seen[$code] = true;
            $prepared[] = ['code' => $code, 'name' => $name, 'description' => $description !== '' ? $description : null];
        }

        if ($errors) return back()->withErrors(array_slice($errors, 0, 50))->with('import_error_count', count($errors));
        if (!$prepared) return back()->withErrors('The import file contains no valid data rows.');

        DB::transaction(function () use ($prepared) {
            foreach ($prepared as $data) {
                $existing = Department::withTrashed()->where('code', $data['code'])->first();
                if ($existing) {
                    $existing->update($data);
                    if ($existing->trashed()) $existing->restore();
                } else {
                    Department::create($data);
                }
            }
        });

        return back()->with('success', count($prepared) . ' department(s) imported successfully.');
    }

    private function rules(?Department $department = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('departments', 'code')->ignore($department?->id)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
