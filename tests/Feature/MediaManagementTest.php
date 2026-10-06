<?php

namespace Tests\Feature;

use App\Enums\RequestStatus as S;
use App\Models\Department;
use App\Models\MediaRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaManagementTest extends TestCase
{
    use RefreshDatabase;

    private Department $unit;

    private User $admin;

    private User $office;

    private User $director;

    private User $head;

    private User $staff;

    private User $media;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->unit = Department::create(['name' => 'Đào tạo', 'code' => 'DT']);
        foreach (['office', 'director', 'head', 'staff', 'media'] as $role) {
            $this->{$role} = User::factory()->create(['role' => $role, 'department_id' => in_array($role, ['head', 'staff']) ? $this->unit->id : null]);
        }
    }

    private function item(array $extra = []): MediaRequest
    {
        return MediaRequest::create(array_merge(['title' => 'Thông báo tuyển sinh', 'description' => 'Nội dung cần truyền thông', 'department_id' => $this->unit->id,
            'creator_id' => $this->staff->id, 'contact_id' => $this->head->id, 'status' => 'draft', 'priority' => 'normal', 'channel' => 'Website',
            'event_at' => now()->addDays(4), 'publish_at' => now()->addDays(3), 'due_at' => now()->addDays(2)], $extra));
    }

    private function move(MediaRequest $item, User $user, string $target, array $extra = [])
    {
        return $this->actingAs($user)->post(route('requests.transition', $item), ['target' => $target, 'note' => 'Ghi chú phối hợp', ...$extra]);
    }

    public function test_guests_must_login_and_inactive_users_are_logged_out(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->staff->update(['active' => false]);
        $this->actingAs($this->staff)->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_all_pages_render_for_admin(): void
    {
        $item = $this->item();
        foreach (['/', '/requests', '/requests/create', '/requests/'.$item->id, '/requests/'.$item->id.'/edit', '/calendar', '/approvals', '/reports', '/library', '/notifications', '/help', '/settings', '/users', '/departments'] as $path) {
            $this->actingAs($this->admin)->get($path)->assertOk();
        }
        $this->get('/reports?export=1')->assertOk()->assertDownload();
    }

    public function test_other_unit_and_unassigned_media_cannot_view_or_download(): void
    {
        Storage::fake('local');
        $item = $this->item(['status' => 'submitted']);
        $other = Department::create(['name' => 'Tuyển sinh', 'code' => 'TS']);
        $outsider = User::factory()->create(['role' => 'head', 'department_id' => $other->id]);
        Storage::disk('local')->put('secret.txt', 'private');
        $file = $item->attachments()->create(['user_id' => $this->staff->id, 'name' => 'secret.txt', 'path' => 'secret.txt', 'size' => 7, 'kind' => 'source']);
        foreach ([$outsider, $this->media] as $user) {
            $this->actingAs($user)->get(route('requests.show', $item))->assertNotFound();
            $this->get(route('attachments.download', $file))->assertNotFound();
            $this->get('/reports?export=1')->assertOk()->assertDontSee('Đào tạo');
        }
        $this->actingAs($this->head)->get(route('attachments.download', $file))->assertOk();
    }

    public function test_staff_creation_is_scoped_and_new_request_stays_draft(): void
    {
        $payload = ['title' => 'Yêu cầu mới', 'description' => 'Nội dung đầy đủ', 'department_id' => $this->unit->id, 'contact_id' => $this->head->id,
            'event_at' => now()->addDays(4)->toDateTimeString(), 'publish_at' => now()->addDays(3)->toDateTimeString(), 'due_at' => now()->addDays(2)->toDateTimeString(), 'channel' => 'Website', 'priority' => 'high'];
        $this->actingAs($this->staff)->post('/requests', $payload)->assertRedirect();
        $this->assertDatabaseHas('media_requests', ['title' => 'Yêu cầu mới', 'status' => 'draft', 'creator_id' => $this->staff->id]);
        $other = Department::create(['name' => 'Khác', 'code' => 'KHAC']);
        $otherHead = User::factory()->create(['role' => 'head', 'department_id' => $other->id]);
        $this->post('/requests', array_replace($payload, ['department_id' => $other->id, 'contact_id' => $otherHead->id]))->assertForbidden();
    }

    public function test_full_workflow_goes_directly_to_office_and_only_office_can_close(): void
    {
        $this->freezeTime();
        $item = $this->item();
        $this->move($item, $this->staff, 'submitted')->assertRedirect()->assertSessionHasNoErrors();
        $this->move($item, $this->head, 'approved', ['coordination_mode' => 'autonomous'])->assertForbidden();
        $this->move($item, $this->office, 'approved', ['coordination_mode' => 'autonomous'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($this->office->id, $item->fresh()->approved_by);
        $this->move($item, $this->staff, 'in_progress')->assertRedirect();
        $this->actingAs($this->staff)->post(route('requests.progress', $item), ['progress' => 100, 'product_notes' => 'Đã tổ chức xong', 'note' => 'Kết quả thực hiện'])->assertRedirect();
        $this->assertSame(S::InProgress, $item->fresh()->status);
        $this->move($item, $this->staff, 'completed')->assertForbidden();
        $this->move($item, $this->office, 'completed')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(S::Completed, $item->fresh()->status);
        $this->assertNotNull($item->fresh()->completed_at);
        $this->assertSame(5, $item->histories()->count());
        $this->assertGreaterThan(0, $this->head->notifications()->count());
    }

    public function test_office_approves_every_event_and_director_is_read_only(): void
    {
        $item = $this->item(['status' => 'submitted', 'important' => true]);
        $this->move($item, $this->director, 'approved', ['coordination_mode' => 'autonomous'])->assertForbidden();
        $this->move($item, $this->office, 'approved', ['coordination_mode' => 'autonomous'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(S::Approved, $item->fresh()->status);
    }

    public function test_cannot_skip_office_approval_or_change_a_closed_event(): void
    {
        $item = $this->item(['status' => 'submitted']);
        $this->move($item, $this->staff, 'in_progress')->assertForbidden();
        $this->move($item, $this->office, 'completed')->assertForbidden();
        $item->update(['status' => 'completed', 'completed_at' => now()]);
        $this->actingAs($this->office)->post(route('requests.progress', $item), ['progress' => 90, 'note' => 'Thay đổi'])->assertForbidden();
        $this->post(route('requests.coordinate', $item), ['coordination_mode' => 'autonomous', 'note' => 'Thay đổi'])->assertUnprocessable();
    }

    public function test_source_and_product_uploads_are_private_and_audited(): void
    {
        Storage::fake('local');
        $item = $this->item();
        $this->actingAs($this->staff)->post(route('requests.upload', $item), ['kind' => 'source', 'file' => UploadedFile::fake()->create('source.pdf', 10, 'application/pdf')])->assertRedirect();
        $file = $item->attachments()->first();
        Storage::disk('local')->assertExists($file->path);
        $this->assertDatabaseHas('request_histories', ['media_request_id' => $item->id, 'action' => 'Bổ sung tài liệu']);
        $item->update(['status' => 'in_progress', 'assignee_id' => $this->media->id]);
        $this->actingAs($this->media)->post(route('requests.upload', $item), ['kind' => 'product', 'file' => UploadedFile::fake()->create('product.pdf', 10, 'application/pdf')])->assertRedirect();
        $this->assertSame(2, $item->attachments()->count());
    }

    public function test_reminders_are_deduplicated_per_user_per_day(): void
    {
        $this->item(['status' => 'in_progress', 'assignee_id' => $this->media->id, 'due_at' => now()->subDay()]);
        $this->artisan('media:remind')->assertSuccessful();
        $this->artisan('media:remind')->assertSuccessful();
        $this->assertSame(1, $this->media->notifications()->count());
        $this->assertSame(1, $this->head->notifications()->count());
    }

    public function test_theme_is_personal_and_staff_cannot_change_system_settings(): void
    {
        $this->actingAs($this->staff)->post('/settings', ['theme_color' => '#7c3aed', 'organization_name' => 'Changed'])->assertRedirect();
        $this->assertSame('#7c3aed', $this->staff->fresh()->theme_color);
        $this->assertNull(Setting::find('organization_name'));
        $this->post('/settings', ['theme_color' => 'red;}</style>'])->assertSessionHasErrors('theme_color');
        $this->get('/users')->assertForbidden();
    }

    public function test_user_creation_requires_password_and_department_for_staff(): void
    {
        $data = ['name' => 'New Staff', 'email' => 'new@example.com', 'role' => 'staff', 'active' => 1];
        $this->actingAs($this->admin)->post('/users', $data)->assertSessionHasErrors(['password', 'department_id']);
        $this->post('/users', [...$data, 'password' => 'ValidPassword123!', 'department_id' => $this->unit->id])->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'role' => 'staff']);
    }

    public function test_filters_and_reports_match_requested_unit_and_time(): void
    {
        $this->item(['status' => 'completed', 'completed_at' => now(), 'title' => 'Đã xong']);
        $this->item(['status' => 'needs_info', 'title' => 'Cần tài liệu']);
        $this->actingAs($this->office)->get('/requests?status=needs_info')->assertOk()->assertSee('Cần tài liệu')->assertDontSee('Đã xong');
        $this->get('/reports?department_id='.$this->unit->id)->assertOk()->assertSee('Đào tạo');
        $this->get('/calendar?week=invalid')->assertRedirect()->assertSessionHasErrors('week');
    }

    public function test_all_roles_can_render_their_visible_pages(): void
    {
        $item = $this->item(['status' => 'approved', 'assignee_id' => $this->media->id]);
        foreach ([$this->director, $this->head, $this->staff, $this->media] as $user) {
            foreach (['/', '/requests', '/requests/'.$item->id, '/calendar', '/reports', '/library', '/settings'] as $path) {
                $this->actingAs($user)->get($path)->assertOk();
            }
        }
    }
}
