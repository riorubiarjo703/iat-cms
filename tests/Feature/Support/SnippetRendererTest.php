<?php

namespace Tests\Feature\Support;

use App\Enums\SnippetPosition;
use App\Models\CodeSnippet;
use App\Models\User;
use App\Support\SnippetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
     * Equal priorities fall back to id order — the ordering an editor
     * actually sees when two snippets tie on priority.
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

    /**
     * The test above cannot actually prove `->orderBy('id')` does anything.
     * `id` is an `INTEGER PRIMARY KEY`, which sqlite treats as an alias for
     * `rowid`, and a rowid table is physically a B-tree keyed by rowid — a
     * scan returns ascending id order whether the query asks for it or not,
     * regardless of insertion sequence or what ids the rows are given.
     * (Verified: forceCreating id 30, then 10, then 20 and reading back with
     * `->orderBy('id')` removed still returned 10, 20, 30 — an output-order
     * assertion cannot tell the two states apart on sqlite, which is exactly
     * how this clause went uncaught during mutation testing before.)
     * Asserting on the generated SQL is the only way to close that gap for
     * real, independent of how any particular engine happens to break ties.
     */
    public function test_the_query_orders_by_priority_then_id(): void
    {
        DB::enableQueryLog();

        CodeSnippet::factory()->create(['priority' => 10]);

        $this->renderer()->for(SnippetPosition::Head);

        $sql = collect(DB::getQueryLog())->last()['query'];

        $this->assertStringContainsString('order by "priority" asc, "id" asc', $sql);
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
