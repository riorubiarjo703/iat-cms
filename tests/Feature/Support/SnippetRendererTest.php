<?php

namespace Tests\Feature\Support;

use App\Enums\SnippetPosition;
use App\Models\CodeSnippet;
use App\Models\User;
use App\Support\SnippetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnippetRendererTest extends TestCase
{
    use RefreshDatabase;

    private function renderer(): SnippetRenderer
    {
        return app(SnippetRenderer::class);
    }

    public function test_it_returns_only_snippets_for_the_requested_position(): void
    {
        CodeSnippet::factory()->create(['name' => 'In head', 'position' => SnippetPosition::Head]);
        CodeSnippet::factory()->create(['name' => 'At body end', 'position' => SnippetPosition::BodyEnd]);

        $this->assertSame(
            ['In head'],
            $this->renderer()->for(SnippetPosition::Head)->pluck('name')->all(),
        );
    }

    public function test_it_excludes_inactive_snippets(): void
    {
        CodeSnippet::factory()->create(['name' => 'On', 'is_active' => true]);
        CodeSnippet::factory()->create(['name' => 'Off', 'is_active' => false]);

        $this->assertSame(
            ['On'],
            $this->renderer()->for(SnippetPosition::Head)->pluck('name')->all(),
        );
    }

    public function test_lower_priority_renders_first(): void
    {
        CodeSnippet::factory()->create(['name' => 'Last', 'priority' => 90]);
        CodeSnippet::factory()->create(['name' => 'First', 'priority' => 1]);

        $this->assertSame(
            ['First', 'Last'],
            $this->renderer()->for(SnippetPosition::Head)->pluck('name')->all(),
        );
    }

    /**
     * Equal priorities fall back to insertion order rather than whatever order
     * the database happens to return, so a page's markup does not reshuffle
     * between requests.
     */
    public function test_equal_priorities_fall_back_to_creation_order(): void
    {
        CodeSnippet::factory()->create(['name' => 'One', 'priority' => 10]);
        CodeSnippet::factory()->create(['name' => 'Two', 'priority' => 10]);
        CodeSnippet::factory()->create(['name' => 'Three', 'priority' => 10]);

        $this->assertSame(
            ['One', 'Two', 'Three'],
            $this->renderer()->for(SnippetPosition::Head)->pluck('name')->all(),
        );
    }

    public function test_admin_skipping_snippets_are_hidden_from_authenticated_users(): void
    {
        CodeSnippet::factory()->create(['name' => 'Tracking', 'skip_for_admins' => true]);
        CodeSnippet::factory()->create(['name' => 'Always', 'skip_for_admins' => false]);

        $this->actingAs(User::factory()->create());

        $this->assertSame(
            ['Always'],
            $this->renderer()->for(SnippetPosition::Head)->pluck('name')->all(),
        );
    }

    public function test_admin_skipping_snippets_render_for_guests(): void
    {
        CodeSnippet::factory()->create(['name' => 'Tracking', 'skip_for_admins' => true]);
        CodeSnippet::factory()->create(['name' => 'Always', 'skip_for_admins' => false]);

        $this->assertSame(
            ['Tracking', 'Always'],
            $this->renderer()->for(SnippetPosition::Head)->pluck('name')->all(),
        );
    }

    public function test_a_position_with_no_snippets_returns_an_empty_collection(): void
    {
        $this->assertTrue($this->renderer()->for(SnippetPosition::BodyStart)->isEmpty());
    }
}
