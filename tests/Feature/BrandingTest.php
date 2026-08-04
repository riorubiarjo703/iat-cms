<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        SiteSetting::singleton()->update(['brand_subtitle' => ['en' => 'Danayasa Arthatama']]);
        $this->seedHomepage();
    }

    public function test_the_public_header_renders_the_uploaded_logo(): void
    {
        Storage::disk('public')->put('uploads/branding/logo.png', 'png-bytes');
        SiteSetting::singleton()->update(['site_name' => 'SCBD', 'logo' => 'uploads/branding/logo.png']);

        $this->get('/')
            ->assertSuccessful()
            ->assertSee('uploads/branding/logo.png', false);
    }

    public function test_the_logo_image_carries_the_site_name_as_alt_text(): void
    {
        Storage::disk('public')->put('uploads/branding/logo.png', 'png-bytes');
        SiteSetting::singleton()->update(['site_name' => 'SCBD', 'logo' => 'uploads/branding/logo.png']);

        $this->get('/')->assertSee('alt="SCBD"', false);
    }

    public function test_the_header_falls_back_to_the_site_name_when_no_logo_is_set(): void
    {
        SiteSetting::singleton()->update(['site_name' => 'SCBD', 'logo' => null]);

        $response = $this->get('/');

        // The brand must never disappear just because no logo was uploaded.
        $response->assertSee('SCBD');

        // Scoped to the brand link rather than the whole document: the header
        // legitimately carries other images (the locale flags), and asserting
        // on the page as a whole made this test fail for an unrelated reason.
        $html = $response->getContent();
        $start = strpos($html, '<a href="#top"');
        $brand = substr($html, $start, strpos($html, '</a>', $start) - $start);

        $this->assertStringNotContainsString('<img', $brand);
    }

    public function test_the_fallback_uses_the_configured_site_name_not_a_hardcoded_string(): void
    {
        // Guards against the previous behaviour, where the header hardcoded "SCBD"
        // and ignored Site Settings entirely.
        SiteSetting::singleton()->update(['site_name' => 'Renamed Co', 'logo' => null]);

        $this->get('/')
            ->assertSee('Renamed Co')
            ->assertDontSee('>SCBD<', false);
    }

    public function test_the_brand_subtitle_still_renders_alongside_the_logo(): void
    {
        Storage::disk('public')->put('uploads/branding/logo.png', 'png-bytes');
        SiteSetting::singleton()->update(['site_name' => 'SCBD', 'logo' => 'uploads/branding/logo.png']);

        $this->get('/')->assertSee('Danayasa Arthatama');
    }

    public function test_a_missing_logo_never_emits_an_empty_src(): void
    {
        SiteSetting::singleton()->update(['site_name' => 'SCBD', 'logo' => null]);

        $this->get('/')->assertDontSee('src=""', false);
    }

    public function test_the_admin_panel_uses_the_uploaded_logo(): void
    {
        Storage::disk('public')->put('uploads/branding/logo.png', 'png-bytes');
        SiteSetting::singleton()->update(['site_name' => 'SCBD', 'logo' => 'uploads/branding/logo.png']);

        $this->actingAs(User::factory()->create());

        $this->get('/superduper')
            ->assertSuccessful()
            ->assertSee('uploads/branding/logo.png', false);
    }

    public function test_the_admin_panel_falls_back_to_the_site_name(): void
    {
        SiteSetting::singleton()->update(['site_name' => 'Renamed Co', 'logo' => null]);

        $this->actingAs(User::factory()->create());

        $this->get('/superduper')->assertSuccessful()->assertSee('Renamed Co');
    }

    public function test_the_panel_does_not_break_on_a_fresh_database(): void
    {
        // SiteSetting::singleton() firstOrCreates, so site_name may be null.
        $this->actingAs(User::factory()->create());

        $this->get('/superduper')->assertSuccessful();
    }
}
