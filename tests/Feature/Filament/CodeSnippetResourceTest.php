<?php

namespace Tests\Feature\Filament;

use App\Enums\SnippetPosition;
use App\Enums\SnippetType;
use App\Filament\Resources\CodeSnippets\CodeSnippetResource;
use App\Filament\Resources\CodeSnippets\Pages\CreateCodeSnippet;
use App\Filament\Resources\CodeSnippets\Pages\EditCodeSnippet;
use App\Filament\Resources\CodeSnippets\Pages\ListCodeSnippets;
use App\Models\CodeSnippet;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CodeSnippetResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // A plain factory user now hits the policies added for this
        // resource and is refused; the resource's own behaviour, not
        // authorization, is what this suite covers.
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
    }

    public function test_the_index_page_renders(): void
    {
        $this->get(CodeSnippetResource::getUrl('index'))->assertSuccessful();
    }

    /**
     * The reference mock's empty state has two actions, "Add Snippet" and
     * "Use Template". Both labels appearing twice — the header's copy plus
     * the empty state's own — is what distinguishes "the empty state offers
     * both" from "the header does and the empty state merely isn't hidden".
     */
    public function test_the_empty_state_offers_add_snippet_and_use_template(): void
    {
        $html = Livewire::test(ListCodeSnippets::class)->html();

        $this->assertSame(2, substr_count($html, 'Add Snippet'));
        $this->assertSame(2, substr_count($html, 'Template'));
    }

    /**
     * The plan dropped the empty state's "Use Template" button, reasoning
     * that `emptyStateActions` are table actions and "cannot reach the
     * page's applyTemplate method". That premise is false: `ListRecords`
     * implements `Tables\Contracts\HasTable`, so the table and the page are
     * the same Livewire component. Mounting the table-registered copy of the
     * action specifically (not the header's) and seeing its modal render
     * proves the button genuinely works, not just that the label is present
     * in the HTML.
     */
    public function test_the_empty_state_template_action_opens_the_same_modal(): void
    {
        Livewire::test(ListCodeSnippets::class)
            ->mountTableAction('template')
            ->assertMountedActionModalSee('Google Tag Manager')
            ->assertMountedActionModalSee("applyTemplate('gtm')", false);
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

    public function test_the_list_orders_by_position_then_priority(): void
    {
        // Deliberately inserted out of order and out of ID order, so passing
        // requires the query to actually sort rather than happening to
        // return rows in insertion or primary-key order.
        $bodyEnd = CodeSnippet::factory()->create(['position' => SnippetPosition::BodyEnd, 'priority' => 5]);
        $headLate = CodeSnippet::factory()->create(['position' => SnippetPosition::Head, 'priority' => 20]);
        $bodyStart = CodeSnippet::factory()->create(['position' => SnippetPosition::BodyStart, 'priority' => 1]);
        $headEarly = CodeSnippet::factory()->create(['position' => SnippetPosition::Head, 'priority' => 1]);

        Livewire::test(ListCodeSnippets::class)
            ->assertCanSeeTableRecords([$headEarly, $headLate, $bodyStart, $bodyEnd], inOrder: true);
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
