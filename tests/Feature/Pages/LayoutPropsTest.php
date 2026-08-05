<?php

namespace Tests\Feature\Pages;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class LayoutPropsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHeaderMenu();
        SiteSetting::singleton()->update(['site_name' => 'SCBD']);
    }

    public function test_the_layout_renders_the_title_it_is_handed_verbatim(): void
    {
        // The layout no longer knows what a suffix is; the caller applies it.
        $rendered = view('components.layouts.page', [
            'title' => 'Handed Title — SCBD',
            'description' => 'A description.',
            'animated' => false,
            'showLoader' => false,
            'i18n' => [],
            'slot' => '<p>Body</p>',
        ])->render();

        $this->assertStringContainsString('<title>Handed Title — SCBD</title>', $rendered);
        $this->assertStringContainsString('content="A description."', $rendered);
    }

    public function test_an_unanimated_page_loads_no_animation_bundle_and_no_i18n_payload(): void
    {
        $rendered = view('components.layouts.page', [
            'title' => 'Plain',
            'description' => null,
            'animated' => false,
            'showLoader' => false,
            'i18n' => ['en' => ['x' => 'y']],
            'slot' => '',
        ])->render();

        $this->assertStringNotContainsString('resources/js/scbd/index.js', $rendered);
        $this->assertStringNotContainsString('scbd-i18n', $rendered);
        $this->assertStringNotContainsString('cursor:none', $rendered);
    }

    public function test_an_animated_page_loads_the_bundle_the_cursor_and_the_payload(): void
    {
        $rendered = view('components.layouts.page', [
            'title' => 'Rich',
            'description' => null,
            'animated' => true,
            'showLoader' => false,
            'i18n' => ['en' => ['x' => 'y']],
            'slot' => '',
        ])->render();

        $this->assertStringContainsString('data-cursor', $rendered);
        $this->assertStringContainsString('scbd-i18n', $rendered);
    }

    public function test_the_loader_appears_only_when_asked_for(): void
    {
        $without = view('components.layouts.page', [
            'title' => 'A', 'description' => null, 'animated' => true,
            'showLoader' => false, 'i18n' => [], 'slot' => '',
        ])->render();

        $with = view('components.layouts.page', [
            'title' => 'A', 'description' => null, 'animated' => true,
            'showLoader' => true, 'i18n' => [], 'slot' => '',
        ])->render();

        $this->assertStringNotContainsString('data-loader', $without);
        $this->assertStringContainsString('data-loader', $with);
    }

    public function test_a_real_page_still_gets_its_suffixed_title(): void
    {
        Page::create([
            'title' => ['en' => 'About Us'],
            'slug' => 'about-us',
            'type' => Page::TYPE_SIMPLE,
            'content' => ['en' => '<p>Body</p>'],
            'status' => Page::STATUS_PUBLISHED,
        ]);

        $this->get('/about-us')->assertSee('<title>About Us — SCBD</title>', false);
    }

    public function test_the_homepage_takes_no_suffix(): void
    {
        $this->seedHomepage();
        SiteSetting::singleton()->update(['meta_title' => ['en' => 'SCBD Jakarta']]);

        $this->get('/')->assertSee('<title>SCBD Jakarta</title>', false);
    }
}
