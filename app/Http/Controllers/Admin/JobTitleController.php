<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobTitle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobTitleController extends Controller
{
    public function index()
    {
        return view('admin.job_titles.index', [
            'jobTitles' => JobTitle::withCount('users')->orderBy('name')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('admin.job_titles.form', ['jobTitle' => new JobTitle(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        JobTitle::create($data);

        return redirect()->route('admin.job_titles.index')->with('success', 'Job title created.');
    }

    public function edit(JobTitle $jobTitle)
    {
        return view('admin.job_titles.form', compact('jobTitle'));
    }

    public function update(Request $request, JobTitle $jobTitle)
    {
        $data = $request->validate($this->rules($jobTitle));
        $jobTitle->update($data);

        return redirect()->route('admin.job_titles.index')->with('success', 'Job title updated.');
    }

    public function destroy(JobTitle $jobTitle)
    {
        if ($jobTitle->users()->exists()) {
            return back()->withErrors('This job title is assigned to users and cannot be deleted. Deactivate it instead.');
        }

        $jobTitle->delete();

        return back()->with('success', 'Job title deleted.');
    }

    private function rules(?JobTitle $jobTitle = null): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50', 'alpha_dash',
                Rule::unique('job_titles', 'code')->ignore($jobTitle?->id),
            ],
            'name' => 'required|string|max:150',
            'level' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
            'target_workload_point' => 'required|numeric|min:0|max:99999999.99',
            'is_active' => 'boolean',
        ];
    }
}
