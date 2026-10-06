<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\MediaRequest;
use App\Models\User;
use App\Notifications\RequestUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private function event(Department $department, User $owner, string $status = 'submitted'): MediaRequest
    {
        return MediaRequest::create(['title' => 'Sự kiện nội bộ', 'description' => 'Thông tin nội bộ', 'department_id' => $department->id, 'creator_id' => $owner->id, 'contact_id' => $owner->id,
            'status' => $status, 'channel' => 'Website', 'event_at' => now()->addDay()]);
    }

    public function test_all_business_routes_require_login_including_downloads_exports_and_writes(): void
    {
        foreach (['/', '/requests', '/requests/create', '/requests/1', '/requests/1/edit', '/attachments/1', '/calendar', '/calendar?export=xlsx', '/approvals', '/reports', '/reports?export=pdf', '/library', '/notifications', '/settings', '/help', '/users', '/roles', '/departments'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        foreach (['/requests', '/requests/1/transition', '/requests/1/coordinate', '/requests/1/progress', '/requests/1/links', '/requests/1/attachments', '/settings', '/users', '/roles', '/departments', '/logout'] as $url) {
            $this->post($url)->assertRedirect('/login');
        }
        foreach (['/requests/1', '/users/1', '/roles/1', '/departments/1'] as $url) {
            $this->put($url)->assertRedirect('/login');
        }
        foreach (['/users/1', '/roles/1', '/departments/1'] as $url) {
            $this->delete($url)->assertRedirect('/login');
        }
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
        $this->post('/_boost/browser-logs')->assertNotFound();
    }

    public function test_pages_and_denied_responses_have_security_headers_and_do_not_cache_private_data(): void
    {
        $this->get('/login')->assertOk()->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $user = User::factory()->create(['permissions' => []]);
        $response = $this->actingAs($user)->get('/roles')->assertForbidden()->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("script-src 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_production_redirects_http_to_https_and_secure_pages_send_hsts(): void
    {
        $this->app->instance('env', 'production');
        $this->get('http://localhost/login')->assertStatus(308)->assertRedirect('https://localhost/login');
        $this->get('https://localhost/login')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_csrf_is_required_for_login_and_administrative_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'ValidPassword123!']);
        $this->app->instance('env', 'local');
        $this->post('/login', ['email' => $admin->email, 'password' => 'ValidPassword123!'])->assertStatus(419);
        $this->assertGuest();
        $this->actingAs($admin)->post('/departments', ['name' => 'Forged unit', 'code' => 'FORGED'])->assertStatus(419);
        $this->assertDatabaseMissing('departments', ['code' => 'FORGED']);
    }

    public function test_permissions_and_global_scope_are_denied_when_not_explicitly_granted(): void
    {
        $user = User::factory()->create(['role' => 'office', 'permissions' => null, 'view_all_units' => null]);
        $this->assertFalse($user->isLeadership());
        $this->actingAs($user)->get('/requests')->assertForbidden();
        $this->get('/reports')->assertForbidden();
        $this->get('/approvals')->assertForbidden();
        $this->get('/help')->assertOk();
        $user->permissions = ['requests'];
        $user->access_role_id = null;
        $user->role = 'staff';
        $user->save();
        DB::table('access_roles')->where('code', 'staff')->delete();
        $this->actingAs($user->fresh())->get('/requests')->assertForbidden();
    }

    public function test_archived_accounts_cannot_login_even_if_active_flag_is_set(): void
    {
        $user = User::factory()->create(['active' => true, 'archived_at' => now(), 'password' => 'ValidPassword123!']);
        $this->post('/login', ['email' => $user->email, 'password' => 'ValidPassword123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get('/help')->assertRedirect('/login');
    }

    public function test_media_cannot_read_other_people_drafts_or_see_unrelated_staff_in_filters(): void
    {
        $unit = Department::create(['code' => 'A', 'name' => 'A']);
        $other = Department::create(['code' => 'B', 'name' => 'B']);
        $owner = User::factory()->create(['department_id' => $unit->id]);
        $media = User::factory()->create(['role' => 'media', 'department_id' => $unit->id]);
        $outsider = User::factory()->create(['role' => 'media', 'department_id' => $other->id, 'name' => 'Nhân sự bí mật']);
        $draft = $this->event($unit, $owner, 'draft');
        $draft->update(['assignee_id' => $media->id]);
        $this->actingAs($media)->get('/requests/'.$draft->id)->assertNotFound();
        $this->get('/requests')->assertOk()->assertDontSee('Nhân sự bí mật');
        $draft->update(['status' => 'submitted']);
        $this->get('/requests/'.$draft->id)->assertOk();
        $withoutUnit = User::factory()->create(['role' => 'media']);
        $anotherWithoutUnit = User::factory()->create(['role' => 'media', 'name' => 'Nhân sự chưa có đơn vị']);
        $this->actingAs($withoutUnit)->get('/requests')->assertOk()->assertDontSee('Nhân sự chưa có đơn vị');
    }

    public function test_upload_rejects_disguised_executable_extension_and_downloads_are_private(): void
    {
        Storage::fake('local');
        $unit = Department::create(['code' => 'A', 'name' => 'A']);
        $owner = User::factory()->create(['department_id' => $unit->id]);
        $event = $this->event($unit, $owner, 'draft');
        $this->actingAs($owner)->post('/requests/'.$event->id.'/attachments', ['kind' => 'source', 'file' => UploadedFile::fake()->create('payload.php', 1, 'text/plain')])->assertSessionHasErrors('file');
        $this->assertSame(0, $event->attachments()->count());
        $this->post('/requests/'.$event->id.'/attachments', ['kind' => 'source', 'file' => UploadedFile::fake()->createWithContent('notes.txt', 'Nội dung tài liệu')])->assertRedirect();
        $attachment = $event->attachments()->firstOrFail();
        $this->get('/attachments/'.$attachment->id)->assertOk()->assertDownload('notes.txt')->assertHeader('Content-Type', 'application/octet-stream')->assertHeader('X-Content-Type-Options', 'nosniff');
        $attachment->update(['path' => '../../../../../.env']);
        $this->get('/attachments/'.$attachment->id)->assertNotFound();
    }

    public function test_passwords_are_hashed_and_admin_reset_revokes_old_sessions_and_remember_token(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = Department::create(['code' => 'A', 'name' => 'A']);
        $user = User::factory()->create(['department_id' => $unit->id]);
        DB::table('sessions')->insert(['id' => 'old-user-session', 'user_id' => $user->id, 'payload' => base64_encode('{}'), 'last_activity' => time()]);
        $this->actingAs($admin)->put('/users/'.$user->id, ['name' => $user->name, 'email' => $user->email, 'role' => 'staff', 'department_id' => $unit->id, 'password' => 'NewPassword123!', 'permissions' => ['requests'], 'active' => 1])->assertRedirect();
        $this->assertNotSame('NewPassword123!', $user->fresh()->password);
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertNull($user->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-user-session']);
    }

    public function test_database_session_payload_is_encrypted_and_cookies_are_http_only(): void
    {
        config(['session.driver' => 'database', 'session.encrypt' => true]);
        $this->app->forgetInstance('session');
        $this->app->forgetInstance('session.store');
        $this->get('/login')->assertOk();
        $session = DB::table('sessions')->first();
        $this->assertNotNull($session);
        $raw = base64_decode($session->payload);
        $this->assertStringNotContainsString('"_token"', $raw);
        $decoded = json_decode(Crypt::decrypt($raw), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('_token', $decoded);
        $this->assertTrue(config('session.http_only'));
    }

    public function test_old_notifications_are_hidden_after_permissions_or_unit_scope_are_revoked(): void
    {
        $unit = Department::create(['code' => 'A', 'name' => 'A']);
        $other = Department::create(['code' => 'B', 'name' => 'B']);
        $user = User::factory()->create(['department_id' => $unit->id, 'permissions' => ['requests']]);
        $event = $this->event($unit, $user);
        $event->update(['title' => 'Tên sự kiện nhạy cảm']);
        $user->notify(new RequestUpdated($event, 'Cập nhật riêng'));
        $this->actingAs($user)->get('/notifications')->assertOk()->assertSee('Tên sự kiện nhạy cảm');
        $user->update(['department_id' => $other->id]);
        $this->actingAs($user->fresh())->get('/notifications')->assertOk()->assertDontSee('Tên sự kiện nhạy cảm');
        $user->update(['department_id' => $unit->id, 'permissions' => []]);
        $this->actingAs($user->fresh())->get('/notifications')->assertOk()->assertDontSee('Tên sự kiện nhạy cảm');
    }

    public function test_xss_and_sql_filter_payloads_do_not_execute_or_bypass_unit_scope(): void
    {
        $unit = Department::create(['code' => 'A', 'name' => 'A']);
        $owner = User::factory()->create(['department_id' => $unit->id]);
        $event = $this->event($unit, $owner);
        $event->update(['title' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(1)>']);
        $this->actingAs($owner)->get('/requests/'.$event->id)->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
        $this->get('/requests?q='.urlencode("' OR 1=1 --"))->assertOk()->assertViewHas('items', fn ($items) => $items->total() === 0);
        $this->post('/settings', ['theme_color' => '#2563eb', 'role' => 'admin', 'permissions' => ['approvals'], 'access_role_id' => 1, 'view_all_units' => true])->assertRedirect();
        $this->assertSame('staff', $owner->fresh()->role->value);
        $this->assertFalse($owner->fresh()->view_all_units);
    }
}
