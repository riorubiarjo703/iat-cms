<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ActsAsSuperAdmin;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsSuperAdmin;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        SiteSetting::singleton()->update(['brand_subtitle' => ['en' => 'Danayasa Arthatama']]);
        $this->seedHomepage();
    }

    /** The brand link's markup, located by the header bar rather than by its
     *  own href — which is the thing under test. */
    private function brandLink(string $html): string
    {
        $bar = strpos($html, 'scbd-header-bar');

        $this->assertNotFalse($bar, 'the header bar should be present');

        $start = strpos($html, '<a ', $bar);

        return substr($html, $start, strpos($html, '</a>', $start) - $start);
    }

    public function test_the_logo_links_to_the_site_root_from_an_interior_page(): void
    {
        // This link has been wrong three ways: "#top", which only scrolls the
        // page you are already on and is dead everywhere else; the admin panel
        // URL, which sent visitors to a login screen and published the admin
        // path on every page; and an empty href, which reloads wherever you
        // are. It is the way back to the homepage, so it points at the root.
        \App\Models\Page::create([
            'title' => ['en' => 'Interior'], 'slug' => 'interior',
            'type' => \App\Models\Page::TYPE_BUILDER,
            'status' => \App\Models\Page::STATUS_PUBLISHED,
            'builder_payload' => [],
        ]);

        $brand = $this->brandLink($this->get('/interior')->assertSuccessful()->getContent());

        $this->assertStringContainsString('href="'.route('home').'"', $brand);
        $this->assertStringNotContainsString('#top', $brand);
        $this->assertStringNotContainsString(rtrim(\Filament\Facades\Filament::getUrl(), '/'), $brand);
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
        //
        // Located by the header bar, not by the link's href. Keying off
        // '<a href="#top"' meant that changing where the brand points made
        // strpos return false, substr read from offset 0, and the assertion
        // quietly examine the document head — which has no <img> in it, so the
        // test passed while checking nothing.
        $brand = $this->brandLink($response->getContent());

        $this->assertStringContainsString('SCBD', $brand, 'the brand link should carry the site name');
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

        $this->actingAsSuperAdmin();

        $this->get('/superduper')
            ->assertSuccessful()
            ->assertSee('uploads/branding/logo.png', false);
    }

    public function test_the_admin_panel_falls_back_to_the_site_name(): void
    {
        SiteSetting::singleton()->update(['site_name' => 'Renamed Co', 'logo' => null]);

        $this->actingAsSuperAdmin();

        $this->get('/superduper')->assertSuccessful()->assertSee('Renamed Co');
    }

    public function test_the_panel_does_not_break_on_a_fresh_database(): void
    {
        // SiteSetting::singleton() firstOrCreates, so site_name may be null.
        $this->actingAsSuperAdmin();

        $this->get('/superduper')->assertSuccessful();
    }
}
