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

    private User $office;

    private User $director;

    private User $head;

    private User $staff;

    private User $media;

    protected function setUp(): void
    {
        parent::setUp();
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

    public function test_all_pages_render_for_office(): void
    {
        $item = $this->item();
        foreach (['/', '/requests', '/requests/create', '/requests/'.$item->id, '/requests/'.$item->id.'/edit', '/calendar', '/approvals', '/reports', '/library', '/notifications', '/settings', '/users', '/departments'] as $path) {
            $this->actingAs($this->office)->get($path)->assertOk();
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

    public function test_full_workflow_requires_internal_and_professional_approval(): void
    {
        $item = $this->item();
        $this->move($item, $this->staff, 'submitted')->assertForbidden();
        foreach ([[$this->staff, 'internal_review'], [$this->head, 'submitted'], [$this->office, 'received'], [$this->office, 'ready'], [$this->office, 'awaiting_assignment']] as [$user, $target]) {
            $this->move($item, $user, $target)->assertRedirect();
        }
        $this->move($item, $this->office, 'assigned', ['assignee_id' => $this->staff->id])->assertSessionHasErrors('assignee_id');
        $this->move($item, $this->office, 'assigned', ['assignee_id' => $this->media->id])->assertRedirect();
        $this->move($item, $this->media, 'in_progress')->assertRedirect();
        $this->move($item, $this->media, 'pending_approval')->assertSessionHasErrors('target');
        $this->actingAs($this->media)->post(route('requests.progress', $item), ['progress' => 90, 'product_notes' => 'Bài viết hoàn chỉnh', 'note' => 'Đã bàn giao'])->assertRedirect();
        $this->move($item, $this->media, 'pending_approval')->assertRedirect();
        $this->move($item, $this->office, 'approved')->assertForbidden();
        $this->move($item, $this->head, 'professional_check')->assertRedirect();
        $this->move($item, $this->office, 'approved')->assertRedirect();
        $this->move($item, $this->media, 'published')->assertSessionHasErrors('published_url');
        $this->move($item, $this->media, 'published', ['published_url' => 'https://example.com/news'])->assertRedirect();
        $this->move($item, $this->media, 'completed')->assertRedirect();
        $this->assertSame(S::Completed, $item->fresh()->status);
        $this->assertSame(100, $item->fresh()->progress);
        $this->assertEquals(13, $item->histories()->count());
        $this->assertGreaterThan(0, $this->head->notifications()->count());
    }

    public function test_important_content_requires_director_and_revision_clears_check(): void
    {
        $item = $this->item(['status' => 'pending_approval', 'important' => true, 'professional_checked_by' => $this->head->id, 'assignee_id' => $this->media->id]);
        $this->move($item, $this->office, 'approved')->assertForbidden();
        $this->move($item, $this->director, 'revision')->assertRedirect();
        $this->assertNull($item->fresh()->professional_checked_by);
        $item->refresh()->update(['status' => 'pending_approval', 'professional_checked_by' => $this->head->id]);
        $this->move($item, $this->director, 'approved')->assertRedirect();
        $this->assertSame(S::Approved, $item->fresh()->status);
    }

    public function test_cannot_skip_approval_or_modify_approved_product(): void
    {
        $item = $this->item(['status' => 'in_progress', 'assignee_id' => $this->media->id]);
        $this->move($item, $this->media, 'published', ['published_url' => 'https://example.com'])->assertForbidden();
        $item->update(['status' => 'approved']);
        $this->actingAs($this->office)->post(route('requests.progress', $item), ['progress' => 90, 'product_notes' => 'Thay đổi sau duyệt', 'note' => 'Sửa'])->assertForbidden();
        $this->post(route('requests.upload', $item), ['kind' => 'product', 'file' => UploadedFile::fake()->create('product.pdf', 10, 'application/pdf')])->assertForbidden();
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
        $this->actingAs($this->office)->post('/users', $data)->assertSessionHasErrors(['password', 'department_id']);
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
        $item = $this->item(['status' => 'pending_approval', 'assignee_id' => $this->media->id]);
        foreach ([$this->director, $this->head, $this->staff, $this->media] as $user) {
            foreach (['/', '/requests', '/requests/'.$item->id, '/calendar', '/approvals', '/reports', '/library', '/settings'] as $path) {
                $this->actingAs($user)->get($path)->assertOk();
            }
        }
    }
}
