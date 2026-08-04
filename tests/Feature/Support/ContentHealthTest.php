<?php

namespace Tests\Feature\Support;

use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\User;
use App\Support\ContentHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContentHealthTest extends TestCase
{
    use RefreshDatabase;

    private ContentHealth $health;

    protected function setUp(): void
    {
        parent::setUp();
        $this->health = new ContentHealth;
    }

    public function test_it_counts_users(): void
    {
        User::factory()->count(3)->create();

        $this->assertSame(3, $this->health->users());
    }

    public function test_it_separates_published_from_pending_posts(): void
    {
        BlogPost::create(['title' => 'A', 'slug' => 'a', 'content' => 'x', 'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => now()]);
        BlogPost::create(['title' => 'B', 'slug' => 'b', 'content' => 'x', 'status' => BlogPost::STATUS_DRAFT]);
        BlogPost::create(['title' => 'C', 'slug' => 'c', 'content' => 'x', 'status' => BlogPost::STATUS_SCHEDULED, 'published_at' => now()->addDay()]);

        $this->assertSame(1, $this->health->publishedPosts());
        $this->assertSame(2, $this->health->pendingPosts());
    }

    public function test_zero_is_a_real_answer_not_a_missing_one(): void
    {
        // Zero means "nothing yet"; null means "cannot say". Conflating them
        // would make an empty install look broken.
        $this->assertSame(0, $this->health->menuItems());
    }

    public function test_it_counts_pages(): void
    {
        \App\Models\Page::create(['title' => ['en' => 'One'], 'slug' => 'one']);
        \App\Models\Page::create(['title' => ['en' => 'Two'], 'slug' => 'two']);

        $this->assertSame(2, $this->health->pages());
    }

    public function test_a_missing_table_reports_null_and_logs_once(): void
    {
        Log::spy();
        Schema::drop('menu_items');

        $this->assertNull($this->health->menuItems());
        $this->assertNull($this->health->menuItems());

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_locales_configured_follows_the_model_constant(): void
    {
        $this->assertSame(3, $this->health->localesConfigured());
    }
}
