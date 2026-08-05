<?php

namespace Tests\Feature\News;

use AjayDhakal\FilamentStory\Models\BlogCategory;
use AjayDhakal\FilamentStory\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class NewsDetailPageTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHeaderMenu();
    }

    private function makePost(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'title' => 'Earth Hour 2026',
            'slug' => 'earth-hour-2026',
            'content' => '<p>Body copy.</p>',
            'excerpt' => 'Summary.',
            'featured_image' => 'uploads/news/earth-hour.jpg',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => '2026-03-28',
        ], $overrides));
    }

    public function test_the_breadcrumb_leads_back_to_the_index(): void
    {
        $this->makePost();

        $this->get('/news/earth-hour-2026')
            ->assertSuccessful()
            ->assertSee('href="'.route('page', 'news').'"', false);
    }

    public function test_the_heading_carries_the_split_hook(): void
    {
        // Without data-split the heading renders and never becomes visible.
        $this->makePost();

        $this->get('/news/earth-hour-2026')->assertSee('data-split', false);
    }

    public function test_the_hero_image_carries_the_reveal_hook(): void
    {
        $this->makePost();

        $this->get('/news/earth-hour-2026')->assertSee('data-reveal', false);
    }

    public function test_the_share_links_point_at_the_canonical_post_url(): void
    {
        $this->makePost();
        $encoded = urlencode(route('news.show', 'earth-hour-2026'));

        $response = $this->get('/news/earth-hour-2026')->assertSuccessful();

        $response->assertSee('linkedin.com/sharing/share-offsite/?url='.$encoded, false);
        $response->assertSee('facebook.com/sharer/sharer.php?u='.$encoded, false);
        $response->assertSee('twitter.com/intent/tweet?url='.$encoded, false);
    }

    public function test_the_body_renders_as_html_inside_the_prose_wrapper(): void
    {
        $this->makePost(['content' => '<p>First paragraph.</p><p>Second paragraph.</p>']);

        $this->get('/news/earth-hour-2026')
            ->assertSee('scbd-prose', false)
            ->assertSee('<p>Second paragraph.</p>', false);
    }

    public function test_the_category_shows_beside_the_date(): void
    {
        $category = BlogCategory::create(['name' => 'Environment']);
        $this->makePost(['blog_category_id' => $category->id]);

        $this->get('/news/earth-hour-2026')
            ->assertSee('28.03.26', false)
            ->assertSee('Environment', false);
    }

    public function test_prev_and_next_render_their_neighbours_titles(): void
    {
        $this->makePost();
        BlogPost::create([
            'title' => 'Older Story', 'slug' => 'older-story', 'content' => '<p>x</p>',
            'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => '2025-01-01',
        ]);
        BlogPost::create([
            'title' => 'Newer Story', 'slug' => 'newer-story', 'content' => '<p>x</p>',
            'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => '2026-07-23',
        ]);

        $this->get('/news/earth-hour-2026')
            ->assertSee('Older Story', false)
            ->assertSee('Newer Story', false);
    }

    public function test_a_lone_post_renders_no_prev_or_next_affordance(): void
    {
        $this->makePost();

        $this->get('/news/earth-hour-2026')
            ->assertDontSee('scbd-news-nav-prev', false)
            ->assertDontSee('scbd-news-nav-next', false);
    }

    public function test_the_latest_row_is_omitted_when_there_are_no_other_posts(): void
    {
        // A heading over an empty row reads as a rendering fault.
        $this->makePost();

        $this->get('/news/earth-hour-2026')->assertDontSee('LATEST NEWS', false);
    }

    public function test_the_latest_row_appears_when_other_posts_exist(): void
    {
        $this->makePost();
        BlogPost::create([
            'title' => 'Another Story', 'slug' => 'another-story', 'content' => '<p>x</p>',
            'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => '2025-01-01',
        ]);

        $this->get('/news/earth-hour-2026')
            ->assertSee('LATEST NEWS', false)
            ->assertSee('Another Story', false);
    }
}
