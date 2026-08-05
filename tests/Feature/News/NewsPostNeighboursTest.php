<?php

namespace Tests\Feature\News;

use AjayDhakal\FilamentStory\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class NewsPostNeighboursTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHeaderMenu();
    }

    private function makePost(string $title, string $date): BlogPost
    {
        return BlogPost::create([
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title),
            'content' => '<p>Body.</p>',
            'excerpt' => 'Summary.',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => $date,
        ]);
    }

    /** Oldest to newest. */
    private function seedFive(): void
    {
        $this->makePost('Oldest Post', '2024-05-14');
        $this->makePost('Second Post', '2024-06-26');
        $this->makePost('Middle Post', '2025-02-11');
        $this->makePost('Fourth Post', '2025-08-17');
        $this->makePost('Newest Post', '2026-07-23');
    }

    public function test_previous_is_the_newest_older_post(): void
    {
        $this->seedFive();

        $this->get('/news/middle-post')
            ->assertSuccessful()
            ->assertViewHas('previous', fn ($p) => $p?->title === 'Second Post');
    }

    public function test_next_is_the_oldest_newer_post(): void
    {
        $this->seedFive();

        $this->get('/news/middle-post')
            ->assertViewHas('next', fn ($n) => $n?->title === 'Fourth Post');
    }

    public function test_the_oldest_post_has_no_previous(): void
    {
        $this->seedFive();

        // A closure, not null: assertViewHas($key, null) only asserts the key
        // exists, so it would pass with a populated neighbour.
        $this->get('/news/oldest-post')->assertViewHas('previous', fn ($p) => $p === null);
    }

    public function test_the_newest_post_has_no_next(): void
    {
        $this->seedFive();

        $this->get('/news/newest-post')->assertViewHas('next', fn ($n) => $n === null);
    }

    public function test_neighbours_skip_unpublished_posts(): void
    {
        $this->seedFive();
        BlogPost::where('slug', 'fourth-post')->update(['status' => BlogPost::STATUS_DRAFT]);

        // With Fourth drafted, Middle's next must jump to Newest rather than
        // pointing at a URL that 404s.
        $this->get('/news/middle-post')
            ->assertViewHas('next', fn ($n) => $n?->title === 'Newest Post');
    }

    public function test_latest_excludes_the_post_being_viewed(): void
    {
        $this->seedFive();

        $this->get('/news/newest-post')
            ->assertViewHas('latest', fn ($latest) => $latest
                ->pluck('slug')
                ->doesntContain('newest-post'));
    }

    public function test_latest_is_capped_at_four_newest_first(): void
    {
        $this->seedFive();

        $this->get('/news/oldest-post')
            ->assertViewHas('latest', fn ($latest) => $latest->count() === 4
                && $latest->first()->title === 'Newest Post');
    }

    public function test_latest_omits_unpublished_posts(): void
    {
        $this->seedFive();
        BlogPost::where('slug', 'newest-post')->update(['published_at' => now()->addYear()]);

        $this->get('/news/oldest-post')
            ->assertViewHas('latest', fn ($latest) => $latest
                ->pluck('slug')
                ->doesntContain('newest-post'));
    }
}
