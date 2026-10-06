<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AccessRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccessRoleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['q' => 'nullable|string|max:120']);
        $search = trim($data['q'] ?? '');
        $roles = AccessRole::withCount('users')->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')))->orderByRaw("code = 'admin' DESC")->orderBy('name')->paginate(12)->withQueryString();

        return view('admin.roles', ['roles' => $roles, 'search' => $search, 'baseRoles' => array_filter(Role::cases(), fn ($role) => $role !== Role::Admin), 'permissionOptions' => User::permissionOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['code' => ['required', 'regex:/^[a-z][a-z0-9_-]*$/', 'max:40', Rule::unique('access_roles'), Rule::notIn(array_column(Role::cases(), 'value'))],
            'name' => 'required|string|max:120', 'base_role' => ['required', Rule::in(['director', 'office', 'head', 'staff', 'media'])],
            'permissions' => 'nullable|array', 'permissions.*' => ['required', Rule::in(array_keys(User::permissionOptions()))]]);
        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));
        $this->validatePermissions($data['base_role'], $data['permissions']);
        AccessRole::create($data);

        return back()->with('success', 'Đã tạo vai trò. Bạn có thể chọn vai trò này khi cấp tài khoản.');
    }

    public function update(Request $request, AccessRole $accessRole): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_if($accessRole->isAdmin(), 403, 'Admin được bảo vệ.');
        $data = $request->validate(['name' => 'required|string|max:120', 'permissions' => 'nullable|array', 'permissions.*' => ['required', Rule::in(array_keys(User::permissionOptions()))]]);
        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));
        $this->validatePermissions($accessRole->base_role->value, $data['permissions']);
        $accessRole->update($data);

        return back()->with('success', 'Đã cập nhật vai trò. Quyền chức năng mới áp dụng cho các tài khoản thuộc vai trò này.');
    }

    public function destroy(Request $request, AccessRole $accessRole): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $request->validate(['confirm_delete' => 'accepted']);
        DB::transaction(function () use ($accessRole) {
            $role = AccessRole::whereKey($accessRole->id)->lockForUpdate()->firstOrFail();
            if ($role->isAdmin()) {
                throw ValidationException::withMessages(['role' => 'Không thể xóa vai trò Admin.']);
            }
            if (User::where('access_role_id', $role->id)->orWhere(fn ($query) => $query->whereNull('access_role_id')->where('role', $role->code))->exists()) {
                throw ValidationException::withMessages(['role' => 'Vai trò còn tài khoản sử dụng. Hãy đổi vai trò của các tài khoản đó trước khi xóa.']);
            }
            $role->delete();
        });

        return back()->with('success', 'Đã xóa vai trò.');
    }

    private function validatePermissions(string $baseRole, array $permissions): void
    {
        if (in_array('approvals', $permissions, true) && ($baseRole !== 'office' || ! in_array('requests', $permissions, true))) {
            throw ValidationException::withMessages(['permissions' => 'Quyền duyệt chỉ dành cho nhóm Văn phòng BGĐ và phải có thêm quyền Sự kiện phòng ban.']);
        }
    }
}
