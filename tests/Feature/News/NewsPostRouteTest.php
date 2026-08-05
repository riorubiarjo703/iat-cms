<?php

namespace Tests\Feature\News;

use AjayDhakal\FilamentStory\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class NewsPostRouteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHeaderMenu();
    }

    /** Every field the view needs, so a test can vary one thing at a time. */
    private function makePost(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'title' => 'A Community Service Programme',
            'slug' => 'a-community-service-programme',
            'content' => '<p>Body copy.</p>',
            'excerpt' => 'A short summary.',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_a_published_post_is_reachable(): void
    {
        $this->makePost();

        $this->get('/news/a-community-service-programme')
            ->assertSuccessful()
            ->assertSee('A Community Service Programme', false);
    }

    public function test_a_draft_post_is_not_found(): void
    {
        // 404 rather than 403: an unlisted URL must not confirm a post exists.
        $this->makePost(['status' => BlogPost::STATUS_DRAFT, 'published_at' => null]);

        $this->get('/news/a-community-service-programme')->assertNotFound();
    }

    public function test_a_post_dated_in_the_future_is_not_found(): void
    {
        // Marked published but scheduled. It looks live in the admin and must
        // not be reachable by URL yet.
        $this->makePost(['published_at' => now()->addWeek()]);

        $this->get('/news/a-community-service-programme')->assertNotFound();
    }

    public function test_a_post_with_the_scheduled_status_is_not_found(): void
    {
        $this->makePost([
            'status' => BlogPost::STATUS_SCHEDULED,
            'published_at' => now()->addWeek(),
        ]);

        $this->get('/news/a-community-service-programme')->assertNotFound();
    }

    public function test_an_unknown_slug_is_not_found(): void
    {
        $this->get('/news/nothing-here')->assertNotFound();
    }

    public function test_the_post_route_is_not_shadowed_by_the_page_catch_all(): void
    {
        // The catch-all only matches slugs without a slash, but the ordering is
        // load-bearing and worth pinning.
        $this->makePost();

        $this->assertSame(
            url('/news/a-community-service-programme'),
            route('news.show', 'a-community-service-programme'),
        );
    }
}
