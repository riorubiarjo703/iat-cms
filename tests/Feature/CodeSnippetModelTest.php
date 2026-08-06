<?php

namespace Tests\Feature;

use App\Enums\SnippetPosition;
use App\Enums\SnippetType;
use App\Models\CodeSnippet;
use App\Support\RequestCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodeSnippetModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_casts_type_and_position_to_enums(): void
    {
        $snippet = CodeSnippet::factory()->create([
            'type' => SnippetType::Style,
            'position' => SnippetPosition::BodyEnd,
        ]);

        $fresh = $snippet->fresh();

        $this->assertSame(SnippetType::Style, $fresh->type);
        $this->assertSame(SnippetPosition::BodyEnd, $fresh->position);
        $this->assertIsBool($fresh->is_active);
        $this->assertIsBool($fresh->skip_for_admins);
    }

    public function test_the_active_scope_excludes_disabled_snippets(): void
    {
        CodeSnippet::factory()->create(['name' => 'On', 'is_active' => true]);
        CodeSnippet::factory()->create(['name' => 'Off', 'is_active' => false]);

        $this->assertSame(['On'], CodeSnippet::query()->active()->pluck('name')->all());
    }

    /**
     * The renderer memoises its query for the whole request. Saving a snippet
     * without dropping that memo would show an editor a stale page and send
     * them hunting for a bug in their markup.
     */
    public function test_saving_flushes_the_request_cache(): void
    {
        RequestCache::remember('code_snippets', fn () => 'stale');

        CodeSnippet::factory()->create();

        $this->assertSame('fresh', RequestCache::remember('code_snippets', fn () => 'fresh'));
    }

    public function test_deleting_flushes_the_request_cache(): void
    {
        $snippet = CodeSnippet::factory()->create();

        RequestCache::remember('code_snippets', fn () => 'stale');

        $snippet->delete();

        $this->assertSame('fresh', RequestCache::remember('code_snippets', fn () => 'fresh'));
    }
}
