<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_and_all_roles_can_read_their_usage_guide(): void
    {
        $this->get('/help')->assertRedirect(route('login'));
        foreach (['admin', 'office', 'director', 'head', 'staff', 'media'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/help')->assertOk()->assertSee('Hướng dẫn sử dụng & phân quyền')->assertSee($user->role->label())->assertSee('Bảng phân quyền')->assertSee('Cửa sổ thao tác')->assertSee('modal-old-input', false)->assertSee('modals.js', false);
        }
    }

    public function test_validation_reopens_correct_modal_without_exposing_passwords(): void
    {
        $office = User::factory()->create(['role' => 'admin']);
        $this->actingAs($office)->from('/users')->post('/users', ['name' => 'Nhập chưa lưu', 'email' => 'sai', 'password' => 'PrivatePassword@2026', 'role' => 'staff', '_modal_form' => '/users:0'])->assertRedirect('/users')->assertSessionHasErrors(['email', 'department_id']);
        $this->get('/users')->assertOk()->assertSee('/users:0')->assertSee('Nhập chưa lưu')->assertDontSee('PrivatePassword@2026');
    }
}
