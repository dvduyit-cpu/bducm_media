<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\MediaRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_and_search_departments_by_name_or_code(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Department::create(['code' => 'OTHER', 'name' => 'Phòng khác']);
        $this->actingAs($admin)->post('/departments', ['code' => 'DT', 'name' => 'Đào tạo'])->assertRedirect();
        $unit = Department::where('code', 'DT')->firstOrFail();
        $this->put('/departments/'.$unit->id, ['code' => 'CTSV', 'name' => 'Công tác sinh viên'])->assertRedirect();
        foreach (['CTSV', 'sinh viên'] as $term) {
            $this->get('/departments?q='.urlencode($term))->assertOk()->assertViewHas('departments', fn ($items) => $items->total() === 1 && $items->first()->id === $unit->id)->assertSee('Công tác sinh viên')->assertDontSee('Phòng khác');
        }
        $this->get('/departments?q=not-found')->assertOk()->assertSee('Không tìm thấy phòng ban phù hợp.');
        $this->post('/departments', ['code' => 'CTSV', 'name' => 'Trùng mã'])->assertSessionHasErrors('code');
        $this->get('/departments')->assertOk()->assertSee('data-modal-trigger="create-department"', false);
    }

    public function test_empty_department_requires_confirmation_before_deletion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = Department::create(['code' => 'EMPTY', 'name' => 'Phòng trống']);
        $this->actingAs($admin)->delete('/departments/'.$unit->id)->assertSessionHasErrors('confirm_delete');
        $this->assertDatabaseHas('departments', ['id' => $unit->id]);
        $this->delete('/departments/'.$unit->id, ['confirm_delete' => 1])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('departments', ['id' => $unit->id]);
    }

    public function test_departments_referenced_by_users_host_events_or_support_events_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $accountUnit = Department::create(['code' => 'AC', 'name' => 'Có tài khoản cũ']);
        $owner = User::factory()->create(['department_id' => $accountUnit->id, 'active' => false, 'archived_at' => now()]);
        $host = Department::create(['code' => 'HOST', 'name' => 'Đơn vị chủ trì']);
        $support = Department::create(['code' => 'SUP', 'name' => 'Đơn vị hỗ trợ']);
        $event = MediaRequest::create(['title' => 'Sự kiện', 'description' => 'Nội dung', 'department_id' => $host->id, 'creator_id' => $owner->id, 'contact_id' => $owner->id, 'status' => 'completed', 'channel' => 'Website', 'event_at' => now()]);
        $event->supportDepartments()->attach($support);
        foreach ([$accountUnit, $host, $support] as $unit) {
            $this->actingAs($admin)->delete('/departments/'.$unit->id, ['confirm_delete' => 1])->assertSessionHasErrors('department');
            $this->assertDatabaseHas('departments', ['id' => $unit->id]);
        }
        $this->assertDatabaseHas('media_requests', ['id' => $event->id]);
        $this->assertDatabaseHas('department_media_request', ['department_id' => $support->id]);
    }

    public function test_non_admins_cannot_search_edit_or_delete_departments_by_direct_url(): void
    {
        $unit = Department::create(['code' => 'A', 'name' => 'A']);
        $this->delete('/departments/'.$unit->id, ['confirm_delete' => 1])->assertRedirect('/login');
        foreach (['office', 'director', 'head', 'staff'] as $role) {
            $user = User::factory()->create(['role' => $role, 'department_id' => $unit->id]);
            $this->actingAs($user)->get('/departments?q=A')->assertForbidden();
            $this->put('/departments/'.$unit->id, ['code' => 'CHANGED', 'name' => 'Changed'])->assertForbidden();
            $this->delete('/departments/'.$unit->id, ['confirm_delete' => 1])->assertForbidden();
        }
        $this->assertDatabaseHas('departments', ['id' => $unit->id, 'code' => 'A']);
    }
}
