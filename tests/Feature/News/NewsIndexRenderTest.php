<?php

namespace Tests\Feature\News;

use AjayDhakal\FilamentStory\Models\BlogCategory;
use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\Page;
use App\PageBuilder\Blocks\NewsIndexBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class NewsIndexRenderTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHeaderMenu();
    }

    private function indexPage(array $data = []): Page
    {
        return Page::create([
            'title' => ['en' => 'News'],
            'slug' => 'news',
            'type' => Page::TYPE_BUILDER,
            'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => [[
                'id' => 'block_1',
                'type' => NewsIndexBlock::type(),
                'data' => array_merge([
                    'eyebrow' => ['en' => 'Newsroom'],
                    'heading' => ['en' => 'News'],
                    'empty_text' => ['en' => 'Nothing published yet.'],
                    'sidebar_heading' => ['en' => 'Flash news'],
                    'show_filters' => true,
                    'sidebar_limit' => 5,
                ], $data),
                'children' => null,
            ]],
        ]);
    }

    private function makePost(string $title, string $date, array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title),
            'content' => '<p>Body.</p>',
            'excerpt' => 'Summary.',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => $date,
        ], $overrides));
    }

    public function test_published_posts_are_listed_newest_first(): void
    {
        $this->indexPage();
        $this->makePost('Older Story', '2025-01-01');
        $this->makePost('Newer Story', '2026-07-23');

        $html = $this->get('/news')->assertSuccessful()->getContent();

        $this->assertLessThan(
            strpos($html, 'Older Story'),
            strpos($html, 'Newer Story'),
            'The newest post should appear before the older one.',
        );
    }

    public function test_a_draft_post_is_not_listed(): void
    {
        $this->indexPage();
        $this->makePost('Hidden Story', '2026-01-01', ['status' => BlogPost::STATUS_DRAFT]);

        $this->get('/news')->assertDontSee('Hidden Story', false);
    }

    public function test_a_post_dated_in_the_future_is_not_listed(): void
    {
        $this->indexPage();
        $this->makePost('Scheduled Story', now()->addWeek()->toDateString());

        $this->get('/news')->assertDontSee('Scheduled Story', false);
    }

    public function test_the_empty_state_shows_and_the_sidebar_is_omitted(): void
    {
        $this->indexPage();

        $this->get('/news')
            ->assertSuccessful()
            ->assertSee('scbd-news-index-empty', false)
            ->assertDontSee('scbd-news-index-side', false);
    }

    public function test_the_sidebar_honours_its_limit(): void
    {
        $this->indexPage(['sidebar_limit' => 2]);
        $this->makePost('One Story', '2026-01-01');
        $this->makePost('Two Story', '2026-02-01');
        $this->makePost('Three Story', '2026-03-01');

        $html = $this->get('/news')->getContent();

        // Three cards in the grid; two in the sidebar.
        $this->assertSame(2, substr_count($html, 'scbd-news-card-compact'));
        $this->assertSame(3, substr_count($html, 'scbd-news-card-grid'));
    }

    public function test_chips_are_rendered_for_categories_that_have_posts(): void
    {
        $this->indexPage();
        $used = BlogCategory::create(['name' => 'Environment']);
        BlogCategory::create(['name' => 'Unused Category']);
        $this->makePost('Earth Hour', '2026-03-28', ['blog_category_id' => $used->id]);

        $response = $this->get('/news')->assertSuccessful();

        $response->assertSee('data-news-filter-chip="environment"', false);
        // An empty chip is a dead end — it would filter to nothing.
        $response->assertDontSee('data-news-filter-chip="unused-category"', false);
    }

    public function test_the_all_chip_carries_an_empty_value(): void
    {
        $this->indexPage();
        $category = BlogCategory::create(['name' => 'Environment']);
        $this->makePost('Earth Hour', '2026-03-28', ['blog_category_id' => $category->id]);

        $this->get('/news')->assertSee('data-news-filter-chip=""', false);
    }

    public function test_chips_can_be_switched_off(): void
    {
        $this->indexPage(['show_filters' => false]);
        $category = BlogCategory::create(['name' => 'Environment']);
        $this->makePost('Earth Hour', '2026-03-28', ['blog_category_id' => $category->id]);

        $this->get('/news')->assertDontSee('data-news-filter-chip', false);
    }

    public function test_the_heading_carries_the_split_hook_and_an_i18n_key(): void
    {
        $this->indexPage();

        $this->get('/news')
            ->assertSee('data-split', false)
            ->assertSee('data-i18n="b1_heading"', false);
    }
}
