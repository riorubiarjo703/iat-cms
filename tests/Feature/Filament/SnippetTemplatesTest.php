<?php

namespace Tests\Feature\Filament;

use App\Enums\SnippetPosition;
use App\Filament\Resources\CodeSnippets\Pages\ListCodeSnippets;
use App\Models\CodeSnippet;
use App\Models\User;
use App\Support\SnippetTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SnippetTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_all_six_templates_are_defined(): void
    {
        $this->assertSame(
            ['gtm', 'ga4', 'meta_pixel', 'crisp', 'custom_css', 'custom_js'],
            array_keys(SnippetTemplates::all()),
        );
    }

    /**
     * Every tracking template ships with a placeholder id. If templates created
     * active snippets, one click would inject a broken tag into every page of
     * the live site before the operator could type their real id.
     */
    public function test_a_template_creates_its_snippet_switched_off(): void
    {
        Livewire::test(ListCodeSnippets::class)->call('applyTemplate', 'ga4');

        $snippet = CodeSnippet::query()->sole();

        $this->assertFalse($snippet->is_active);
        $this->assertSame(SnippetPosition::Head, $snippet->position);
    }

    public function test_google_tag_manager_creates_two_snippets_in_two_positions(): void
    {
        Livewire::test(ListCodeSnippets::class)->call('applyTemplate', 'gtm');

        $snippets = CodeSnippet::query()->orderBy('id')->get();

        $this->assertCount(2, $snippets);
        $this->assertSame(SnippetPosition::Head, $snippets[0]->position);
        $this->assertSame(SnippetPosition::BodyStart, $snippets[1]->position);
        $this->assertFalse($snippets[0]->is_active);
        $this->assertFalse($snippets[1]->is_active);
    }

    public function test_an_unknown_template_key_creates_nothing(): void
    {
        Livewire::test(ListCodeSnippets::class)->call('applyTemplate', 'nope');

        $this->assertSame(0, CodeSnippet::query()->count());
    }
}
