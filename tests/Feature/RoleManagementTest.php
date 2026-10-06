<?php

namespace Tests\Feature;

use App\Models\AccessRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_search_rename_and_delete_an_unused_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->from('/roles')->post('/roles', ['code' => 'tuyen_sinh', 'name' => 'Chuyên viên tuyển sinh', 'base_role' => 'staff', 'permissions' => ['requests', 'calendar']])->assertRedirect('/roles');
        $role = AccessRole::where('code', 'tuyen_sinh')->firstOrFail();
        $this->assertSame(['requests', 'calendar'], $role->permissions);
        $this->get('/users')->assertOk()->assertSee('Chuyên viên tuyển sinh');
        $this->get('/roles?q=tuyen_sinh')->assertOk()->assertViewHas('roles', fn ($roles) => $roles->total() === 1)->assertSee('Chuyên viên tuyển sinh');
        $this->put('/roles/'.$role->id, ['name' => 'Nhân sự tuyển sinh', 'permissions' => ['requests'], 'base_role' => 'admin', 'code' => 'admin'])->assertRedirect();
        $this->assertDatabaseHas('access_roles', ['id' => $role->id, 'name' => 'Nhân sự tuyển sinh', 'code' => 'tuyen_sinh', 'base_role' => 'staff']);
        $this->delete('/roles/'.$role->id)->assertSessionHasErrors('confirm_delete');
        $this->delete('/roles/'.$role->id, ['confirm_delete' => 1])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('access_roles', ['id' => $role->id]);
    }

    public function test_custom_role_assignments_keep_workflow_and_unit_scope_and_revocations_apply_immediately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = Department::create(['name' => 'Tuyển sinh', 'code' => 'TS']);
        $role = AccessRole::factory()->create(['name' => 'Chuyên viên tuyển sinh', 'code' => 'tuyen_sinh', 'permissions' => ['requests', 'calendar']]);
        $this->actingAs($admin)->post('/users', ['name' => 'Nhân viên', 'email' => 'ts@example.test', 'password' => 'ValidPassword123!', 'role' => 'tuyen_sinh', 'department_id' => $unit->id, 'permissions' => ['requests', 'calendar'], 'view_all_units' => 1, 'active' => 1])->assertRedirect();
        $user = User::where('email', 'ts@example.test')->firstOrFail();
        $this->assertSame($role->id, $user->access_role_id);
        $this->assertSame('staff', $user->role->value);
        $this->assertFalse($user->view_all_units);
        $this->actingAs($user)->get('/requests/create')->assertOk()->assertSee('Chuyên viên tuyển sinh');
        $this->get('/calendar')->assertOk();
        $this->get('/reports')->assertForbidden();
        $this->actingAs($admin)->put('/roles/'.$role->id, ['name' => 'Nhân sự đơn vị', 'permissions' => ['requests']])->assertRedirect();
        $this->actingAs($user->fresh())->get('/calendar')->assertForbidden();
        $this->get('/requests')->assertOk()->assertSee('Nhân sự đơn vị');
        $this->actingAs($admin)->delete('/roles/'.$role->id, ['confirm_delete' => 1])->assertSessionHasErrors('role');
        $this->assertDatabaseHas('access_roles', ['id' => $role->id]);
        $this->put('/users/'.$user->id, ['name' => $user->name, 'email' => $user->email, 'role' => 'staff', 'department_id' => $unit->id, 'permissions' => ['requests'], 'active' => 1])->assertRedirect();
        $this->delete('/roles/'.$role->id, ['confirm_delete' => 1])->assertRedirect();
        $this->assertDatabaseMissing('access_roles', ['id' => $role->id]);
    }

    public function test_role_management_denies_guests_and_every_non_admin_group(): void
    {
        $role = AccessRole::factory()->create();
        $this->get('/roles')->assertRedirect('/login');
        foreach (['office', 'director', 'head', 'staff', 'media'] as $group) {
            $user = User::factory()->create(['role' => $group]);
            $this->actingAs($user)->get('/roles')->assertForbidden();
            $this->post('/roles', ['code' => 'unauthorized'])->assertForbidden();
            $this->put('/roles/'.$role->id, ['name' => 'Changed'])->assertForbidden();
            $this->delete('/roles/'.$role->id, ['confirm_delete' => 1])->assertForbidden();
        }
        $this->assertDatabaseHas('access_roles', ['id' => $role->id, 'name' => $role->name]);
    }

    public function test_admin_role_is_protected_and_invalid_permissions_cannot_be_granted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = AccessRole::where('code', 'admin')->firstOrFail();
        $this->actingAs($admin)->put('/roles/'.$role->id, ['name' => 'Changed', 'permissions' => []])->assertForbidden();
        $this->delete('/roles/'.$role->id, ['confirm_delete' => 1])->assertSessionHasErrors('role');
        $this->post('/roles', ['name' => 'Admin clone', 'code' => 'admin_clone', 'base_role' => 'admin'])->assertSessionHasErrors('base_role');
        $this->post('/roles', ['name' => 'Reserved', 'code' => 'staff', 'base_role' => 'staff'])->assertSessionHasErrors('code');
        $this->post('/roles', ['name' => 'Wrong approval', 'code' => 'wrong_approval', 'base_role' => 'staff', 'permissions' => ['approvals', 'requests']])->assertSessionHasErrors('permissions');
        $this->post('/roles', ['name' => 'Missing prerequisite', 'code' => 'missing', 'base_role' => 'office', 'permissions' => ['approvals']])->assertSessionHasErrors('permissions');
        $unit = Department::create(['code' => 'TS', 'name' => 'Tuyển sinh']);
        $restricted = AccessRole::factory()->create(['permissions' => ['requests']]);
        $this->post('/users', ['name' => 'Bad grant', 'email' => 'bad@example.test', 'password' => 'ValidPassword123!', 'role' => $restricted->code, 'department_id' => $unit->id, 'permissions' => ['reports']])->assertSessionHasErrors('permissions');
        $this->assertDatabaseMissing('users', ['email' => 'bad@example.test']);
        $this->post('/users', ['role' => ['staff']])->assertSessionHasErrors('role');
        $this->post('/users', ['role' => 'missing-role'])->assertSessionHasErrors('role');
        $this->assertSame('Admin', $role->fresh()->name);
    }

    public function test_custom_office_role_requires_explicit_global_scope_and_can_approve(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = Department::create(['code' => 'VP', 'name' => 'Văn phòng']);
        $role = AccessRole::factory()->create(['base_role' => 'office', 'permissions' => ['requests', 'approvals']]);
        $this->actingAs($admin)->post('/users', ['name' => 'Điều phối', 'email' => 'office@example.test', 'password' => 'ValidPassword123!', 'role' => $role->code, 'department_id' => $unit->id, 'permissions' => ['requests', 'approvals'], 'active' => 1])->assertRedirect();
        $user = User::where('email', 'office@example.test')->firstOrFail();
        $this->assertTrue($user->isOffice());
        $this->assertFalse($user->isLeadership());
        $this->actingAs($user)->get('/approvals')->assertOk();
        $this->get('/users')->assertForbidden();
    }

    public function test_role_names_are_escaped_in_cards_and_account_selectors(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        AccessRole::factory()->create(['name' => '<script>alert(1)</script>']);
        $this->actingAs($admin)->get('/roles')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/users')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
