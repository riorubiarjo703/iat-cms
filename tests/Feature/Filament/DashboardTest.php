<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\QuickActions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['name' => 'Rio Rubiarjo']));
    }

    /**
     * The real request, not Livewire::test(). A widget whose view data never
     * arrives still passes assertOk() — the undefined variables surface only
     * as warnings there, while the actual page returns 500.
     */
    public function test_the_dashboard_renders_on_a_fresh_database(): void
    {
        $this->get('/superduper')->assertSuccessful();
    }

    public function test_every_widget_actually_emits_its_content(): void
    {
        // Guards the same failure from the other side: a widget can render an
        // empty shell when getViewData() is spelled wrong.
        $html = $this->get('/superduper')->getContent();

        foreach ([
            'scbd-banner' => 'welcome banner',
            'scbd-stats' => 'stat cards',
            'scbd-coverage' => 'translation coverage',
            'scbd-actions' => 'quick actions',
        ] as $marker => $region) {
            $this->assertStringContainsString($marker, $html, "The {$region} did not render");
        }
    }

    public function test_the_banner_greets_the_user_by_first_name(): void
    {
        $this->get('/superduper')->assertSee('Rio', false);
    }

    public function test_the_banner_greeting_matches_the_application_timezone(): void
    {
        // Not the server's clock — the greeting is for the reader.
        $hour = (int) now()->setTimezone(config('app.timezone'))->format('G');
        $expected = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        $this->get('/superduper')->assertSee($expected, false);
    }

    public function test_the_stat_cards_show_real_counts(): void
    {
        User::factory()->count(2)->create();

        // 3 users total: the acting user plus two.
        $html = $this->get('/superduper')->getContent();
        $stats = substr($html, strpos($html, 'scbd-stats'), 6000);

        $this->assertStringContainsString('>3', preg_replace('/\s+/', '', $stats));
    }

    public function test_every_quick_action_resolves_to_a_reachable_page(): void
    {
        // The assertion that catches a link pointing at nothing.
        foreach (QuickActions::actions() as $action) {
            $this->assertNotEmpty($action['url'], "[{$action['label']}] has no URL");
            $this->get($action['url'])->assertSuccessful();
        }
    }

    public function test_the_dashboard_drops_filaments_own_promo_widget(): void
    {
        // FilamentInfoWidget advertises the framework; it is not part of this
        // product's dashboard.
        $this->get('/superduper')->assertDontSee('fi-widget-filament-info', false);
    }
}
