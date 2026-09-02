<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KpiSlaPriority;
use App\Models\KpiWeight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KpiParameterController extends Controller
{
    public function index()
    {
        $weights = KpiWeight::orderBy('sort_order')->get();
        $priorities = KpiSlaPriority::orderBy('sort_order')->get();

        return view('admin.kpi_parameters.index', compact('weights', 'priorities'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'weights' => ['required', 'array'],
            'weights.*' => ['required', 'integer', 'min:0', 'max:100'],
            'priorities' => ['required', 'array'],
            'priorities.*.response_minutes' => ['required', 'integer', 'min:0', 'max:525600'],
            'priorities.*.resolution_minutes' => ['required', 'integer', 'min:0', 'max:525600'],
            'priorities.*.weight' => ['required', 'integer', 'min:0', 'max:100'],
            'priorities.*.workload_point' => ['required', 'integer', 'min:0', 'max:999999'],
        ]);

        if (array_sum(array_map('intval', $data['weights'])) !== 100) {
            return back()->withErrors(['weights' => 'KPI weight total must equal 100%.'])->withInput();
        }

        $priorityIds = KpiSlaPriority::pluck('id')->map(fn ($id) => (string) $id)->all();
        $submittedIds = array_map('strval', array_keys($data['priorities']));
        sort($priorityIds);
        sort($submittedIds);
        if ($priorityIds !== $submittedIds) {
            return back()->withErrors(['priorities' => 'The KPI priority configuration is invalid. Please reload the page and try again.'])->withInput();
        }

        DB::transaction(function () use ($data) {
            foreach ($data['weights'] as $id => $weight) {
                KpiWeight::whereKey($id)->update(['weight' => (int) $weight]);
            }

            foreach ($data['priorities'] as $id => $priority) {
                KpiSlaPriority::whereKey($id)->update([
                    'response_minutes' => (int) $priority['response_minutes'],
                    'resolution_minutes' => (int) $priority['resolution_minutes'],
                    'weight' => (int) $priority['weight'],
                    'workload_point' => (int) $priority['workload_point'],
                ]);
            }
        });

        return back()->with('success', 'KPI parameters saved successfully.');
    }
}
