<?php

namespace Tests\Feature\Filament;

use App\Enums\SnippetPosition;
use App\Enums\SnippetType;
use App\Filament\Resources\CodeSnippets\CodeSnippetResource;
use App\Filament\Resources\CodeSnippets\Pages\CreateCodeSnippet;
use App\Filament\Resources\CodeSnippets\Pages\EditCodeSnippet;
use App\Models\CodeSnippet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CodeSnippetResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_index_page_renders(): void
    {
        $this->get(CodeSnippetResource::getUrl('index'))->assertSuccessful();
    }

    public function test_it_creates_a_snippet(): void
    {
        Livewire::test(CreateCodeSnippet::class)
            ->fillForm([
                'name' => 'Google Analytics',
                'type' => SnippetType::Script->value,
                'position' => SnippetPosition::Head->value,
                'priority' => 10,
                'code' => '<script>ga()</script>',
                'is_active' => true,
                'skip_for_admins' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $snippet = CodeSnippet::query()->sole();

        $this->assertSame('Google Analytics', $snippet->name);
        $this->assertSame(SnippetPosition::Head, $snippet->position);
        $this->assertTrue($snippet->skip_for_admins);
    }

    public function test_name_and_code_are_required(): void
    {
        Livewire::test(CreateCodeSnippet::class)
            ->fillForm(['name' => null, 'code' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'code' => 'required']);
    }

    public function test_priority_is_capped_at_one_hundred(): void
    {
        Livewire::test(CreateCodeSnippet::class)
            ->fillForm([
                'name' => 'Too eager',
                'code' => '<script></script>',
                'priority' => 101,
            ])
            ->call('create')
            ->assertHasFormErrors(['priority']);
    }

    public function test_priority_rejects_negative_numbers(): void
    {
        Livewire::test(CreateCodeSnippet::class)
            ->fillForm([
                'name' => 'Backwards',
                'code' => '<script></script>',
                'priority' => -1,
            ])
            ->call('create')
            ->assertHasFormErrors(['priority']);
    }

    public function test_it_edits_an_existing_snippet(): void
    {
        $snippet = CodeSnippet::factory()->create(['name' => 'Old']);

        Livewire::test(EditCodeSnippet::class, ['record' => $snippet->getKey()])
            ->fillForm(['name' => 'New'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('New', $snippet->fresh()->name);
    }
}
