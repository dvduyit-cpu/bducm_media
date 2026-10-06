<?php

namespace Tests\Feature;

use App\Models\AccessRole;
use App\Models\Department;
use App\Models\MediaRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_tick_reviewer_for_staff_without_changing_role_and_revoke_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = Department::create(['code' => 'A', 'name' => 'Phòng A']);
        $b = Department::create(['code' => 'B', 'name' => 'Phòng B']);
        $reviewer = User::factory()->create(['department_id' => $a->id, 'name' => 'Người kiểm duyệt', 'permissions' => ['requests']]);
        $owner = User::factory()->create(['department_id' => $b->id]);
        $event = MediaRequest::create(['title' => 'Sự kiện cần kiểm duyệt', 'description' => 'Nội dung', 'department_id' => $b->id, 'creator_id' => $owner->id, 'contact_id' => $owner->id, 'status' => 'submitted', 'channel' => 'Website', 'event_at' => now()->addDay()]);
        $payload = ['name' => $reviewer->name, 'email' => $reviewer->email, 'role' => 'staff', 'department_id' => $a->id, 'active' => 1, 'permissions' => ['requests'], 'reviewer_control' => 1];
        $this->actingAs($reviewer)->get('/approvals')->assertForbidden();
        $this->get('/requests/'.$event->id)->assertNotFound();
        $this->actingAs($admin)->put('/users/'.$reviewer->id, [...$payload, 'reviewer_access' => 1])->assertRedirect();
        $this->assertSame('staff', $reviewer->fresh()->role->value);
        $this->assertTrue($reviewer->fresh()->reviewer_access);
        $this->assertSame(1, $reviewer->notifications()->where('data->request_id', $event->id)->count());
        $this->get('/users')->assertOk()->assertViewHas('reviewers', fn ($users) => $users->contains('id', $reviewer->id))->assertSee('Có quyền kiểm duyệt');
        $this->actingAs($reviewer->fresh())->get('/approvals')->assertOk()->assertSee('Sự kiện cần kiểm duyệt');
        $this->get('/requests/'.$event->id)->assertOk()->assertSee('Duyệt sự kiện');
        $this->post('/requests/'.$event->id.'/transition', ['target' => 'approved', 'coordination_mode' => 'autonomous'])->assertRedirect();
        $this->assertDatabaseHas('media_requests', ['id' => $event->id, 'status' => 'approved', 'approved_by' => $reviewer->id]);
        $this->actingAs($admin)->put('/users/'.$reviewer->id, $payload)->assertRedirect();
        $this->assertFalse($reviewer->fresh()->reviewer_access);
        $this->actingAs($reviewer->fresh())->get('/approvals')->assertForbidden();
        $this->get('/requests/'.$event->id)->assertNotFound();
        $this->get('/notifications')->assertOk()->assertDontSee('Sự kiện cần kiểm duyệt');
    }

    public function test_list_review_returns_with_reason_and_allows_resubmission_before_approval(): void
    {
        $unit = Department::create(['code' => 'REVIEW', 'name' => 'Đơn vị gửi sự kiện']);
        $owner = User::factory()->create(['department_id' => $unit->id]);
        $reviewer = User::factory()->create(['reviewer_access' => true]);
        $event = MediaRequest::create(['title' => 'Sự kiện kiểm tra trả lại', 'description' => 'Nội dung', 'department_id' => $unit->id, 'creator_id' => $owner->id, 'contact_id' => $owner->id, 'status' => 'submitted', 'channel' => 'Website', 'event_at' => now()->addDay()]);
        $url = '/requests/'.$event->id.'/transition';
        $this->actingAs($owner)->get('/requests')->assertOk()->assertDontSee('data-modal-trigger="return-event-'.$event->id.'"', false);
        $this->actingAs($reviewer)->get('/requests')->assertOk()->assertSee('data-modal-trigger="approve-event-'.$event->id.'"', false)->assertSee('data-modal-trigger="return-event-'.$event->id.'"', false);
        $this->post($url, ['target' => 'needs_info'])->assertSessionHasErrors('note');
        $this->assertDatabaseHas('media_requests', ['id' => $event->id, 'status' => 'submitted']);
        $this->post($url, ['target' => 'needs_info', 'note' => 'Bổ sung địa điểm cụ thể'])->assertRedirect();
        $this->get('/requests')->assertOk()->assertSee('Trả lại: Bổ sung địa điểm cụ thể')->assertDontSee('data-modal-trigger="approve-event-'.$event->id.'"', false);
        $this->post($url, ['target' => 'approved', 'coordination_mode' => 'autonomous'])->assertForbidden();
        $this->actingAs($owner)->get('/notifications')->assertOk()->assertSee('Sự kiện kiểm tra trả lại');
        $this->post($url, ['target' => 'submitted'])->assertRedirect();
        $this->actingAs($reviewer)->post($url, ['target' => 'approved', 'coordination_mode' => 'autonomous'])->assertRedirect();
        $this->assertDatabaseHas('media_requests', ['id' => $event->id, 'status' => 'approved', 'approved_by' => $reviewer->id]);
        $this->assertSame(['needs_info', 'submitted', 'approved'], $event->histories()->reorder('id')->pluck('to_status')->all());
        $this->post($url, ['target' => 'needs_info', 'note' => 'Thao tác cũ'])->assertForbidden();
        $this->get('/requests')->assertOk()->assertSee('Đã duyệt')->assertDontSee('data-modal-trigger="return-event-'.$event->id.'"', false);
    }

    public function test_non_admin_cannot_grant_itself_review_access_and_admin_stays_protected(): void
    {
        $staff = User::factory()->create(['permissions' => ['requests']]);
        $this->actingAs($staff)->put('/users/'.$staff->id, ['reviewer_access' => 1, 'reviewer_control' => 1])->assertForbidden();
        $this->post('/settings', ['theme_color' => '#2563eb', 'reviewer_access' => 1])->assertRedirect();
        $this->assertFalse($staff->fresh()->reviewer_access);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put('/users/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role' => 'admin', 'active' => 1, 'reviewer_control' => 1, 'reviewer_access' => 0])->assertRedirect();
        $this->assertTrue($admin->fresh()->canReview());
    }

    public function test_review_tick_grants_required_modules_with_custom_role_and_not_admin_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = Department::create(['code' => 'A', 'name' => 'A']);
        $role = AccessRole::factory()->create(['permissions' => ['calendar']]);
        $this->actingAs($admin)->post('/users', ['name' => 'Người duyệt tùy chỉnh', 'email' => 'reviewer@example.test', 'password' => 'ValidPassword123!', 'role' => $role->code, 'department_id' => $unit->id, 'permissions' => ['calendar'], 'active' => 1, 'reviewer_control' => 1, 'reviewer_access' => 1])->assertRedirect();
        $user = User::where('email', 'reviewer@example.test')->firstOrFail();
        $this->assertSame($role->id, $user->access_role_id);
        $this->actingAs($user)->get('/requests')->assertOk();
        $this->get('/approvals')->assertOk();
        $this->get('/reports')->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->get('/roles')->assertForbidden();
    }

    public function test_new_reviewer_receives_submissions_from_other_units_without_duplicate_notifications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = Department::create(['code' => 'A', 'name' => 'A']);
        $b = Department::create(['code' => 'B', 'name' => 'B']);
        $reviewer = User::factory()->create(['department_id' => $a->id, 'reviewer_access' => true]);
        $owner = User::factory()->create(['department_id' => $b->id]);
        $event = MediaRequest::create(['title' => 'Gửi cho người duyệt', 'description' => 'Nội dung', 'department_id' => $b->id, 'creator_id' => $owner->id, 'contact_id' => $owner->id, 'status' => 'draft', 'channel' => 'Website', 'event_at' => now()->addDay()]);
        $this->actingAs($owner)->post('/requests/'.$event->id.'/transition', ['target' => 'submitted'])->assertRedirect();
        $this->assertSame(1, $reviewer->notifications()->where('data->request_id', $event->id)->count());
        $this->actingAs($admin)->put('/users/'.$reviewer->id, ['name' => $reviewer->name, 'email' => $reviewer->email, 'role' => 'staff', 'department_id' => $a->id, 'permissions' => ['requests'], 'active' => 1, 'reviewer_control' => 1, 'reviewer_access' => 1])->assertRedirect();
        $this->assertSame(1, $reviewer->notifications()->where('data->request_id', $event->id)->count());
    }
}
