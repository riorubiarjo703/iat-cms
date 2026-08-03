<?php

namespace Tests\Feature;

use App\Models\HomepageContent;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\SiteSetting;
use App\Support\MenuLocations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        HomepageContent::singleton()->update(['hero_line' => ['en' => 'Hero']]);
    }

    private function footerMenu(): Menu
    {
        $menu = Menu::create(['name' => 'Footer Navigation', 'location' => MenuLocations::FOOTER]);
        $column = MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Sitemap'], 'url' => '#', 'sort' => 1]);
        MenuItem::create(['menu_id' => $menu->id, 'parent_id' => $column->id, 'label' => ['en' => 'Company profile'], 'url' => '#about', 'sort' => 1]);

        return $menu;
    }

    /**
     * The contact section is this design's footer: address, contact, sitemap
     * and social in one band. There is no separate <footer> element.
     */
    private function footer(): string
    {
        $html = $this->get('/')->assertSuccessful()->getContent();
        $start = strpos($html, '<section id="contact"');

        $this->assertNotFalse($start, 'No contact/footer section rendered');

        return substr($html, $start, strpos($html, '</section>', $start) - $start + 10);
    }

    public function test_the_sitemap_column_renders_the_assigned_menu(): void
    {
        $this->footerMenu();

        $footer = $this->footer();

        $this->assertStringContainsString('Company profile', $footer);
        $this->assertStringContainsString('#about', $footer);
    }

    public function test_a_grouping_parent_is_a_heading_not_a_link(): void
    {
        // "Sitemap" groups the links; rendering it as a link too would put a
        // dead entry at the top of the column.
        $this->footerMenu();

        $this->assertStringNotContainsString('>Sitemap</a>', $this->footer());
    }

    public function test_an_unassigned_footer_renders_no_links_rather_than_failing(): void
    {
        $this->assertStringNotContainsString('Company profile', $this->footer());
    }

    public function test_inactive_children_are_not_rendered(): void
    {
        // They exist so a URL can be filled in later; until then they must not
        // appear as links that go nowhere.
        $menu = $this->footerMenu();
        MenuItem::create([
            'menu_id' => $menu->id,
            'parent_id' => $menu->rootItems()->first()->id,
            'label' => ['en' => 'Careers'],
            'url' => '#',
            'is_active' => false,
            'sort' => 2,
        ]);

        $this->assertStringNotContainsString('Careers', $this->footer());
    }

    public function test_a_top_level_item_with_no_children_is_itself_a_link(): void
    {
        $menu = Menu::create(['name' => 'Footer', 'location' => MenuLocations::FOOTER]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Careers'], 'url' => '/careers', 'sort' => 1]);

        $this->assertStringContainsString('/careers', $this->footer());
    }

    public function test_contact_details_come_from_site_settings(): void
    {
        SiteSetting::singleton()->update([
            'contact_email' => 'hello@example.com',
            'contact_phone' => '+62 (21) 515-2390',
            'contact_address' => "Line One\nLine Two",
        ]);

        $footer = $this->footer();

        $this->assertStringContainsString('hello@example.com', $footer);
        $this->assertStringContainsString('+62 (21) 515-2390', $footer);
        $this->assertStringContainsString('Line One', $footer);
        $this->assertStringContainsString('Line Two', $footer);
    }

    public function test_only_configured_social_networks_appear(): void
    {
        SiteSetting::singleton()->update(['social' => ['instagram' => 'https://instagram.com/x', 'linkedin' => null]]);

        $footer = $this->footer();

        $this->assertStringContainsString('Instagram', $footer);
        $this->assertStringNotContainsString('LinkedIn', $footer);
    }

    public function test_social_links_keep_their_declared_order_and_casing(): void
    {
        // Insertion order in the JSON column is arbitrary, and Str::headline
        // renders "linkedin" as "Linkedin".
        SiteSetting::singleton()->update(['social' => [
            'linkedin' => 'https://linkedin.com/x',
            'facebook' => 'https://facebook.com/x',
            'twitter' => 'https://twitter.com/x',
        ]]);

        $footer = $this->footer();

        $this->assertStringContainsString('LinkedIn', $footer);
        $this->assertStringContainsString('X / Twitter', $footer);
        $this->assertLessThan(strpos($footer, 'X / Twitter'), strpos($footer, 'Facebook'));
        $this->assertLessThan(strpos($footer, 'LinkedIn'), strpos($footer, 'X / Twitter'));
    }

    public function test_the_contact_section_reads_the_same_source_as_the_footer(): void
    {
        // Two sources for one fact would drift.
        SiteSetting::singleton()->update(['contact_phone' => '+62 999']);

        $this->get('/')->assertSee('+62 999', false);
    }
}
