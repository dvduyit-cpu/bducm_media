<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\MediaRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function event(Department $unit, User $owner): MediaRequest
    {
        return MediaRequest::create(['title' => 'Sự kiện riêng '.$unit->code, 'description' => 'Nội dung', 'department_id' => $unit->id, 'creator_id' => $owner->id, 'contact_id' => $owner->id, 'status' => 'submitted', 'channel' => 'Website', 'event_at' => now()->addDay(), 'due_at' => now()->addDay()]);
    }

    public function test_admin_creates_scoped_accounts_and_permission_changes_apply_immediately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = Department::create(['code' => 'A', 'name' => 'Phòng A']);
        $b = Department::create(['code' => 'B', 'name' => 'Phòng B']);
        $owner = User::factory()->create(['department_id' => $b->id]);
        $otherEvent = $this->event($b, $owner);
        $this->actingAs($admin)->post('/users', ['name' => 'Trưởng A', 'email' => 'head-a@example.test', 'password' => 'ValidPassword123!', 'role' => 'head', 'department_id' => $a->id, 'active' => 1, 'view_all_units' => 1, 'permissions' => ['dashboard', 'requests', 'calendar']])->assertRedirect();
        $head = User::where('email', 'head-a@example.test')->firstOrFail();
        $event = $this->event($a, $head);
        $this->assertFalse($head->view_all_units);
        $this->actingAs($head)->get('/requests/'.$event->id)->assertOk();
        $this->get('/requests/'.$otherEvent->id)->assertNotFound();
        $this->get('/reports')->assertForbidden();
        $this->get('/reports?export=xlsx')->assertForbidden();
        $this->get('/settings')->assertDontSee('href="'.route('reports').'"', false)->assertDontSee('href="'.route('users').'"', false);
        $this->get('/users')->assertForbidden();
        $this->get('/departments')->assertForbidden();
        $this->post('/users', ['name' => 'Self grant'])->assertForbidden();
        $this->actingAs($admin)->put('/users/'.$head->id, ['name' => $head->name, 'email' => $head->email, 'role' => 'head', 'department_id' => $a->id, 'active' => 1, 'permissions' => ['requests', 'reports']])->assertRedirect();
        $this->actingAs($head->fresh())->get('/reports')->assertOk()->assertSee('Phòng A')->assertDontSee('Sự kiện riêng B');
        $this->get('/calendar')->assertForbidden();
        $this->actingAs($admin)->get('/requests/'.$otherEvent->id)->assertOk();
    }

    public function test_ungranted_accounts_land_on_settings_and_cannot_access_modules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = Department::create(['code' => 'A', 'name' => 'A']);
        $this->actingAs($admin)->post('/users', ['name' => 'Chưa cấp quyền', 'email' => 'no-access@example.test', 'password' => 'ValidPassword123!', 'role' => 'staff', 'department_id' => $unit->id, 'active' => 1])->assertRedirect();
        $user = User::where('email', 'no-access@example.test')->firstOrFail();
        $this->assertSame([], $user->permissions);
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'ValidPassword123!'])->assertRedirect('/settings');
        foreach (['/', '/requests', '/calendar', '/approvals', '/reports', '/library', '/users', '/departments'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->get('/help')->assertOk();
        $this->post('/settings', ['theme_color' => '#2563eb', 'permissions' => ['requests'], 'role' => 'admin', 'view_all_units' => true])->assertRedirect();
        $this->get('/requests')->assertForbidden();
        $this->assertSame('staff', $user->fresh()->role->value);
    }

    public function test_office_needs_explicit_approval_and_global_scope_grants(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = Department::create(['code' => 'A', 'name' => 'A']);
        $b = Department::create(['code' => 'B', 'name' => 'B']);
        $office = User::factory()->create(['role' => 'office', 'department_id' => $a->id, 'permissions' => ['requests'], 'view_all_units' => false]);
        $own = $this->event($a, $office);
        $owner = User::factory()->create(['department_id' => $b->id]);
        $other = $this->event($b, $owner);
        $this->actingAs($office)->get('/requests/'.$other->id)->assertNotFound();
        $this->get('/users')->assertForbidden();
        $this->post('/requests/'.$own->id.'/transition', ['target' => 'approved', 'coordination_mode' => 'autonomous'])->assertForbidden();
        $this->actingAs($admin)->put('/users/'.$office->id, ['name' => $office->name, 'email' => $office->email, 'role' => 'office', 'active' => 1, 'view_all_units' => 1, 'permissions' => ['requests', 'approvals']])->assertRedirect();
        $this->assertSame(1, $office->notifications()->where('data->request_id', $other->id)->count());
        $this->actingAs($office->fresh())->get('/approvals')->assertOk()->assertSee('Sự kiện riêng B');
        $this->get('/notifications')->assertOk()->assertSee('Sự kiện riêng B');
        $this->get('/requests/'.$other->id)->assertOk();
        $this->post('/requests/'.$other->id.'/transition', ['target' => 'approved', 'coordination_mode' => 'autonomous'])->assertRedirect();
    }

    public function test_only_one_admin_exists_and_it_cannot_lock_or_demote_itself(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/users', ['name' => 'Another admin', 'email' => 'another@example.test', 'password' => 'ValidPassword123!', 'role' => 'admin', 'active' => 1])->assertSessionHasErrors('role');
        $this->put('/users/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role' => 'staff', 'active' => 0])->assertRedirect();
        $this->assertTrue($admin->fresh()->active);
        $this->assertTrue($admin->fresh()->isAdmin());
        $this->get('/users')->assertOk();
    }

    public function test_account_reset_archives_old_users_preserves_event_history_and_allows_email_reuse(): void
    {
        $unit = Department::create(['code' => 'A', 'name' => 'A']);
        $old = User::factory()->create(['department_id' => $unit->id, 'password' => 'OldPassword123!']);
        $originalEmail = $old->email;
        $event = $this->event($unit, $old);
        $this->artisan('media:create-admin', ['email' => 'admin@example.test', '--reset-accounts' => true])->assertSuccessful();
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->assertEquals(1, User::where('active', true)->count());
        $this->assertFalse($old->fresh()->active);
        $this->assertNotNull($old->fresh()->archived_at);
        $this->assertEquals($originalEmail, $old->fresh()->archived_email);
        $this->assertEquals($old->id, $event->fresh()->creator_id);
        $this->post('/login', ['email' => $originalEmail, 'password' => 'OldPassword123!'])->assertSessionHasErrors('email');
        $this->actingAs($old->fresh())->get('/')->assertRedirect('/login');
        $this->actingAs($admin)->get('/users')->assertOk()->assertSee($old->name)->assertSee($originalEmail)->assertSee('Đã lưu trữ')->assertSee('data-modal-trigger="edit-user-'.$old->id.'"', false)->assertSee('data-modal-trigger="delete-user-'.$old->id.'"', false);
        $this->post('/users', ['name' => 'Tài khoản mới', 'email' => $originalEmail, 'password' => 'NewPassword123!', 'role' => 'staff', 'department_id' => $unit->id, 'active' => 1, 'permissions' => ['requests']])->assertRedirect();
        $this->assertTrue(Hash::check('NewPassword123!', User::where('email', $originalEmail)->firstOrFail()->password));
        $this->put('/users/'.$old->id, [])->assertSessionHasErrors('name');
    }

    public function test_archived_account_can_be_edited_and_reactivated_with_new_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = Department::create(['code' => 'A', 'name' => 'A']);
        $user = User::factory()->create(['department_id' => $unit->id, 'active' => false, 'archived_at' => now()]);
        $data = ['name' => 'Đã sửa', 'email' => 'restored@example.test', 'role' => 'staff', 'department_id' => $unit->id, 'permissions' => ['requests']];
        $this->actingAs($admin)->put('/users/'.$user->id, $data)->assertRedirect();
        $this->assertSame('Đã sửa', $user->fresh()->name);
        $this->assertNotNull($user->fresh()->archived_at);
        $this->put('/users/'.$user->id, [...$data, 'active' => 1])->assertSessionHasErrors('password');
        $this->put('/users/'.$user->id, [...$data, 'active' => 1, 'password' => 'RestoredPassword123!'])->assertRedirect();
        $this->assertNull($user->fresh()->archived_at);
        $this->assertTrue($user->fresh()->active);
        $this->assertTrue(Hash::check('RestoredPassword123!', $user->fresh()->password));
    }

    public function test_deleting_accounts_requires_admin_confirmation_and_preserves_linked_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = Department::create(['code' => 'A', 'name' => 'A']);
        $user = User::factory()->create(['department_id' => $unit->id, 'active' => false, 'archived_at' => now()]);
        $staff = User::factory()->create(['department_id' => $unit->id]);
        $this->actingAs($staff)->delete('/users/'.$user->id, ['confirm_delete' => 1])->assertForbidden();
        $this->actingAs($admin)->delete('/users/'.$user->id)->assertSessionHasErrors('confirm_delete');
        $this->delete('/users/'.$admin->id, ['confirm_delete' => 1])->assertSessionHasErrors('user');
        $this->delete('/users/'.$user->id, ['confirm_delete' => 1])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $event = $this->event($unit, $staff);
        $this->delete('/users/'.$staff->id, ['confirm_delete' => 1])->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('media_requests', ['id' => $event->id]);
    }

    public function test_notifications_do_not_expose_other_units_or_ungranted_events(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = Department::create(['code' => 'A', 'name' => 'A']);
        $b = Department::create(['code' => 'B', 'name' => 'B']);
        $scoped = User::factory()->create(['role' => 'office', 'department_id' => $a->id, 'permissions' => ['requests', 'approvals'], 'view_all_units' => false]);
        $ungranted = User::factory()->create(['role' => 'staff', 'department_id' => $b->id, 'permissions' => []]);
        $owner = User::factory()->create(['role' => 'head', 'department_id' => $b->id, 'permissions' => ['requests']]);
        $event = $this->event($b, $owner);
        $this->actingAs($admin)->post('/requests/'.$event->id.'/transition', ['target' => 'approved', 'coordination_mode' => 'autonomous'])->assertRedirect();
        $this->assertEquals(0, $scoped->notifications()->count());
        $this->assertEquals(0, $ungranted->notifications()->count());
        $this->assertEquals(1, $owner->notifications()->count());
        $this->artisan('media:remind')->assertSuccessful();
        $this->assertEquals(0, $scoped->notifications()->count());
        $this->assertEquals(0, $ungranted->notifications()->count());
    }
}
