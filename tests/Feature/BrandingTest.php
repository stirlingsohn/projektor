<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Branding::DISK);
    }

    public function test_the_owner_sets_company_name_and_logo()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('branding.update'), ['company_name' => ' Tschan Music ', 'logo' => UploadedFile::fake()->image('logo.png', 64, 64)])
            ->assertSessionHasNoErrors();
        Setting::flush();

        $this->assertSame('Tschan Music', Branding::name());
        Storage::disk(Branding::DISK)->assertExists(Branding::logoPath());
        $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('branding.name', 'Tschan Music')
            ->where('branding.logoUrl', Branding::logoUrl()));
    }

    public function test_the_logo_is_public_for_the_login_page()
    {
        Storage::disk(Branding::DISK)->put('branding/logo.png', 'png');
        Setting::put('logo_path', 'branding/logo.png');

        $this->get(route('branding.logo'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('login'))->assertInertia(fn (AssertableInertia $page) => $page->where('branding.logoUrl', Branding::logoUrl()));
    }

    public function test_replacing_or_removing_the_logo_deletes_the_old_file()
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('branding.update'), ['logo' => UploadedFile::fake()->image('a.png')]);
        $first = Setting::query()->find('logo_path')->value;

        $this->actingAs($owner)->post(route('branding.update'), ['logo' => UploadedFile::fake()->image('b.png')]);
        Storage::disk(Branding::DISK)->assertMissing($first);

        $this->actingAs($owner)->delete(route('branding.logo.destroy'));
        Setting::flush();
        $this->assertNull(Branding::logoPath());
        $this->assertSame([], Storage::disk(Branding::DISK)->allFiles('branding'));
    }

    public function test_emails_use_the_company_name()
    {
        Setting::put('company_name', 'Tschan Music');

        $this->get(route('login'));

        $this->assertSame('Tschan Music', config('mail.from.name'));
    }

    public function test_clients_cannot_change_the_branding()
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)->get(route('branding.edit'))->assertForbidden();
        $this->actingAs($client)->post(route('branding.update'), ['company_name' => 'Hack'])->assertForbidden();
    }

    public function test_html_disguised_as_a_logo_is_rejected()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('branding.update'), ['logo' => UploadedFile::fake()->createWithContent('logo.html', '<script>alert(1)</script>')])
            ->assertSessionHasErrors('logo');
    }
}
