<?php

namespace Tests\Feature\News;

use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\Page;
use App\PageBuilder\Blocks\NewsIndexBlock;
use Database\Seeders\NewsPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class NewsWireUpTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHeaderMenu();
    }

    public function test_the_package_blog_routes_are_gone(): void
    {
        // They render Tailwind-CDN views that look nothing like this site.
        $this->assertFalse(app('router')->has('filament-story.index'));
        $this->assertFalse(app('router')->has('filament-story.show'));

        $this->get('/blogs')->assertNotFound();
    }

    public function test_the_api_routes_are_untouched(): void
    {
        // A separate flag; nothing here should have disabled them.
        $this->getJson('/api/posts')->assertSuccessful();
    }

    public function test_the_homepage_news_block_links_to_the_new_pages(): void
    {
        BlogPost::create([
            'title' => 'Earth Hour 2026',
            'slug' => 'earth-hour-2026',
            'content' => '<p>Body.</p>',
            'excerpt' => 'Summary.',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => '2026-03-28',
        ]);

        $this->seedHomepage([[
            'id' => 'block_1',
            'type' => 'scbd_news',
            'data' => ['heading' => ['en' => 'News'], 'cta_label' => ['en' => 'All news'], 'limit' => 3],
            'children' => null,
        ]]);

        $this->get('/')
            ->assertSuccessful()
            ->assertSee('href="'.route('news.show', 'earth-hour-2026').'"', false)
            ->assertSee('href="'.route('page', 'news').'"', false);
    }

    public function test_the_seeder_publishes_a_news_page_carrying_the_index_block(): void
    {
        Page::create([
            'title' => ['en' => 'News'],
            'slug' => 'news',
            'type' => Page::TYPE_BUILDER,
            'status' => Page::STATUS_DRAFT,
        ]);

        $this->seed(NewsPageSeeder::class);

        $page = Page::where('slug', 'news')->firstOrFail();

        $this->assertSame(Page::STATUS_PUBLISHED, $page->status);
        $this->assertSame(NewsIndexBlock::type(), $page->blocks()[0]['type']);

        $this->get('/news')->assertSuccessful();
    }

    public function test_the_seeder_is_idempotent(): void
    {
        Page::create([
            'title' => ['en' => 'News'], 'slug' => 'news',
            'type' => Page::TYPE_BUILDER, 'status' => Page::STATUS_DRAFT,
        ]);

        $this->seed(NewsPageSeeder::class);
        $this->seed(NewsPageSeeder::class);

        $this->assertSame(1, Page::where('slug', 'news')->count());
        $this->assertCount(1, Page::where('slug', 'news')->firstOrFail()->blocks());
    }

    public function test_the_seeder_does_not_overwrite_an_edited_page(): void
    {
        // Once an editor has arranged the page, re-seeding must leave it alone.
        Page::create([
            'title' => ['en' => 'News'], 'slug' => 'news', 'type' => Page::TYPE_BUILDER,
            'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => [[
                'id' => 'block_1', 'type' => NewsIndexBlock::type(),
                'data' => ['heading' => ['en' => 'Newsroom']], 'children' => null,
            ]],
        ]);

        $this->seed(NewsPageSeeder::class);

        $this->assertSame(
            'Newsroom',
            Page::where('slug', 'news')->firstOrFail()->blocks()[0]['data']['heading']['en'],
        );
    }
}
