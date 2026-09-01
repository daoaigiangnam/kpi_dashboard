<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        // Always include soft-deleted groups so they remain searchable and recoverable.
        $query = UserGroup::withTrashed()
            ->withCount('users')
            ->with([
                'permissions',
                'users' => fn ($users) => $users
                    ->with(['jobTitle', 'departmentRelation', 'unit'])
                    ->orderBy('name'),
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('users', function ($users) use ($search) {
                            $users->where(function ($users) use ($search) {
                                $users->where('employee_code', 'like', "%{$search}%")
                                    ->orWhere('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                        });
                });
            })
            ->orderBy('name');

        $groups = $query->paginate(20)->withQueryString();

        return view('admin.groups.index', compact('groups', 'search'));
    }

    public function create()
    {
        return view('admin.groups.form', [
            'group' => new UserGroup(),
            'permissions' => Permission::orderBy('module')->orderBy('name')->get()->groupBy('module'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:user_groups,name',
            'description' => 'nullable|string|max:255',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $group = UserGroup::create($data);
        $group->permissions()->sync($data['permissions'] ?? []);

        return redirect()->route('admin.groups.index')->with('success', 'Group created.');
    }

    public function edit(UserGroup $group)
    {
        return view('admin.groups.form', [
            'group' => $group,
            'permissions' => Permission::orderBy('module')->orderBy('name')->get()->groupBy('module'),
        ]);
    }

    public function update(Request $request, UserGroup $group)
    {
        if ($group->is_system && $group->name === 'Super Admin' && $request->input('name') !== 'Super Admin') {
            return back()->withErrors('Super Admin cannot be renamed.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:100|unique:user_groups,name,' . $group->id,
            'description' => 'nullable|string|max:255',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $group->update($data);
        $group->permissions()->sync($data['permissions'] ?? []);

        return redirect()->route('admin.groups.index')->with('success', 'Group updated.');
    }

    public function destroy(UserGroup $group)
    {
        // Groups are soft-deleted (hidden), never physically removed.
        // This applies to both custom and system groups so an empty group can be hidden
        // and later restored. A group with assigned users must be emptied first.
        $userCount = $group->users()->count();
        if ($userCount > 0) {
            return back()->withErrors("This group cannot be deleted while {$userCount} user(s) are assigned. Remove all users from the group first.");
        }

        $group->delete();

        return back()->with('success', 'Group deleted (hidden). The record was retained for history and can be restored later.');
    }

    public function restore(int $group)
    {
        $model = UserGroup::withTrashed()->findOrFail($group);
        $model->restore();

        return back()->with('success', 'Group restored. Existing permissions are active again.');
    }

    public function removeUser(UserGroup $group, User $user)
    {
        if ($user->user_group_id !== $group->id) {
            return back()->withErrors('The selected user is not assigned to this group.');
        }

        // Never allow an administrator to accidentally remove the last Super Admin account.
        if ($group->name === 'Super Admin' && $group->users()->count() <= 1) {
            return back()->withErrors('The last Super Admin cannot be removed from the Super Admin group.');
        }

        $user->update(['user_group_id' => null]);

        return back()->with('success', "{$user->name} was removed from {$group->name}. The user account remains active and can be assigned to another group.");
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $groups = UserGroup::withTrashed()
            ->withCount('users')
            ->with([
                'permissions',
                'users' => fn ($users) => $users
                    ->with(['jobTitle', 'departmentRelation', 'unit'])
                    ->orderBy('name'),
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('users', function ($users) use ($search) {
                            $users->where(function ($users) use ($search) {
                                $users->where('employee_code', 'like', "%{$search}%")
                                    ->orWhere('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                        });
                });
            })
            ->orderBy('name')
            ->get();

        $spreadsheet = new Spreadsheet();

        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Groups');
        $summary->fromArray([
            ['Group', 'Description', 'Users', 'Permissions', 'Status', 'System Group'],
        ], null, 'A1');

        $row = 2;
        foreach ($groups as $group) {
            $summary->fromArray([[
                $group->name,
                $group->description,
                $group->users_count,
                $group->permissions->count(),
                $group->trashed() ? 'Hidden' : 'Active',
                $group->is_system ? 'Yes' : 'No',
            ]], null, "A{$row}");
            $row++;
        }

        $summary->getStyle('A1:F1')->getFont()->setBold(true);
        foreach (range('A', 'F') as $column) {
            $summary->getColumnDimension($column)->setAutoSize(true);
        }
        $summary->freezePane('A2');
        $summary->setAutoFilter('A1:F' . max(1, $row - 1));

        $detail = $spreadsheet->createSheet();
        $detail->setTitle('Group Users');
        $detail->fromArray([
            ['Group', 'Group Status', 'Employee Code', 'Name', 'Email', 'Phone', 'Department', 'Unit', 'Job Title', 'User Status'],
        ], null, 'A1');

        $detailRow = 2;
        foreach ($groups as $group) {
            foreach ($group->users as $user) {
                $detail->fromArray([[
                    $group->name,
                    $group->trashed() ? 'Hidden' : 'Active',
                    $user->employee_code,
                    $user->name,
                    $user->email,
                    $user->phone,
                    $user->departmentRelation?->name,
                    $user->unit?->name,
                    $user->jobTitle?->name,
                    $user->is_active ? 'Active' : 'Inactive',
                ]], null, "A{$detailRow}");
                $detailRow++;
            }
        }

        $detail->getStyle('A1:J1')->getFont()->setBold(true);
        foreach (range('A', 'J') as $column) {
            $detail->getColumnDimension($column)->setAutoSize(true);
        }
        $detail->freezePane('A2');
        $detail->setAutoFilter('A1:J' . max(1, $detailRow - 1));

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            'user-groups-' . now()->format('Ymd-His') . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }
}
