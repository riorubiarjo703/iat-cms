<?php

namespace Tests\Feature\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsSuperAdmin;
use Tests\TestCase;

class PublishStateTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsSuperAdmin;

    private function page(array $attributes = []): Page
    {
        return Page::create(array_merge([
            'title' => ['en' => 'Profile'],
            'slug' => 'profile',
            'type' => Page::TYPE_BUILDER,
            'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => [],
        ], $attributes));
    }

    public function test_the_site_timezone_is_not_utc(): void
    {
        // With UTC, a time typed in Jakarta was stored seven hours ahead, so a
        // page published "now" was silently scheduled for the evening.
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }

    public function test_publishing_with_the_current_time_makes_the_page_reachable(): void
    {
        // This is the exact case that produced a 404 while the admin said the
        // page was published.
        $this->page(['published_at' => now()]);

        $this->get('/profile')->assertSuccessful();
    }

    public function test_a_future_publish_time_is_reported_as_scheduled_not_published(): void
    {
        $page = $this->page(['published_at' => now()->addHours(3)]);

        $this->assertTrue($page->isScheduled());
        $this->assertFalse($page->isPublished());
    }

    public function test_a_past_publish_time_is_not_scheduled(): void
    {
        $page = $this->page(['published_at' => now()->subMinute()]);

        $this->assertFalse($page->isScheduled());
        $this->assertTrue($page->isPublished());
    }

    public function test_a_draft_is_never_scheduled(): void
    {
        $page = $this->page(['status' => Page::STATUS_DRAFT, 'published_at' => now()->addDay()]);

        $this->assertFalse($page->isScheduled());
    }

    public function test_the_pages_table_shows_scheduled_rather_than_published(): void
    {
        $this->actingAsSuperAdmin();
        $this->page(['published_at' => now()->addDay()]);

        $this->get(PageResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('scheduled');
    }

    public function test_a_live_page_still_reads_as_published(): void
    {
        $this->actingAsSuperAdmin();
        $this->page(['published_at' => now()->subDay()]);

        $this->get(PageResource::getUrl('index'))
            ->assertSuccessful()
            ->assertDontSee('scheduled');
    }
}
