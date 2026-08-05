<?php

namespace Tests\Feature\News;

use AjayDhakal\FilamentStory\Models\BlogCategory;
use AjayDhakal\FilamentStory\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsCardTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'title' => 'Earth Hour 2026',
            'slug' => 'earth-hour-2026',
            'content' => '<p>Body.</p>',
            'excerpt' => 'Summary.',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => '2026-03-28',
        ], $overrides));
    }

    private function render(BlogPost $post, string $size): string
    {
        return view('partials.site.news-card', ['post' => $post, 'size' => $size])->render();
    }

    public function test_the_grid_card_links_to_the_post_and_shows_its_date(): void
    {
        $html = $this->render($this->makePost(), 'grid');

        $this->assertStringContainsString(route('news.show', 'earth-hour-2026'), $html);
        $this->assertStringContainsString('Earth Hour 2026', $html);
        $this->assertStringContainsString('28.03.26', $html);
    }

    public function test_a_card_carries_its_category_slug_for_the_filter(): void
    {
        $category = BlogCategory::create(['name' => 'Environment']);
        $post = $this->makePost(['blog_category_id' => $category->id]);

        $html = $this->render($post->fresh(), 'grid');

        $this->assertStringContainsString('data-news-category="environment"', $html);
        $this->assertStringContainsString('Environment', $html);
    }

    public function test_an_uncategorised_card_carries_an_empty_category(): void
    {
        // Still present, still empty: the filter reads the attribute on every
        // card, and a missing attribute would make an uncategorised post
        // invisible to "All" rather than merely unfiltered.
        $html = $this->render($this->makePost(), 'grid');

        $this->assertStringContainsString('data-news-category=""', $html);
    }

    public function test_a_post_without_an_image_renders_no_img_tag(): void
    {
        // An empty src resolves against the current page and re-requests it.
        $html = $this->render($this->makePost(), 'grid');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('src=""', $html);
    }

    public function test_the_compact_card_omits_the_category(): void
    {
        $category = BlogCategory::create(['name' => 'Environment']);
        $post = $this->makePost(['blog_category_id' => $category->id]);

        $html = $this->render($post->fresh(), 'compact');

        $this->assertStringContainsString('scbd-news-card-compact', $html);
        $this->assertStringContainsString('28.03.26', $html);
        $this->assertStringNotContainsString('scbd-news-card-category', $html);
    }

    public function test_thumbnails_are_lazy(): void
    {
        // Every published post renders at once, with no pagination.
        $post = $this->makePost(['featured_image' => 'uploads/news/earth-hour.jpg']);

        $html = $this->render($post, 'grid');

        $this->assertStringContainsString('loading="lazy"', $html);
    }
}
