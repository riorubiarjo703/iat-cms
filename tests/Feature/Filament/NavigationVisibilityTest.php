<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\QuickActions;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function sidebarFor(string $role): string
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $this->actingAs($user)->get('/superduper')->assertSuccessful()->getContent();
    }

    /**
     * The Quick Actions widget's own markup, bounded so an assertion here
     * cannot pass because of an unrelated match elsewhere on the dashboard
     * (the sidebar, another widget). No nested <div> sits inside the actions
     * list itself, so the first </div> after it is the list's own close.
     */
    private function quickActionsPanel(string $html): string
    {
        $start = strpos($html, 'Quick actions');
        $this->assertNotFalse($start, 'Quick Actions widget did not render');

        $listStart = strpos($html, 'scbd-actions', $start);
        $end = strpos($html, '</div>', $listStart);
        $this->assertNotFalse($end, 'Quick Actions panel is not closed');

        return substr($html, $start, $end - $start);
    }

    public function test_a_content_editor_sees_the_content_group_and_navigation_menus(): void
    {
        $html = $this->sidebarFor('content_editor');

        $this->assertStringContainsString('Posts', $html);
        $this->assertStringContainsString('Categories', $html);
        $this->assertStringContainsString('Navigation Menus', $html);
    }

    /**
     * Checked against rendered navigation rather than policy return values:
     * the NavigationBuilder is exactly what bypasses Filament's policy-based
     * hiding, so a passing policy test proves nothing about the sidebar.
     */
    public function test_a_content_editor_does_not_see_what_it_cannot_reach(): void
    {
        $html = $this->sidebarFor('content_editor');

        $this->assertStringNotContainsString('Code Snippets', $html);
        $this->assertStringNotContainsString('Site Settings', $html);
        $this->assertStringNotContainsString('Backups', $html);
        $this->assertStringNotContainsString('Roles', $html);
    }

    /**
     * An empty disclosure triangle is worse than no entry: it invites a click
     * that reveals nothing.
     */
    public function test_a_parent_with_no_visible_children_is_removed(): void
    {
        $html = $this->sidebarFor('content_editor');

        $this->assertStringNotContainsString('Marketing', $html);
        $this->assertStringNotContainsString('Users Management', $html);
    }

    public function test_a_super_admin_sees_everything(): void
    {
        $html = $this->sidebarFor('super_admin');

        $this->assertStringContainsString('Code Snippets', $html);
        $this->assertStringContainsString('Users Management', $html);
        $this->assertStringContainsString('Marketing', $html);
    }

    /**
     * The sidebar isn't the only place a destination can leak: the dashboard's
     * Quick Actions widget links straight to Site Settings with no permission
     * check of its own, so an editor got a working-looking button for a
     * screen that 403s — the same defect this task exists to fix, one widget
     * over. Scoped to the widget's own markup so this cannot pass by
     * accident because the string is absent from the sidebar for some
     * unrelated reason.
     */
    public function test_a_content_editor_does_not_see_the_site_settings_quick_action(): void
    {
        $html = $this->sidebarFor('content_editor');

        $this->assertStringNotContainsString('Site Settings', $this->quickActionsPanel($html));
    }

    public function test_a_super_admin_sees_the_site_settings_quick_action(): void
    {
        $html = $this->sidebarFor('super_admin');

        $this->assertStringContainsString('Site Settings', $this->quickActionsPanel($html));
    }

    /**
     * A role holding none of the widget's six destination permissions would
     * otherwise get an empty card — the same "invites a click that reveals
     * nothing" problem the brief calls out for parent nav items with no
     * visible children. Exercised directly against the widget rather than
     * over HTTP: no seeded role currently holds zero of the six permissions
     * (every role that clears the admin.access panel gate holds at least
     * one), so this is the only way to prove the empty case without
     * inventing a role that doesn't otherwise exist.
     */
    public function test_the_quick_actions_widget_renders_nothing_for_a_user_who_can_reach_none_of_its_destinations(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertSame([], QuickActions::actions());
        $this->assertFalse(QuickActions::canView());
    }

    /**
     * Mirrors self::parent()'s rule one level up: NavigationGroup has no
     * visible()/hidden() of its own, so an empty group would otherwise still
     * render its header with nothing beneath it.
     */
    public function test_a_content_editor_has_no_empty_groups_left(): void
    {
        $user = User::factory()->create();
        $user->assignRole('content_editor');
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');

        $labels = [];

        foreach (Filament::getNavigation() as $group) {
            $this->assertNotSame([], $group->getItems(), "[{$group->getLabel()}] rendered with no items");
            $labels[] = $group->getLabel();
        }

        // Every one of "System"'s four entries maps to a permission
        // content_editor lacks, so the group itself must be gone, not just
        // hollow.
        $this->assertNotContains('System', $labels);
    }
}
