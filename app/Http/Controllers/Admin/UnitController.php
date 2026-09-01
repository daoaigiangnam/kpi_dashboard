<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $showDeleted = $request->boolean('deleted');

        $query = $showDeleted ? Unit::withTrashed() : Unit::query();
        $units = $query
            ->withCount('users')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($x) use ($search) {
                    $x->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('tax_code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($showDeleted, fn ($q) => $q->whereNotNull('deleted_at'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.units.index', compact('units', 'search', 'showDeleted'));
    }

    public function create()
    {
        return view('admin.units.form', ['unit' => new Unit()]);
    }

    public function store(Request $request)
    {
        Unit::create($request->validate($this->rules()));
        return redirect()->route('admin.units.index')->with('success', 'Unit created.');
    }

    public function edit(Unit $unit)
    {
        return view('admin.units.form', compact('unit'));
    }

    public function update(Request $request, Unit $unit)
    {
        $unit->update($request->validate($this->rules($unit)));
        return redirect()->route('admin.units.index')->with('success', 'Unit updated.');
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();
        return back()->with('success', 'Unit deleted. The record was retained for history.');
    }

    public function restore(int $unit)
    {
        Unit::withTrashed()->findOrFail($unit)->restore();
        return back()->with('success', 'Unit restored.');
    }

    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Units');
        $sheet->fromArray([
            ['Code', 'Unit Name', 'Address', 'Phone', 'MST', 'Description'],
            ['UNIT-001', 'Example Unit', 'Example address', '0281234567', '0123456789', 'Example only - remove this row before import.'],
        ], null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        foreach (range('A', 'F') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:F2');
        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(fn () => $writer->save('php://output'), 'unit-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $showDeleted = $request->boolean('deleted');
        $query = $showDeleted ? Unit::withTrashed() : Unit::query();

        $units = $query
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($x) use ($search) {
                    $x->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('tax_code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($showDeleted, fn ($q) => $q->whereNotNull('deleted_at'))
            ->orderBy('name')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Units');
        $sheet->fromArray([['Code', 'Unit Name', 'Address', 'Phone', 'MST', 'Description']], null, 'A1');
        $row = 2;
        foreach ($units as $unit) {
            $sheet->fromArray([[
                $unit->code, $unit->name, $unit->address, $unit->phone, $unit->tax_code, $unit->description,
            ]], null, "A{$row}");
            $row++;
        }
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        foreach (range('A', 'F') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:F' . max(1, $row - 1));
        $writer = new Xlsx($spreadsheet);
        $suffix = $showDeleted ? '-deleted' : '';
        return response()->streamDownload(fn () => $writer->save('php://output'), 'units' . $suffix . '-' . now()->format('Ymd-His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        try {
            $rows = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null, true, true, true);
        } catch (\Throwable $e) {
            return back()->withErrors('The Excel file could not be read. Please use the Unit export/template format.');
        }

        if (count($rows) < 2) return back()->withErrors('The import file contains no data rows.');
        $headers = array_map(fn ($v) => strtolower(trim((string) $v)), $rows[1]);
        $required = ['code', 'unit name', 'address', 'phone', 'mst', 'description'];
        $missing = array_values(array_diff($required, $headers));
        if ($missing) return back()->withErrors('Invalid template. Missing columns: ' . implode(', ', $missing) . '.');

        $columns = array_flip($headers);
        $errors = [];
        $prepared = [];
        $seen = [];
        foreach (array_slice($rows, 1, null, true) as $n => $row) {
            $v = fn ($h) => trim((string) ($row[$columns[$h]] ?? ''));
            $code = $v('code'); $name = $v('unit name'); $address = $v('address');
            $phone = $v('phone'); $taxCode = $v('mst'); $description = $v('description');
            if ($code === '' && $name === '' && $address === '' && $phone === '' && $taxCode === '') continue;
            if ($code === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $code)) { $errors[] = "Row {$n}: Code is required and may contain only letters, numbers, hyphens and underscores."; continue; }
            if ($name === '') { $errors[] = "Row {$n}: Unit Name is required."; continue; }
            if ($address === '') { $errors[] = "Row {$n}: Address is required."; continue; }
            if ($phone === '') { $errors[] = "Row {$n}: Phone is required."; continue; }
            if ($taxCode === '') { $errors[] = "Row {$n}: MST is required."; continue; }
            if (isset($seen[$code])) { $errors[] = "Row {$n}: Duplicate Code '{$code}' in the import file."; continue; }
            $seen[$code] = true;
            $prepared[] = ['code' => $code, 'name' => $name, 'address' => $address, 'phone' => $phone, 'tax_code' => $taxCode, 'description' => $description !== '' ? $description : null];
        }

        if ($errors) return back()->withErrors(array_slice($errors, 0, 50))->with('import_error_count', count($errors));
        if (!$prepared) return back()->withErrors('The import file contains no valid data rows.');

        DB::transaction(function () use ($prepared) {
            foreach ($prepared as $data) {
                $existing = Unit::withTrashed()->where('code', $data['code'])->first();
                if ($existing) {
                    $existing->update($data);
                    if ($existing->trashed()) $existing->restore();
                } else {
                    Unit::create($data);
                }
            }
        });

        return back()->with('success', count($prepared) . ' unit(s) imported successfully.');
    }

    private function rules(?Unit $unit = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('units', 'code')->ignore($unit?->id)],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'tax_code' => ['required', 'string', 'max:50', Rule::unique('units', 'tax_code')->ignore($unit?->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
