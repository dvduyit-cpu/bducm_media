<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Department;
use App\Models\MediaRequest;
use App\Models\User;
use App\Notifications\WeeklyRegistrationReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ActivityManagementTest extends TestCase
{
    use RefreshDatabase;

    private function setupRecords(): array
    {
        $unit = Department::create(['name' => 'Đào tạo', 'code' => 'DT']);
        $support = Department::create(['name' => 'Công tác sinh viên', 'code' => 'CTSV']);
        $head = User::factory()->create(['role' => 'head', 'department_id' => $unit->id]);
        $staff = User::factory()->create(['role' => 'staff', 'department_id' => $unit->id]);
        $supportUser = User::factory()->create(['role' => 'staff', 'department_id' => $support->id]);
        $office = User::factory()->create(['role' => 'office']);
        $media = User::factory()->create(['role' => 'media']);
        $item = MediaRequest::create(['title' => 'Dự giờ chuyên môn', 'description' => 'Hoạt động học thuật', 'record_type' => 'activity', 'location' => 'Phòng 101', 'department_id' => $unit->id, 'creator_id' => $staff->id, 'contact_id' => $head->id, 'event_at' => '2026-10-06 09:00', 'event_ends_at' => '2026-10-07 11:00', 'publish_at' => null, 'due_at' => null, 'channel' => 'Website', 'priority' => 'normal', 'status' => 'submitted']);

        return compact('unit', 'support', 'head', 'staff', 'supportUser', 'office', 'media', 'item');
    }

    public function test_event_creation_is_simple_and_server_ignores_workflow_tampering(): void
    {
        ['unit' => $unit,'staff' => $staff,'head' => $head,'support' => $support] = $this->setupRecords();
        $data = ['title' => 'Tập huấn', 'description' => 'Hoạt động đơn vị', 'event_at' => '2026-10-12 09:00', 'event_ends_at' => '2026-10-12 11:00', 'department_id' => $unit->id, 'contact_id' => $head->id, 'task_color' => '#eb2424', 'status' => 'approved', 'coordination_mode' => 'support', 'support_department_ids' => [$support->id]];
        $this->actingAs($staff)->post('/requests', $data)->assertRedirect()->assertSessionHasNoErrors();
        $created = MediaRequest::where('title', 'Tập huấn')->firstOrFail();
        $this->assertSame(RequestStatus::Draft, $created->status);
        $this->assertSame('pending', $created->coordination_mode);
        $this->assertSame(0, $created->supportDepartments()->count());
        $this->assertSame('#eb2424', $created->task_color);
        $this->post('/requests', [...$data, 'event_ends_at' => '2026-10-11 09:00'])->assertSessionHasErrors('event_ends_at');
        $this->post('/requests', [...$data, 'task_color' => 'red;display:none'])->assertSessionHasErrors('task_color');
    }

    public function test_office_must_select_support_units_and_cannot_select_host_or_unknown_unit(): void
    {
        ['item' => $item,'office' => $office,'unit' => $unit] = $this->setupRecords();
        $this->actingAs($office);
        $url = route('requests.transition', $item);
        $this->post($url, ['target' => 'approved'])->assertSessionHasErrors('coordination_mode');
        $this->post($url, ['target' => 'approved', 'coordination_mode' => 'support'])->assertSessionHasErrors('support_department_ids');
        $this->post($url, ['target' => 'approved', 'coordination_mode' => 'support', 'support_department_ids' => [$unit->id]])->assertSessionHasErrors('support_department_ids.0');
        $this->post($url, ['target' => 'approved', 'coordination_mode' => 'support', 'support_department_ids' => [999999]])->assertSessionHasErrors('support_department_ids.0');
        $this->assertSame(RequestStatus::Submitted, $item->fresh()->status);
        $this->assertSame(0, $item->histories()->count());
    }

    public function test_support_units_share_one_event_but_cannot_approve_close_or_edit_source(): void
    {
        ['item' => $item,'office' => $office,'support' => $support,'supportUser' => $supportUser] = $this->setupRecords();
        $this->actingAs($supportUser)->get(route('requests.show', $item))->assertNotFound();
        $this->actingAs($office)->post(route('requests.transition', $item), ['target' => 'approved', 'coordination_mode' => 'support', 'support_department_ids' => [$support->id], 'support_notes' => 'Hỗ trợ chuẩn bị hội trường'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, MediaRequest::count());
        $this->assertSame(1, $supportUser->notifications()->count());
        $this->actingAs($supportUser)->get(route('requests.show', $item))->assertOk()->assertSee('Hỗ trợ chuẩn bị hội trường');
        $this->get(route('requests.edit', $item))->assertForbidden();
        $this->post(route('requests.coordinate', $item), ['coordination_mode' => 'autonomous', 'note' => 'Thay đổi'])->assertForbidden();
        $this->post(route('requests.transition', $item), ['target' => 'completed', 'note' => 'Đã xong'])->assertForbidden();
        $this->post(route('requests.progress', $item), ['progress' => 70, 'product_notes' => 'Đã chuẩn bị xong', 'note' => 'Phòng hỗ trợ cập nhật'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(RequestStatus::InProgress, $item->fresh()->status);
        $this->assertSame(70, $item->fresh()->progress);
        $this->assertDatabaseHas('request_histories', ['media_request_id' => $item->id, 'user_id' => $supportUser->id, 'action' => 'Cập nhật tiến độ 70%']);
    }

    public function test_switch_to_autonomy_removes_support_calendar_access_and_closed_plan_is_locked(): void
    {
        ['item' => $item,'office' => $office,'support' => $support,'supportUser' => $supportUser] = $this->setupRecords();
        $item->update(['status' => 'approved', 'coordination_mode' => 'support']);
        $item->supportDepartments()->attach($support->id);
        $this->actingAs($office)->post(route('requests.coordinate', $item), ['coordination_mode' => 'autonomous', 'support_department_ids' => [$support->id], 'note' => 'Đơn vị tự thực hiện'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, $item->supportDepartments()->count());
        $this->actingAs($supportUser)->get(route('requests.show', $item))->assertNotFound();
        $this->get('/calendar?week=2026-10-06')->assertOk()->assertDontSee('Dự giờ chuyên môn');
        $this->actingAs($office)->post(route('requests.transition', $item), ['target' => 'completed', 'note' => 'Sự kiện đã kết thúc'])->assertRedirect();
        $this->post(route('requests.coordinate', $item), ['coordination_mode' => 'support', 'support_department_ids' => [$support->id], 'note' => 'Đổi'])->assertUnprocessable();
    }

    public function test_support_users_can_add_results_but_closed_event_and_unsafe_links_are_rejected(): void
    {
        Storage::fake('local');
        ['item' => $item,'support' => $support,'supportUser' => $supportUser,'media' => $media] = $this->setupRecords();
        $item->update(['status' => 'approved', 'coordination_mode' => 'support']);
        $item->supportDepartments()->attach($support->id);
        $data = ['name' => 'Ảnh hoạt động', 'url' => 'https://drive.google.com/example', 'kind' => 'product'];
        $this->actingAs($supportUser)->post(route('requests.links', $item), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('requests.upload', $item), ['kind' => 'product', 'file' => UploadedFile::fake()->create('result.pdf', 10, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        $file = $item->attachments()->firstOrFail();
        $this->get(route('attachments.download', $file))->assertOk();
        $this->actingAs($media)->get(route('attachments.download', $file))->assertNotFound();
        $this->actingAs($supportUser)->post(route('requests.links', $item), [...$data, 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $item->update(['status' => 'completed']);
        $this->post(route('requests.links', $item), $data)->assertForbidden();
    }

    public function test_multi_day_event_appears_in_both_unit_calendars_without_duplicate_records(): void
    {
        ['item' => $item,'office' => $office,'support' => $support,'supportUser' => $supportUser,'staff' => $staff,'unit' => $unit] = $this->setupRecords();
        $item->update(['status' => 'approved', 'coordination_mode' => 'support']);
        $item->supportDepartments()->attach($support->id);
        foreach ([$staff, $supportUser] as $user) {
            $this->actingAs($user)->get('/calendar?view=day&week=2026-10-07')->assertOk()->assertSee('Dự giờ chuyên môn')->assertViewHas('items', fn ($items) => $items->count() === 1 && $items->first()->id === $item->id);
        }
        foreach ([$unit, $support] as $department) {
            $this->actingAs($office)->get('/calendar?week=2026-10-06&department_id='.$department->id)->assertViewHas('items', fn ($items) => $items->count() === 1);
        }
    }

    public function test_office_closure_updates_report_for_host_and_support_once(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8)->setTime(12, 0));
        ['item' => $item,'office' => $office,'support' => $support] = $this->setupRecords();
        $item->update(['status' => 'in_progress', 'coordination_mode' => 'support', 'due_at' => '2026-10-07 11:00']);
        $item->supportDepartments()->attach($support->id);
        Department::create(['name' => 'Đơn vị chưa có sự kiện', 'code' => 'ZERO']);
        $this->actingAs($office)->post(route('requests.transition', $item), ['target' => 'completed'])->assertSessionHasErrors('note');
        $this->post(route('requests.transition', $item), ['target' => 'completed', 'note' => 'Tổ chức xong và lưu hồ sơ'])->assertRedirect()->assertSessionHasNoErrors();
        $response = $this->get('/reports?period=month&period_date=2026-10-08')->assertOk()->assertViewHas('total', 1)->assertViewHas('completed', 1)->assertSee('Đơn vị chưa có sự kiện');
        $response->assertViewHas('rows', fn ($rows) => $rows->firstWhere('name', 'Đào tạo')['hosted'] === 1 && $rows->firstWhere('name', 'Công tác sinh viên')['supported'] === 1 && $rows->firstWhere('name', 'Công tác sinh viên')['completed'] === 1 && $rows->firstWhere('name', 'Đào tạo')['late'] === 1);
        $this->get('/reports?period=quarter&period_date=2026-07-01')->assertViewHas('total', 0);
    }

    public function test_excel_and_pdf_are_real_documents_and_formula_titles_are_safe(): void
    {
        ['item' => $item,'office' => $office] = $this->setupRecords();
        $item->update(['title' => '=HYPERLINK("https://example.com")']);
        $this->actingAs($office);
        $response = $this->get('/calendar?week=2026-10-06&export=xlsx')->assertOk()->assertDownload();
        $bytes = $response->streamedContent();
        $this->assertSame('PK', substr($bytes, 0, 2));
        $file = tempnam(sys_get_temp_dir(), 'bdu-xlsx-');
        try {
            file_put_contents($file, $bytes);
            $book = IOFactory::load($file);
            $this->assertSame($item->title, $book->getActiveSheet()->getCell('A2')->getValue());
            $this->assertSame('s', $book->getActiveSheet()->getCell('A2')->getDataType());
        } finally {
            unlink($file);
        }
        foreach (['/calendar?week=2026-10-06&export=pdf', '/reports?export=pdf'] as $url) {
            $pdf = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertSame('%PDF', substr($pdf->getContent(), 0, 4));
        }
        $this->get('/reports?export=xlsx')->assertOk()->assertDownload();
    }

    public function test_weekly_registration_reminder_is_deduplicated(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8)->setTime(15, 0));
        ['staff' => $staff] = $this->setupRecords();
        $this->artisan('media:registration-remind')->assertSuccessful();
        $this->artisan('media:registration-remind')->assertSuccessful();
        $this->assertSame(1, $staff->notifications()->where('type', WeeklyRegistrationReminder::class)->count());
        $this->assertSame('2026-10-12', $staff->notifications()->first()->data['week']);
        $this->actingAs($staff)->get('/notifications')->assertOk();
    }

    public function test_board_names_and_colors_are_personal_and_invalid_colors_are_rejected(): void
    {
        ['staff' => $staff,'office' => $office] = $this->setupRecords();
        $data = ['theme_color' => '#eb2424', 'board' => ['background' => '#ffeddf', 'columns' => ['submitted' => ['title' => 'Đợi kiểm tra', 'color' => '#b91c1c']]]];
        $this->actingAs($staff)->post('/settings', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/')->assertOk()->assertSee('Đợi kiểm tra')->assertSee('#ffeddf')->assertSee('#b91c1c');
        $this->actingAs($office)->get('/')->assertOk()->assertDontSee('Đợi kiểm tra');
        $this->actingAs($staff)->post('/settings', ['theme_color' => '#123456', 'board' => ['background' => 'red;}</style>']])->assertSessionHasErrors('board.background');
        $this->assertSame('#eb2424', $staff->fresh()->theme_color);
    }

    public function test_invalid_coordination_rolls_back_instead_of_partially_replacing_support(): void
    {
        ['item' => $item,'office' => $office,'support' => $support,'unit' => $unit] = $this->setupRecords();
        $item->update(['status' => 'approved', 'coordination_mode' => 'support']);
        $item->supportDepartments()->attach($support->id);
        $this->actingAs($office)->post(route('requests.coordinate', $item), ['coordination_mode' => 'support', 'support_department_ids' => [$unit->id], 'note' => 'Không đổi một phần'])->assertSessionHasErrors('support_department_ids.0');
        $this->assertSame([$support->id], $item->supportDepartments()->pluck('departments.id')->all());
        $this->assertSame(0, $item->histories()->count());
    }
}
