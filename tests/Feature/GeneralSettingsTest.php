<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_branding_and_replace_or_remove_logo(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $data = ['software_name' => 'Event Portal', 'footer_text' => 'Internal system'];
        $this->actingAs($admin)->post(route('settings.general'), [...$data, 'logo' => UploadedFile::fake()->image('logo.png')])->assertRedirect()->assertSessionHasNoErrors();
        $firstPath = Setting::valueOf('logo_path');
        Storage::disk('local')->assertExists($firstPath);
        $this->get(route('settings'))->assertOk()->assertSee('Event Portal')->assertSee('Internal system')->assertSee(Setting::logoUrl());
        auth()->logout();
        $this->get(route('login'))->assertOk()->assertSee('Event Portal')->assertSee('Internal system')->assertSee(Setting::logoUrl());
        $this->get(route('branding.logo'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($admin);
        $this->post(route('settings.general'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($firstPath, Setting::valueOf('logo_path'));
        $this->post(route('settings.general'), [...$data, 'logo' => UploadedFile::fake()->image('new-logo.jpg')])->assertRedirect()->assertSessionHasNoErrors();
        $secondPath = Setting::valueOf('logo_path');
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($secondPath);
        $this->post(route('settings.general'), [...$data, 'footer_text' => '', 'remove_logo' => 1])->assertRedirect()->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($secondPath);
        $this->assertNull(Setting::logoUrl());
        $this->assertSame('', Setting::footerText());
        $this->get(route('branding.logo'))->assertNotFound();
        auth()->logout();
        $this->get(route('login'))->assertOk()->assertSee('Event Portal')->assertDontSee('Internal system');
    }

    public function test_non_admin_cannot_change_general_settings_and_menu_respects_permissions(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['permissions' => ['requests']]);
        $this->actingAs($user)->post(route('settings.general'), ['software_name' => 'Unauthorized', 'footer_text' => 'Changed', 'logo' => UploadedFile::fake()->image('logo.png')])->assertForbidden();
        $this->assertDatabaseMissing('settings', ['key' => 'software_name']);
        $this->assertSame([], Storage::disk('local')->allFiles('branding'));
        $this->get(route('settings'))->assertOk()->assertDontSee('id="general-settings"', false)->assertSee('data-toggle-group="work"', false)->assertSee('data-toggle-group="system"', false)->assertDontSee('data-toggle-group="organization"', false)->assertDontSee('href="'.route('reports').'"', false);
        auth()->logout();
        $this->post(route('settings.general'), ['software_name' => 'Unauthorized'])->assertRedirect(route('login'));
    }

    public function test_invalid_logos_and_names_leave_branding_unchanged_and_text_is_escaped(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $data = ['software_name' => '<script>alert(1)</script>', 'footer_text' => '<img src=x onerror=alert(1)>'];
        $this->actingAs($admin)->post(route('settings.general'), $data)->assertRedirect()->assertSessionHasNoErrors();
        foreach ([UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml'), UploadedFile::fake()->image('large.png')->size(2049), UploadedFile::fake()->createWithContent('fake.png', 'not an image')] as $logo) {
            $this->post(route('settings.general'), ['software_name' => 'Changed', 'logo' => $logo])->assertSessionHasErrors('logo');
            $this->assertSame($data['software_name'], Setting::softwareName());
        }
        $this->post(route('settings.general'), ['software_name' => ''])->assertSessionHasErrors('software_name');
        $this->get(route('settings'))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
        $this->assertSame([], Storage::disk('local')->allFiles('branding'));

    }
}
