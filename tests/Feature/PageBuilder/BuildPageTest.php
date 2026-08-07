<?php

namespace Tests\Feature\PageBuilder;

use App\Filament\Pages\BuildPage;
use App\Models\Page;
use App\PageBuilder\Blocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\ActsAsSuperAdmin;
use Tests\TestCase;

class BuildPageTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsSuperAdmin;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsSuperAdmin();

        $this->page = Page::create([
            'title' => ['en' => 'Built'],
            'slug' => 'built',
            'type' => Page::TYPE_BUILDER,
            'status' => Page::STATUS_PUBLISHED,
        ]);
    }

    private function block(string $type, array $data = [], string $id = 'block_1'): array
    {
        return ['id' => $id, 'type' => $type, 'data' => $data, 'children' => null];
    }

    public function test_the_builder_screen_renders(): void
    {
        $this->get(BuildPage::getUrl(['record' => $this->page->id]))
            ->assertSuccessful()
            ->assertSee('Page structure');
    }

    public function test_the_palette_offers_every_registered_block(): void
    {
        $html = $this->get(BuildPage::getUrl(['record' => $this->page->id]))->getContent();

        foreach ([Blocks\HeroBlock::name(), Blocks\MarqueeBlock::name(), Blocks\NewsBlock::name()] as $name) {
            $this->assertStringContainsString($name, $html);
        }
    }

    public function test_palette_categories_render_as_expanded_collapsible_controls(): void
    {
        $html = $this->get(BuildPage::getUrl(['record' => $this->page->id]))->getContent();

        $this->assertStringContainsString('x-data="{ open: true }"', $html);
        $this->assertStringContainsString('x-on:click="open = !open"', $html);
        $this->assertStringContainsString(':aria-expanded="open.toString()"', $html);
        $this->assertStringContainsString('x-show="open"', $html);
        $this->assertStringContainsString('x-collapse', $html);
    }

    public function test_adding_a_block_persists_it_with_its_defaults(): void
    {
        Livewire::test(BuildPage::class, ['record' => $this->page->id])
            ->call('addBlock', Blocks\MarqueeBlock::type());

        $blocks = $this->page->refresh()->blocks();

        $this->assertCount(1, $blocks);
        $this->assertSame(Blocks\MarqueeBlock::type(), $blocks[0]['type']);
        $this->assertArrayHasKey('text', $blocks[0]['data']);
    }

    public function test_an_unknown_block_type_cannot_be_added(): void
    {
        Livewire::test(BuildPage::class, ['record' => $this->page->id])
            ->call('addBlock', 'not_a_real_block');

        $this->assertSame([], $this->page->refresh()->blocks());
    }

    public function test_every_added_block_gets_a_distinct_id(): void
    {
        // Two blocks sharing an id would collide in the i18n payload and in
        // any keyed update.
        Livewire::test(BuildPage::class, ['record' => $this->page->id])
            ->call('addBlock', Blocks\MarqueeBlock::type())
            ->call('addBlock', Blocks\MarqueeBlock::type());

        $ids = array_column($this->page->refresh()->blocks(), 'id');

        $this->assertCount(2, array_unique($ids));
    }

    public function test_removing_a_block_persists(): void
    {
        $this->page->update(['builder_payload' => [
            $this->block(Blocks\HeroBlock::type(), [], 'block_a'),
            $this->block(Blocks\MarqueeBlock::type(), [], 'block_b'),
        ]]);

        Livewire::test(BuildPage::class, ['record' => $this->page->id])->call('removeBlock', 'block_a');

        $this->assertSame(['block_b'], array_column($this->page->refresh()->blocks(), 'id'));
    }

    public function test_reordering_persists(): void
    {
        $this->page->update(['builder_payload' => [
            $this->block(Blocks\HeroBlock::type(), [], 'block_a'),
            $this->block(Blocks\MarqueeBlock::type(), [], 'block_b'),
        ]]);

        Livewire::test(BuildPage::class, ['record' => $this->page->id])
            ->call('saveOrder', ['block_b', 'block_a']);

        $this->assertSame(['block_b', 'block_a'], array_column($this->page->refresh()->blocks(), 'id'));
    }

    public function test_an_order_that_drops_a_block_is_refused(): void
    {
        // A partial payload must not silently delete what it omits.
        $this->page->update(['builder_payload' => [
            $this->block(Blocks\HeroBlock::type(), [], 'block_a'),
            $this->block(Blocks\MarqueeBlock::type(), [], 'block_b'),
        ]]);

        Livewire::test(BuildPage::class, ['record' => $this->page->id])->call('saveOrder', ['block_a']);

        $this->assertCount(2, $this->page->refresh()->blocks());
    }

    public function test_an_order_naming_a_foreign_id_is_refused(): void
    {
        $this->page->update(['builder_payload' => [$this->block(Blocks\HeroBlock::type(), [], 'block_a')]]);

        Livewire::test(BuildPage::class, ['record' => $this->page->id])->call('saveOrder', ['block_from_elsewhere']);

        $this->assertSame(['block_a'], array_column($this->page->refresh()->blocks(), 'id'));
    }

    public function test_editing_loads_the_blocks_current_data(): void
    {
        $this->page->update(['builder_payload' => [
            $this->block(Blocks\MarqueeBlock::type(), ['text' => ['en' => 'Rolling']], 'block_a'),
        ]]);

        Livewire::test(BuildPage::class, ['record' => $this->page->id])
            ->call('editBlock', 'block_a')
            ->assertSet('editingId', 'block_a')
            ->assertSet('blockData.text.en', 'Rolling');
    }

    public function test_saving_a_block_keeps_the_other_locales(): void
    {
        // Editing English must not wipe a translation entered elsewhere.
        $this->page->update(['builder_payload' => [
            $this->block(Blocks\MarqueeBlock::type(), ['text' => ['en' => 'Rolling', 'id' => 'Berjalan']], 'block_a'),
        ]]);

        Livewire::test(BuildPage::class, ['record' => $this->page->id])
            ->call('editBlock', 'block_a')
            ->set('blockData.text.en', 'Changed')
            ->call('saveBlock')
            ->assertSet('editingId', null);

        $data = $this->page->refresh()->blocks()[0]['data'];

        $this->assertSame('Changed', $data['text']['en']);
        $this->assertSame('Berjalan', $data['text']['id']);
    }

    public function test_saving_one_block_leaves_the_others_alone(): void
    {
        $this->page->update(['builder_payload' => [
            $this->block(Blocks\MarqueeBlock::type(), ['text' => ['en' => 'One']], 'block_a'),
            $this->block(Blocks\MarqueeBlock::type(), ['text' => ['en' => 'Two']], 'block_b'),
        ]]);

        Livewire::test(BuildPage::class, ['record' => $this->page->id])
            ->call('editBlock', 'block_a')
            ->set('blockData.text.en', 'Edited')
            ->call('saveBlock');

        $blocks = $this->page->refresh()->blocks();

        $this->assertSame('Edited', $blocks[0]['data']['text']['en']);
        $this->assertSame('Two', $blocks[1]['data']['text']['en']);
    }

    public function test_a_block_whose_type_is_gone_cannot_be_edited(): void
    {
        // There is no schema to render, so opening it would show an empty form
        // and saving would overwrite the data with nothing.
        $this->page->update(['builder_payload' => [$this->block('vanished', ['x' => 1], 'block_a')]]);

        Livewire::test(BuildPage::class, ['record' => $this->page->id])
            ->call('editBlock', 'block_a')
            ->assertSet('editingId', null);
    }

    public function test_an_unknown_block_is_still_listed_rather_than_hidden(): void
    {
        // Hiding it would make the next save silently drop it.
        $this->page->update(['builder_payload' => [$this->block('vanished', [], 'block_a')]]);

        $this->get(BuildPage::getUrl(['record' => $this->page->id]))
            ->assertSuccessful()
            ->assertSee('Unknown (vanished)');
    }
}
