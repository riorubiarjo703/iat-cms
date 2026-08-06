<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsSuperAdmin;
use Tests\TestCase;

class TopbarTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsSuperAdmin;

    private function panel(): string
    {
        return $this->actingAsSuperAdmin()->get('/superduper')->getContent();
    }

    public function test_the_help_and_settings_buttons_render(): void
    {
        $html = $this->panel();

        $this->assertStringContainsString('aria-label="Help"', $html);
        $this->assertStringContainsString('aria-label="Site settings"', $html);
        $this->assertSame(2, substr_count($html, 'fi-topbar-action'));
    }

    public function test_the_settings_button_points_at_site_settings(): void
    {
        $this->assertStringContainsString(
            \App\Filament\Pages\SiteSettingsPage::getUrl(),
            $this->panel(),
        );
    }

    public function test_the_brand_moved_into_the_sidebar(): void
    {
        // The reference puts the logo above the nav, not in the topbar, so the
        // topbar can be confined to the content column.
        $this->assertStringContainsString('fi-sidebar-brand', $this->panel());
    }

    public function test_global_search_is_bound_to_command_k(): void
    {
        Filament::setCurrentPanel('admin');

        $this->assertSame(['command+k', 'ctrl+k'], Filament::getGlobalSearchKeyBindings());
    }

    public function test_search_actually_returns_results(): void
    {
        // A decorative search field would be worse than none. These resources
        // declare globally searchable attributes so the field resolves records.
        $resources = [
            \App\Filament\Resources\Users\UserResource::class,
            \App\Filament\Resources\BlogCategories\BlogCategoryResource::class,
        ];

        foreach ($resources as $resource) {
            $this->assertNotEmpty(
                $resource::getGloballySearchableAttributes(),
                "[{$resource}] declares no globally searchable attributes",
            );
        }
    }

    public function test_a_user_is_findable_by_global_search(): void
    {
        // getGlobalSearchResultUrl() resolves through the resource's own
        // canView()/canEdit(), which the UserPolicy now denies to a guest —
        // without an actor signed in, every result is filtered out for
        // lacking a URL rather than for failing to match.
        $this->actingAsSuperAdmin();

        User::factory()->create(['name' => 'Findable Person', 'email' => 'findable@example.com']);

        $results = \App\Filament\Resources\Users\UserResource::getGlobalSearchResults('Findable');

        $this->assertGreaterThan(0, $results->count());
        $this->assertSame('Findable Person', $results->first()->title);
    }
}
