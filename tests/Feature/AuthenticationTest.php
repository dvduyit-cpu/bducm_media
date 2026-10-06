<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_logout_with_real_credentials(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword123!']);
        $this->post('/login', ['email' => $user->email, 'password' => 'ValidPassword123!'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_failed_attempts_are_limited_per_account_and_ip(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword123!']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'WrongPassword'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'ValidPassword123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $other = User::factory()->create(['password' => 'OtherPassword123!']);
        $this->post('/login', ['email' => $other->email, 'password' => 'OtherPassword123!'])->assertRedirect('/');
        $this->assertAuthenticatedAs($other);
    }

    public function test_inactive_account_cannot_authenticate(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword123!', 'active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'ValidPassword123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
