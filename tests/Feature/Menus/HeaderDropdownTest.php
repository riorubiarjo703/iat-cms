<?php

namespace Tests\Feature\Menus;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\PageBuilder\BlockRegistry;
use App\PageBuilder\SiteTranslations;
use App\Support\MenuLocations;
use App\Support\MenuRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class HeaderDropdownTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    private function page(string $title, string $slug, string $status = Page::STATUS_PUBLISHED): Page
    {
        return Page::create([
            'title' => ['en' => $title], 'slug' => $slug,
            'type' => Page::TYPE_BUILDER, 'status' => $status, 'builder_payload' => [],
        ]);
    }

    private function tree(): Menu
    {
        $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'location' => MenuLocations::HEADER]);

        $company = MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Company', 'id' => 'Perusahaan'], 'url' => '#about', 'sort' => 1]);
        MenuItem::create([
            'menu_id' => $menu->id, 'parent_id' => $company->id, 'sort' => 1,
            'label' => ['en' => 'Profile', 'id' => 'Profil'],
            'type' => MenuItem::TYPE_PAGE, 'linkable_type' => Page::class,
            'linkable_id' => $this->page('Profile', 'profile')->id,
        ]);

        return $menu;
    }

    public function test_a_parent_with_visible_children_renders_a_dropdown(): void
    {
        $this->tree();
        $this->seedHomepage();

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('scbd-nav-has-children', $html);
        $this->assertStringContainsString('scbd-nav-menu', $html);
        $this->assertStringContainsString('Profile', $html);
    }

    public function test_a_parent_with_no_visible_children_renders_no_dropdown(): void
    {
        // Every child is a draft page, so there is nothing to drop down.
        $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'location' => MenuLocations::HEADER]);
        $parent = MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Company'], 'url' => '#about', 'sort' => 1]);
        MenuItem::create([
            'menu_id' => $menu->id, 'parent_id' => $parent->id,
            'label' => ['en' => 'Profile'], 'type' => MenuItem::TYPE_PAGE,
            'linkable_type' => Page::class, 'linkable_id' => $this->page('Profile', 'profile', Page::STATUS_DRAFT)->id,
        ]);
        $this->seedHomepage();

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('Company', $html);
        $this->assertStringNotContainsString('scbd-nav-has-children', $html);
    }

    public function test_the_dropdown_is_marked_up_for_assistive_technology(): void
    {
        $this->tree();
        $this->seedHomepage();

        $this->get('/')->assertSee('aria-haspopup="true"', false);
    }

    public function test_keys_are_positional_and_nested(): void
    {
        $this->tree();

        $keyed = MenuRenderer::withKeys();

        $this->assertSame('nav1', $keyed[0]['key']);
        $this->assertSame('nav1_1', $keyed[0]['children'][0]['key']);
    }

    public function test_child_labels_are_published_to_the_switcher(): void
    {
        // Without this a dropdown entry never changes language.
        $this->tree();
        $page = $this->seedHomepage();

        $payload = SiteTranslations::forPage($page, app(BlockRegistry::class));

        $this->assertSame('Profile', $payload['en']['nav1_1']);
        $this->assertSame('Profil', $payload['id']['nav1_1']);
    }

    public function test_every_nav_key_in_the_markup_resolves_in_every_locale(): void
    {
        $this->tree();
        $this->seedHomepage();

        $html = $this->get('/')->getContent();

        preg_match('/id="scbd-i18n">(.*?)<\/script>/s', $html, $matches);
        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
        preg_match_all('/data-i18n="(nav[\d_]*)"/', $html, $keys);

        $this->assertNotEmpty($keys[1]);

        foreach (array_keys($payload) as $locale) {
            $missing = array_diff(array_unique($keys[1]), array_keys($payload[$locale]));
            $this->assertSame([], array_values($missing), "[{$locale}] cannot translate: ".implode(', ', $missing));
        }
    }

    public function test_a_third_level_renders(): void
    {
        $menu = $this->tree();
        $company = $menu->items()->whereNull('parent_id')->first();
        $profile = $menu->items()->where('parent_id', $company->id)->first();

        MenuItem::create([
            'menu_id' => $menu->id, 'parent_id' => $profile->id, 'sort' => 1,
            'label' => ['en' => 'Deeper'], 'type' => MenuItem::TYPE_PAGE,
            'linkable_type' => Page::class, 'linkable_id' => $this->page('Deeper', 'deeper')->id,
        ]);
        $this->seedHomepage();

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('scbd-nav-menu-sub', $html);
        $this->assertStringContainsString('data-i18n="nav1_1_1"', $html);
    }

    public function test_the_call_to_action_stays_out_of_the_tree(): void
    {
        $menu = $this->tree();
        MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Enquire'], 'url' => '#c', 'is_cta' => true, 'sort' => 9]);
        $this->seedHomepage();

        $keyed = MenuRenderer::withKeys();

        $this->assertCount(1, $keyed);
        $this->assertSame('Company', $keyed[0]['item']->t('label', 'en'));
    }
}
