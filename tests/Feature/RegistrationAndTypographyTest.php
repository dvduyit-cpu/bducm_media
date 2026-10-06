<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationAndTypographyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_removed_and_cannot_create_accounts(): void
    {
        $unit = Department::create(['code' => 'DT', 'name' => 'Đào tạo']);
        $before = User::count();
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'Public user', 'email' => 'public@example.test', 'department_id' => $unit->id, 'password' => 'ValidPassword123!', 'password_confirmation' => 'ValidPassword123!'])->assertNotFound();
        $this->assertEquals($before, User::count());
        $this->get('/login')->assertOk()->assertDontSee('href="'.url('/register').'"', false)->assertSee('Liên hệ admin tổng để được cấp.');
    }

    public function test_admin_can_create_departments_and_accounts_but_staff_cannot(): void
    {
        $office = User::factory()->create(['role' => 'admin']);
        $this->actingAs($office)->post('/departments', ['code' => 'CTSV', 'name' => 'Phòng Công tác sinh viên'])->assertRedirect();
        $unit = Department::where('code', 'CTSV')->firstOrFail();
        $this->post('/users', ['name' => 'Nhân viên mới', 'email' => 'new@example.test', 'department_id' => $unit->id, 'password' => 'ValidPassword123!', 'role' => 'staff', 'active' => 1, 'permissions' => ['dashboard', 'requests', 'calendar']])->assertRedirect();
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertTrue($user->active);
        $this->assertTrue(Hash::check('ValidPassword123!', $user->password));
        $this->actingAs($user)->post('/users', ['name' => 'Unauthorized'])->assertForbidden();
        $this->post('/departments', ['code' => 'NEW', 'name' => 'Unauthorized'])->assertForbidden();
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'ValidPassword123!'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_typography_is_personal_validated_and_preserved_when_not_submitted(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $other = User::factory()->create();
        $this->actingAs($user)->post('/settings', ['theme_color' => '#2563eb', 'font_family' => 'tahoma', 'font_size' => 20])->assertRedirect();
        $this->assertEquals('tahoma', $user->fresh()->font_family);
        $this->assertEquals(20, $user->fresh()->font_size);
        $this->assertEquals('system', $other->fresh()->font_family);
        $this->assertEquals(16, $other->fresh()->font_size);
        $this->get('/')->assertOk()->assertSee('--font-scale: 1.25', false)->assertSee('--app-font: Tahoma, Arial, sans-serif', false);
        $this->post('/settings', ['theme_color' => '#2563eb', 'font_family' => 'bad-font; color:red', 'font_size' => 100])->assertSessionHasErrors(['font_family', 'font_size']);
        $this->assertEquals(20, $user->fresh()->font_size);
        $this->post('/settings', ['theme_color' => '#7c3aed'])->assertRedirect();
        $this->assertEquals('tahoma', $user->fresh()->font_family);
        $this->assertEquals(20, $user->fresh()->font_size);
    }

    public function test_loading_style_is_personal_and_only_accepts_supported_options(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $other = User::factory()->create();
        $this->actingAs($user)->post('/settings', ['theme_color' => '#2563eb', 'loading_style' => 'top'])->assertRedirect();
        $this->assertEquals('top', $user->fresh()->loading_style);
        $this->assertEquals('center', $other->fresh()->loading_style);
        $this->get('/')->assertOk()->assertSee('data-loading-style="top"', false);
        $this->post('/settings', ['theme_color' => '#2563eb', 'loading_style' => 'invalid'])->assertSessionHasErrors('loading_style');
        $this->assertEquals('top', $user->fresh()->loading_style);
        $this->post('/settings', ['theme_color' => '#7c3aed'])->assertRedirect();
        $this->assertEquals('top', $user->fresh()->loading_style);
        $this->post('/settings', ['theme_color' => '#2563eb', 'loading_style' => 'center'])->assertRedirect();
        $this->get('/')->assertSee('data-loading-style="center"', false);
    }
}
