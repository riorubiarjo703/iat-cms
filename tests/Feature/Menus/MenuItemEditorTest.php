<?php

namespace Tests\Feature\Menus;

use App\Filament\Pages\EditMenuPage;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\ActsAsSuperAdmin;
use Tests\TestCase;

class MenuItemEditorTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsSuperAdmin;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();
        // EditMenuPage now gates on menus.manage (C1 of the roles/permissions
        // final review) — a roleless actor 403s before this test's own
        // assertions ever run.
        $this->actingAsSuperAdmin();
        $this->menu = Menu::create(['name' => 'Main Navigation']);
    }

    private function item(array $label = ['en' => 'Home'], string $type = MenuItem::TYPE_CUSTOM): MenuItem
    {
        return MenuItem::create([
            'menu_id' => $this->menu->id,
            'type' => $type,
            'label' => $label,
            'url' => '/',
        ]);
    }

    public function test_opening_the_editor_loads_the_items_values(): void
    {
        $item = $this->item(['en' => 'Home', 'id' => 'Beranda']);

        Livewire::test(EditMenuPage::class, ['record' => $this->menu->id])
            ->call('startEditing', (string) $item->id)
            ->assertSet('editingId', (string) $item->id)
            ->assertSet('editLabel.en', 'Home')
            ->assertSet('editLabel.id', 'Beranda')
            ->assertSet('editUrl', '/');
    }

    public function test_every_locale_gets_a_key_even_when_untranslated(): void
    {
        // An absent key silently discards whatever is typed into that input.
        $item = $this->item(['en' => 'Home']);

        Livewire::test(EditMenuPage::class, ['record' => $this->menu->id])
            ->call('startEditing', (string) $item->id)
            ->assertSet('editLabel.cn', '');
    }

    public function test_saving_writes_every_locale(): void
    {
        $item = $this->item();

        Livewire::test(EditMenuPage::class, ['record' => $this->menu->id])
            ->call('startEditing', (string) $item->id)
            ->set('editLabel.en', 'Home')
            ->set('editLabel.id', 'Beranda')
            ->set('editLabel.cn', '首页')
            ->set('editUrl', '/home')
            ->set('editTarget', '_blank')
            ->call('saveItem');

        $item->refresh();
        $this->assertSame('Beranda', $item->t('label', 'id'));
        $this->assertSame('首页', $item->t('label', 'cn'));
        $this->assertSame('/home', $item->url);
        $this->assertSame('_blank', $item->target);
    }

    public function test_a_blank_locale_is_dropped_not_stored_as_an_empty_string(): void
    {
        // Storing '' would defeat the English fallback for that locale.
        $item = $this->item(['en' => 'Home', 'id' => 'Beranda']);

        Livewire::test(EditMenuPage::class, ['record' => $this->menu->id])
            ->call('startEditing', (string) $item->id)
            ->set('editLabel.id', '')
            ->call('saveItem');

        $this->assertArrayNotHasKey('id', $item->refresh()->translations('label'));
        $this->assertSame('Home', $item->t('label', 'id'));
    }

    public function test_a_custom_link_cannot_be_left_with_no_label_at_all(): void
    {
        $item = $this->item();

        Livewire::test(EditMenuPage::class, ['record' => $this->menu->id])
            ->call('startEditing', (string) $item->id)
            ->set('editLabel.en', '')
            ->call('saveItem')
            ->assertSet('editingId', (string) $item->id);

        $this->assertSame('Home', $item->refresh()->t('label'));
    }

    public function test_a_linked_item_may_have_every_label_cleared(): void
    {
        // Clearing it is how you go back to borrowing the record's own title.
        $category = \AjayDhakal\FilamentStory\Models\BlogCategory::create(['name' => 'News', 'slug' => 'news']);
        $item = MenuItem::create([
            'menu_id' => $this->menu->id,
            'type' => MenuItem::TYPE_CATEGORY,
            'label' => ['en' => 'Override'],
            'linkable_type' => \AjayDhakal\FilamentStory\Models\BlogCategory::class,
            'linkable_id' => $category->id,
        ]);

        Livewire::test(EditMenuPage::class, ['record' => $this->menu->id])
            ->call('startEditing', (string) $item->id)
            ->set('editLabel.en', '')
            ->call('saveItem')
            ->assertSet('editingId', null);

        $this->assertSame('News', $item->refresh()->t('label'));
    }

    public function test_the_active_toggle_persists(): void
    {
        $item = $this->item();

        Livewire::test(EditMenuPage::class, ['record' => $this->menu->id])
            ->call('startEditing', (string) $item->id)
            ->set('editActive', false)
            ->call('saveItem');

        $this->assertFalse($item->refresh()->is_active);
    }

    public function test_cancelling_discards_the_changes(): void
    {
        $item = $this->item();

        Livewire::test(EditMenuPage::class, ['record' => $this->menu->id])
            ->call('startEditing', (string) $item->id)
            ->set('editLabel.en', 'Changed')
            ->call('cancelEditing')
            ->assertSet('editingId', null);

        $this->assertSame('Home', $item->refresh()->t('label'));
    }

    public function test_editing_an_item_from_another_menu_is_refused(): void
    {
        $other = Menu::create(['name' => 'Other']);
        $foreign = MenuItem::create(['menu_id' => $other->id, 'label' => ['en' => 'Foreign'], 'url' => '/x']);

        Livewire::test(EditMenuPage::class, ['record' => $this->menu->id])
            ->call('startEditing', (string) $foreign->id)
            ->assertSet('editingId', null);
    }
}
