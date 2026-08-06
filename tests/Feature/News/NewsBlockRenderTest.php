<?php

namespace Tests\Feature\News;

use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\Page;
use App\PageBuilder\Blocks\NewsBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

/**
 * The homepage News block.
 *
 * Its rows link to route('news.show'), whose controller only serves posts that
 * are published AND dated at or before now — so this block has to agree about
 * what "published" means, or the top row of the homepage points at a 404.
 */
class NewsBlockRenderTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHeaderMenu();
    }

    private function blockPage(): Page
    {
        return Page::create([
            'title' => ['en' => 'Home'],
            'slug' => 'home',
            'type' => Page::TYPE_BUILDER,
            'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => [[
                'id' => 'block_1',
                'type' => NewsBlock::type(),
                'data' => [
                    'eyebrow' => ['en' => 'Newsroom'],
                    'heading' => ['en' => 'News'],
                    'cta_label' => ['en' => 'All news'],
                    'empty_text' => ['en' => 'Nothing published yet.'],
                    'limit' => 3,
                ],
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
        $this->blockPage();
        $this->makePost('Older Story', '2025-01-01');
        $this->makePost('Newer Story', '2026-07-23');

        $html = $this->get('/home')->assertSuccessful()->getContent();

        $this->assertLessThan(
            strpos($html, 'Older Story'),
            strpos($html, 'Newer Story'),
        );
    }

    public function test_a_post_dated_in_the_future_is_not_shown(): void
    {
        // published_at DESC puts a scheduled post FIRST, so without the date
        // guard the very top row of the homepage links to a URL that 404s.
        $this->blockPage();
        $this->makePost('Live Story', '2026-01-01');
        $this->makePost('Scheduled Story', now()->addWeek()->toDateString());

        $this->get('/home')
            ->assertSuccessful()
            ->assertSee('Live Story', false)
            ->assertDontSee('Scheduled Story', false);
    }

    public function test_a_draft_post_is_not_shown(): void
    {
        $this->blockPage();
        $this->makePost('Hidden Story', '2026-01-01', ['status' => BlogPost::STATUS_DRAFT]);

        $this->get('/home')->assertDontSee('Hidden Story', false);
    }
}
