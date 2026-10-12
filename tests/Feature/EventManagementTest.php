<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\MediaRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{item: MediaRequest, owner: User, department: Department} */
    private function records(): array
    {
        $department = Department::create(['name' => 'Host', 'code' => 'HOST']);
        $owner = User::factory()->create(['role' => 'staff', 'department_id' => $department->id]);
        $item = MediaRequest::create(['title' => 'Event', 'description' => 'Details', 'department_id' => $department->id,
            'creator_id' => $owner->id, 'contact_id' => $owner->id, 'status' => 'submitted', 'channel' => 'Website', 'event_at' => now()->addDay()]);

        return compact('item', 'owner', 'department');
    }

    public function test_owner_can_edit_active_events_without_changing_workflow(): void
    {
        ['item' => $item, 'owner' => $owner, 'department' => $department] = $this->records();
        $this->actingAs($owner)->get(route('requests.index'))->assertOk()->assertSee(route('requests.edit', $item))->assertSee('delete-event-'.$item->id);
        foreach (['submitted', 'approved', 'in_progress'] as $status) {
            $item->update(['status' => $status]);
            $this->get(route('requests.edit', $item))->assertOk();
            $this->put(route('requests.update', $item), ['title' => 'Updated event', 'description' => 'New details',
                'department_id' => $department->id, 'contact_id' => $owner->id, 'event_at' => '2026-10-12 09:00',
                'event_ends_at' => '2026-10-12 11:00', 'status' => 'completed', 'coordination_mode' => 'support'])
                ->assertRedirect(route('requests.show', $item))->assertSessionHasNoErrors();
            $this->assertSame($status, $item->fresh()->status->value);
            $this->assertSame('Updated event', $item->fresh()->title);
            $this->assertSame('pending', $item->fresh()->coordination_mode);
        }
        $this->assertSame(3, $item->histories()->count());
    }

    public function test_deletion_requires_confirmation_and_removes_related_records_and_files(): void
    {
        Storage::fake('local');
        ['item' => $item, 'owner' => $owner] = $this->records();
        $support = Department::create(['name' => 'Support', 'code' => 'SUP']);
        $item->supportDepartments()->attach($support);
        $path = 'requests/'.$item->id.'/source.txt';
        Storage::disk('local')->put($path, 'Source');
        $file = $item->attachments()->create(['user_id' => $owner->id, 'name' => 'source.txt', 'path' => $path, 'size' => 6]);
        $item->links()->create(['user_id' => $owner->id, 'name' => 'Source', 'url' => 'https://example.com', 'kind' => 'source']);
        $item->histories()->create(['user_id' => $owner->id, 'action' => 'Created']);
        $this->actingAs($owner)->delete(route('requests.destroy', $item))->assertSessionHasErrors('confirm_delete');
        $this->assertDatabaseHas('media_requests', ['id' => $item->id]);
        $this->delete(route('requests.destroy', $item), ['confirm_delete' => 1])->assertRedirect(route('requests.index'));
        foreach (['media_requests' => 'id', 'attachments' => 'media_request_id', 'request_links' => 'media_request_id', 'request_histories' => 'media_request_id', 'department_media_request' => 'media_request_id'] as $table => $column) {
            $this->assertDatabaseMissing($table, [$column => $item->id]);
        }
        Storage::disk('local')->assertMissing($path);
        $this->get(route('attachments.download', $file))->assertNotFound();
    }

    public function test_permissions_and_closed_events_prevent_editing_and_deletion(): void
    {
        ['item' => $item, 'owner' => $owner, 'department' => $department] = $this->records();
        $support = Department::create(['name' => 'Support', 'code' => 'SUP']);
        $colleague = User::factory()->create(['department_id' => $department->id]);
        $supportUser = User::factory()->create(['department_id' => $support->id]);
        $this->actingAs($supportUser)->delete(route('requests.destroy', $item), ['confirm_delete' => 1])->assertNotFound();
        $item->supportDepartments()->attach($support);
        foreach ([$colleague, $supportUser] as $user) {
            $this->actingAs($user)->get(route('requests.edit', $item))->assertForbidden();
            $this->delete(route('requests.destroy', $item), ['confirm_delete' => 1])->assertForbidden();
        }
        foreach (['completed', 'cancelled'] as $status) {
            $item->update(['status' => $status]);
            $this->actingAs($owner)->get(route('requests.edit', $item))->assertForbidden();
            $this->delete(route('requests.destroy', $item), ['confirm_delete' => 1])->assertForbidden();
        }
        $item->update(['status' => 'submitted']);
        $reviewer = User::factory()->create(['department_id' => $support->id, 'reviewer_access' => true]);
        $this->actingAs($reviewer)->get(route('requests.edit', $item))->assertOk();
        $this->delete(route('requests.destroy', $item), ['confirm_delete' => 1])->assertRedirect(route('requests.index'));
    }
}
