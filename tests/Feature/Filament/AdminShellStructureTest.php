<?php

namespace Tests\Feature\Filament;

use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShellStructureTest extends TestCase
{
    use RefreshDatabase;

    private function panel(): string
    {
        return $this->actingAs(User::factory()->create())->get('/superduper')->getContent();
    }

    /**
     * The first flyout panel, bounded by its own markup. A fixed-size window
     * silently truncated this at 4000 chars while the panel is ~8400.
     */
    private function firstFlyout(?string $html = null): string
    {
        $html ??= $this->panel();
        $start = strpos($html, 'class="fi-sidebar-flyout"');
        $this->assertNotFalse($start, 'No flyout panel rendered');

        $end = strpos($html, '</template>', $start);
        $this->assertNotFalse($end, 'Flyout panel is not closed by its teleport template');

        return substr($html, $start, $end - $start);
    }

    public function test_the_topbar_renders_inside_the_content_column(): void
    {
        // The requested design is two columns with the bar belonging to the
        // content column. Upstream renders it before .fi-layout, spanning the
        // sidebar too — margin offsets could hide that, but not fix it.
        $html = $this->panel();

        $mainCtn = strpos($html, 'fi-main-ctn');
        $topbar = strpos($html, 'fi-topbar-ctn');

        $this->assertNotFalse($mainCtn);
        $this->assertNotFalse($topbar);
        $this->assertGreaterThan($mainCtn, $topbar, 'The topbar renders before .fi-main-ctn, so it is not inside the content column');
    }

    public function test_the_topbar_is_not_a_sibling_of_the_layout(): void
    {
        $html = $this->panel();

        $this->assertGreaterThan(
            strpos($html, 'fi-layout'),
            strpos($html, 'fi-topbar-ctn'),
            'The topbar still precedes .fi-layout, which is the upstream full-viewport position',
        );
    }

    public function test_the_chevron_is_hidden_while_the_sidebar_is_collapsed(): void
    {
        // Collapsed there is no room to expand in place, so a chevron would
        // point at something that cannot happen.
        $html = $this->panel();

        $chevron = substr($html, strpos($html, 'fi-sidebar-item-chevron'), 400);

        $this->assertStringContainsString('$store.sidebar.isOpen', $chevron);
    }

    public function test_parents_with_children_render_a_flyout(): void
    {
        $html = $this->panel();

        $this->assertStringContainsString('fi-sidebar-flyout', $html);
        $this->assertSame(6, substr_count($html, 'class="fi-sidebar-flyout"'), 'Expected one flyout per expandable parent');
    }

    public function test_the_flyout_is_teleported_out_of_the_sidebar(): void
    {
        // The sidebar clips overflow and sits under a transformed ancestor;
        // either traps a position:fixed child, so the panel rendered at full
        // size and was never painted.
        $html = $this->panel();

        $flyout = strpos($html, 'class="fi-sidebar-flyout"');
        $before = substr($html, max(0, $flyout - 500), 500);

        $this->assertStringContainsString('x-teleport="body"', $before);
    }

    public function test_the_flyout_lists_the_same_children_as_the_expanded_parent(): void
    {
        $html = $this->panel();

        $panel = $this->firstFlyout($html);

        foreach (['Posts', 'Pages', 'Content Blocks', 'Categories', 'Comments', 'Media Library'] as $child) {
            $this->assertStringContainsString($child, $panel, "[{$child}] is missing from the Content flyout");
        }
    }

    public function test_the_flyout_header_totals_its_childrens_badges(): void
    {
        BlogPost::create(['title' => 'A', 'slug' => 'a', 'content' => 'x', 'status' => BlogPost::STATUS_DRAFT]);
        BlogPost::create(['title' => 'B', 'slug' => 'b', 'content' => 'x', 'status' => BlogPost::STATUS_DRAFT]);

        $panel = $this->firstFlyout();

        $this->assertStringContainsString('(2)', $panel);
    }

    public function test_the_flyout_header_shows_no_total_when_nothing_is_pending(): void
    {
        // A bare "(0)" beside an empty feature would be inventing a metric.
        $this->assertStringNotContainsString('fi-sidebar-flyout-count', $this->firstFlyout());
    }
}
