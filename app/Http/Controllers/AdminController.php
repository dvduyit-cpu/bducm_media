<?php

namespace App\Http\Controllers;

use App\Models\AccessRole;
use App\Models\Department;
use App\Models\MediaRequest;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\RequestUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function settings(Request $request)
    {
        return view('settings', ['settings' => Setting::pluck('value', 'key')]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate(['theme_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'font_family' => ['sometimes', 'required', Rule::in(array_keys(User::fontOptions()))], 'font_size' => 'sometimes|required|integer|min:14|max:20', 'loading_style' => ['sometimes', 'required', Rule::in(['center', 'top'])], 'organization_name' => 'nullable|string|max:120',
            'default_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 'deadline_day' => 'nullable|integer|min:1|max:7', 'deadline_time' => 'nullable|date_format:H:i', 'board' => 'nullable|array:background,columns', 'board.background' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 'board.columns' => 'nullable|array:draft,submitted,approved,in_progress,completed', 'board.columns.*' => 'array:title,color', 'board.columns.*.title' => 'required|string|max:40', 'board.columns.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        $request->user()->update(['theme_color' => $data['theme_color'], ...array_intersect_key($data, array_flip(['font_family', 'font_size', 'loading_style'])), ...(isset($data['board']) ? ['board_preferences' => $data['board']] : [])]);
        if ($request->user()->isAdmin()) {
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
        abort_unless($request->user()->isAdmin(), 403);

        return view('admin.users', ['users' => User::with(['department', 'accessRole'])->orderByRaw('archived_at IS NOT NULL')->orderBy('name')->paginate(20), 'departments' => Department::all(), 'roles' => AccessRole::orderBy('name')->get(), 'permissionOptions' => User::permissionOptions(), 'reviewers' => User::with(['department', 'accessRole'])->where('active', true)->whereNull('archived_at')->orderBy('name')->get()->filter(fn (User $account) => $account->canReview())]);
    }

    public function saveUser(Request $request, ?User $user = null)
    {
        abort_unless($request->user()->isAdmin(), 403);
        if ($user?->id === $request->user()->id) {
            $request->merge(['role' => 'admin', 'active' => 1]);
        }
        $roleCode = $request->input('role');
        $roleDefinition = is_string($roleCode) ? AccessRole::where('code', $roleCode)->first() : null;
        $baseRole = $roleDefinition?->base_role->value;
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'password' => [$user?->exists ? 'nullable' : 'required', 'string', 'min:10', 'max:128'], 'role' => ['required', 'string', Rule::exists('access_roles', 'code'), Rule::unique('users', 'role')->where('role', 'admin')->ignore($user?->id)],
            'department_id' => [Rule::requiredIf(in_array($baseRole, ['head', 'staff']) || (in_array($baseRole, ['office', 'director']) && ! $request->boolean('view_all_units'))), 'nullable', 'exists:departments,id'], 'active' => 'nullable|boolean', 'reviewer_access' => 'nullable|boolean', 'reviewer_control' => 'nullable|boolean', 'view_all_units' => 'nullable|boolean', 'permissions' => 'nullable|array', 'permissions.*' => ['required', Rule::in(array_keys(User::permissionOptions()))]]);
        $data['access_role_id'] = $roleDefinition->id;
        $data['role'] = $baseRole;
        $data['active'] = $request->boolean('active');
        $controlled = $request->boolean('reviewer_control');
        $data['reviewer_access'] = $controlled ? $request->boolean('reviewer_access') : ($user?->reviewer_access ?? false);
        unset($data['reviewer_control']);
        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));
        if ($controlled) {
            $data['permissions'] = array_values(array_diff($data['permissions'], ['approvals']));
        }
        if ($data['reviewer_access']) {
            $data['permissions'] = array_values(array_unique([...$data['permissions'], 'requests', 'approvals']));
        }
        $allowed = $data['reviewer_access'] ? array_unique([...$roleDefinition->assignablePermissions(), 'requests', 'approvals']) : $roleDefinition->assignablePermissions();
        if (! $roleDefinition->isAdmin() && array_diff($data['permissions'], $allowed)) {
            throw ValidationException::withMessages(['permissions' => 'Có chức năng chưa được cho phép trong vai trò đã chọn.']);
        }
        if (! $data['reviewer_access'] && in_array('approvals', $data['permissions'], true) && (! in_array($data['role'], ['office', 'admin']) || ! in_array('requests', $data['permissions'], true))) {
            throw ValidationException::withMessages(['permissions' => 'Quyền duyệt chỉ dành cho VP BGĐ và cần chọn thêm Sự kiện phòng ban.']);
        }
        $data['view_all_units'] = in_array($data['role'], ['office', 'director']) && $request->boolean('view_all_units');
        if ($user?->id === $request->user()->id) {
            $data['active'] = true;
            $data['role'] = 'admin';
        }
        if ($user?->archived_at && $data['active']) {
            if (empty($data['password'])) {
                throw ValidationException::withMessages(['password' => 'Nhập mật khẩu mới để kích hoạt lại tài khoản đã lưu trữ.']);
            }
            $data['archived_at'] = null;
            $data['archived_email'] = null;
        } elseif ($user?->archived_at) {
            $data['archived_email'] = $data['email'];
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user?->exists) {
            if ($user->registration_pending && $data['active']) {
                $data['registration_pending'] = false;
            }
            $user->update($data);
            $user->unsetRelation('accessRole');
            if (isset($data['password']) || ! $data['active']) {
                $user->forceFill(['remember_token' => null])->save();
                DB::table('sessions')->where('user_id', $user->id)->when($user->id === $request->user()->id, fn ($query) => $query->where('id', '!=', $request->session()->getId()))->delete();
            }
        } else {
            $user = User::create($data);
        }

        if ($user->active && $user->canReview() && $user->isLeadership() && $user->canAccess('requests') && $user->canAccess('approvals')) {
            foreach (MediaRequest::visibleTo($user)->where('status', 'submitted')->get() as $pending) {
                if (! $user->notifications()->where('data->request_id', $pending->id)->exists()) {
                    $user->notify(new RequestUpdated($pending, 'Có sự kiện đã gửi đang chờ kiểm duyệt.'));
                }
            }
        }

        return back()->with('success', 'Đã lưu tài khoản.');
    }

    public function deleteUser(Request $request, User $user)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $request->validate(['confirm_delete' => 'accepted']);
        DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->isAdmin()) {
                throw ValidationException::withMessages(['user' => 'Không thể xóa admin tổng.']);
            }
            $events = DB::table('media_requests')->where(function ($query) use ($user) {
                foreach (['creator_id', 'contact_id', 'assignee_id', 'professional_checked_by', 'media_assignee_id', 'approved_by'] as $column) {
                    $query->orWhere($column, $user->id);
                }
            })->count();
            $history = DB::table('request_histories')->where('user_id', $user->id)->count();
            $files = DB::table('attachments')->where('user_id', $user->id)->count();
            $links = DB::table('request_links')->where('user_id', $user->id)->count();
            if ($events + $history + $files + $links > 0) {
                throw ValidationException::withMessages(['user' => "Chưa thể xóa: tài khoản liên kết với {$events} sự kiện, {$history} lịch sử xử lý, {$files} tài liệu và {$links} liên kết. Có thể sửa phòng ban hoặc khóa tài khoản; cần xử lý các liên kết trước khi xóa."]);
            }
            $user->notifications()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        return back()->with('success', 'Đã xóa tài khoản.');
    }

    public function departments(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate(['q' => 'nullable|string|max:200']);
        $search = trim($data['q'] ?? '');

        return view('admin.departments', ['search' => $search, 'departments' => Department::withCount(['users', 'users as archived_users_count' => fn ($query) => $query->whereNotNull('archived_at'), 'requests', 'supportedRequests'])->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')))->orderBy('name')->paginate(12)->withQueryString()]);
    }

    public function saveDepartment(Request $request, ?Department $department = null)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['name' => 'required|string|max:255', 'code' => ['required', 'regex:/^[A-Z0-9_-]+$/', 'max:20', Rule::unique('departments')->ignore($department?->id)]]);
        if ($department?->exists) {
            $department->update($data);
        } else {
            Department::create($data);
        }

        return back()->with('success', 'Đã lưu đơn vị.');
    }

    public function deleteDepartment(Request $request, Department $department)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $request->validate(['confirm_delete' => 'accepted']);
        DB::transaction(function () use ($department) {
            $department = Department::whereKey($department->id)->lockForUpdate()->firstOrFail();
            if ($department->users()->exists() || $department->requests()->exists() || $department->supportedRequests()->exists()) {
                throw ValidationException::withMessages(['department' => 'Không thể xóa phòng ban đang liên kết với tài khoản hoặc sự kiện (kể cả dữ liệu đã lưu trữ).']);
            }
            $department->delete();
        });

        return back()->with('success', 'Đã xóa phòng ban.');
    }
}
