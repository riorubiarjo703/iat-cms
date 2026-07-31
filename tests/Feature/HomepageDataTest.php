<?php

namespace Tests\Feature;

use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\DistrictPlace;
use App\Models\HomepageContent;
use App\Models\PublicMenuItem;
use App\Support\HomepageData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_on_a_completely_empty_database(): void
    {
        $data = HomepageData::build();

        $this->assertInstanceOf(HomepageContent::class, $data->content);
        $this->assertTrue($data->menu->isEmpty());
        $this->assertTrue($data->places->isEmpty());
        $this->assertNull($data->cta);
    }

    public function test_it_separates_nav_links_from_the_cta(): void
    {
        PublicMenuItem::create(['label' => ['en' => 'Company'], 'url' => '#about', 'sort' => 1]);
        PublicMenuItem::create(['label' => ['en' => 'Leasing enquiry'], 'url' => '#contact', 'sort' => 9, 'is_cta' => true]);

        $data = HomepageData::build();

        $this->assertCount(1, $data->menu);
        $this->assertSame('Leasing enquiry', $data->cta->t('label', 'en'));
    }

    public function test_it_excludes_inactive_places_and_respects_order(): void
    {
        DistrictPlace::create(['title' => ['en' => 'Second'], 'sort' => 2]);
        DistrictPlace::create(['title' => ['en' => 'First'], 'sort' => 1]);
        DistrictPlace::create(['title' => ['en' => 'Hidden'], 'sort' => 3, 'is_active' => false]);

        $titles = HomepageData::build()->places->map(fn ($p) => $p->t('title', 'en'));

        $this->assertSame(['First', 'Second'], $titles->all());
    }

    public function test_it_takes_at_most_three_published_posts_newest_first(): void
    {
        BlogPost::create(['title' => 'Oldest', 'slug' => 'oldest', 'content' => 'x', 'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => now()->subDays(5)]);
        BlogPost::create(['title' => 'Newest', 'slug' => 'newest', 'content' => 'x', 'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => now()]);
        BlogPost::create(['title' => 'Middle', 'slug' => 'middle', 'content' => 'x', 'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => now()->subDay()]);
        BlogPost::create(['title' => 'Fourth', 'slug' => 'fourth', 'content' => 'x', 'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => now()->subDays(9)]);
        // published_at is deliberately the most recent of all rows: a draft
        // that merely lacks a timestamp would sort itself out of the top
        // three regardless of the status filter, which would let this test
        // pass even if the ->where('status', ...) clause were removed.
        BlogPost::create(['title' => 'Draft', 'slug' => 'draft', 'content' => 'x', 'status' => BlogPost::STATUS_DRAFT, 'published_at' => now()->addDay()]);

        $titles = HomepageData::build()->posts->pluck('title');

        $this->assertSame(['Newest', 'Middle', 'Oldest'], $titles->all());
    }

    public function test_the_i18n_payload_covers_all_three_locales(): void
    {
        HomepageContent::singleton()->update([
            'hero_line' => ['en' => 'English', 'id' => 'Indonesia', 'cn' => '中文'],
        ]);

        $i18n = HomepageData::build()->i18n;

        $this->assertSame(['en', 'id', 'cn'], array_keys($i18n));
        $this->assertSame('English', $i18n['en']['heroline']);
        $this->assertSame('Indonesia', $i18n['id']['heroline']);
        $this->assertSame('中文', $i18n['cn']['heroline']);
    }

    public function test_the_payload_falls_back_to_english_per_key(): void
    {
        HomepageContent::singleton()->update([
            'hero_line' => ['en' => 'English line'],
            'contact_heading' => ['en' => 'Contact', 'id' => 'Kontak'],
        ]);

        $i18n = HomepageData::build()->i18n;

        $this->assertSame('English line', $i18n['cn']['heroline']);
        $this->assertSame('Kontak', $i18n['id']['contacth']);
    }

    public function test_newlines_become_br_tags_for_the_char_split(): void
    {
        HomepageContent::singleton()->update(['hero_line' => ['en' => "A district\nthat never\nclocks out"]]);

        $this->assertSame('A district<br>that never<br>clocks out', HomepageData::build()->i18n['en']['heroline']);
    }

    public function test_the_payload_escapes_html(): void
    {
        HomepageContent::singleton()->update(['hero_sub' => ['en' => '<script>alert(1)</script>']]);

        $this->assertStringNotContainsString('<script>', HomepageData::build()->i18n['en']['herosub']);
    }

    public function test_the_payload_includes_nav_links_and_the_cta(): void
    {
        PublicMenuItem::create(['label' => ['en' => 'Company', 'id' => 'Perusahaan'], 'url' => '#about', 'sort' => 1]);
        PublicMenuItem::create(['label' => ['en' => 'District', 'id' => 'Kawasan'], 'url' => '#district', 'sort' => 2]);
        PublicMenuItem::create(['label' => ['en' => 'Leasing enquiry', 'id' => 'Ajukan sewa'], 'url' => '#contact', 'sort' => 9, 'is_cta' => true]);

        $i18n = HomepageData::build()->i18n;

        $this->assertSame('Perusahaan', $i18n['id']['nav1']);
        $this->assertSame('Kawasan', $i18n['id']['nav2']);
        $this->assertSame('Ajukan sewa', $i18n['id']['cta']);
    }

    public function test_the_controller_returns_the_home_view_with_the_dto(): void
    {
        // The `home` view does not exist until Task 16, so assert on the
        // controller's return value rather than rendering the response. This
        // keeps Task 15 independently green.
        $view = (new \App\Http\Controllers\HomeController)();

        $this->assertSame('home', $view->name());
        $this->assertInstanceOf(HomepageData::class, $view->getData()['data']);
    }

    public function test_the_route_is_registered_and_points_at_the_controller(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('home');

        $this->assertNotNull($route);
        $this->assertSame('/', $route->uri());
        $this->assertSame(\App\Http\Controllers\HomeController::class, $route->getActionName());
    }
}
