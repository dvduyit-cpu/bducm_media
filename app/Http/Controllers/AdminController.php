<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function settings(Request $request)
    {
        return view('settings', ['settings' => Setting::pluck('value', 'key')]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate(['theme_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'organization_name' => 'nullable|string|max:120',
            'default_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 'deadline_day' => 'nullable|integer|min:1|max:7', 'deadline_time' => 'nullable|date_format:H:i']);
        $request->user()->update(['theme_color' => $data['theme_color']]);
        if ($request->user()->isOffice()) {
            foreach (['organization_name', 'default_color', 'deadline_day', 'deadline_time'] as $key) {
                if (isset($data[$key])) {
                    Setting::updateOrCreate(['key' => $key], ['value' => $data[$key]]);
                }
            }
        }

        return back()->with('success', 'Đã lưu thiết lập giao diện.');
    }

    public function users(Request $request)
    {
        abort_unless($request->user()->isOffice(), 403);

        return view('admin.users', ['users' => User::with('department')->orderBy('name')->paginate(20), 'departments' => Department::all(), 'roles' => Role::cases()]);
    }

    public function saveUser(Request $request, ?User $user = null)
    {
        abort_unless($request->user()->isOffice(), 403);
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'password' => [$user?->exists ? 'nullable' : 'required', 'string', 'min:10', 'max:128'], 'role' => ['required', Rule::enum(Role::class)],
            'department_id' => 'nullable|required_if:role,head,staff|exists:departments,id', 'active' => 'nullable|boolean']);
        $data['active'] = $request->boolean('active');
        if ($user?->id === $request->user()->id) {
            $data['active'] = true;
            $data['role'] = 'office';
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user?->exists) {
            $user->update($data);
        } else {
            User::create($data);
        }

        return back()->with('success', 'Đã lưu tài khoản.');
    }

    public function departments(Request $request)
    {
        abort_unless($request->user()->isLeadership(), 403);

        return view('admin.departments', ['departments' => Department::withCount(['users', 'requests'])->get()]);
    }

    public function saveDepartment(Request $request, ?Department $department = null)
    {
        abort_unless($request->user()->isOffice(), 403);
        $data = $request->validate(['name' => 'required|string|max:255', 'code' => ['required', 'regex:/^[A-Z0-9_-]+$/', 'max:20', Rule::unique('departments')->ignore($department?->id)]]);
        if ($department?->exists) {
            $department->update($data);
        } else {
            Department::create($data);
        }

        return back()->with('success', 'Đã lưu đơn vị.');
    }
}
