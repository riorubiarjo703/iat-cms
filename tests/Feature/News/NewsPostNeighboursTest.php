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

    /**
     * Three posts sharing one timestamp, plus a distinct one either side.
     *
     * The importer stores date-only values — every time is 00:00:00 — so any
     * two posts published on the same day collide. Ordered by published_at
     * alone that is not a total order, and a `<` / `>` comparison makes the
     * colliding posts mutually invisible: each is excluded from the other's
     * neighbour query, so prev/next steps straight over them.
     *
     * @return array{BlogPost, BlogPost, BlogPost}
     */
    private function seedSameDayRun(): array
    {
        $this->makePost('Day Before', '2025-03-09');
        $first = $this->makePost('Same Day One', '2025-03-10');
        $second = $this->makePost('Same Day Two', '2025-03-10');
        $third = $this->makePost('Same Day Three', '2025-03-10');
        $this->makePost('Day After', '2025-03-11');

        return [$first, $second, $third];
    }

    public function test_previous_finds_the_sibling_sharing_a_published_at(): void
    {
        $this->seedSameDayRun();

        // Not 'Day Before': the post immediately behind the middle of the run
        // is its own same-day sibling, reachable only once the ordering breaks
        // the tie on the primary key.
        $this->get('/news/same-day-two')
            ->assertSuccessful()
            ->assertViewHas('previous', fn ($p) => $p?->title === 'Same Day One');
    }

    public function test_next_finds_the_sibling_sharing_a_published_at(): void
    {
        $this->seedSameDayRun();

        $this->get('/news/same-day-two')
            ->assertViewHas('next', fn ($n) => $n?->title === 'Same Day Three');
    }

    public function test_the_first_of_a_same_day_run_still_reaches_the_previous_day(): void
    {
        $this->seedSameDayRun();

        // The tie-break must not strand the ends of the run: the earliest
        // same-day post still has to fall through to the day before.
        $this->get('/news/same-day-one')
            ->assertViewHas('previous', fn ($p) => $p?->title === 'Day Before');
    }

    public function test_the_last_of_a_same_day_run_still_reaches_the_next_day(): void
    {
        $this->seedSameDayRun();

        $this->get('/news/same-day-three')
            ->assertViewHas('next', fn ($n) => $n?->title === 'Day After');
    }

    public function test_walking_next_then_previous_returns_to_the_same_post(): void
    {
        $this->seedSameDayRun();

        // next() and previous() must be inverses of one and the same ordering.
        // Two orderings that disagree would each answer plausibly on their own
        // while the round trip landed somewhere else.
        foreach (['day-before', 'same-day-one', 'same-day-two', 'same-day-three'] as $slug) {
            $next = $this->get("/news/{$slug}")->viewData('next');

            $this->assertNotNull($next, "'{$slug}' has no next post to walk to");

            $this->get("/news/{$next->slug}")
                ->assertViewHas(
                    'previous',
                    fn ($p) => $p?->slug === $slug,
                );
        }
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
