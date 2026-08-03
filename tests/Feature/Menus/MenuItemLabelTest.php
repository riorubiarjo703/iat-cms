<?php

namespace Tests\Feature\Menus;

use AjayDhakal\FilamentStory\Models\BlogCategory;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemLabelTest extends TestCase
{
    use RefreshDatabase;

    private function menu(): Menu
    {
        return Menu::create(['name' => 'One']);
    }

    public function test_a_typed_label_wins(): void
    {
        $item = MenuItem::create(['menu_id' => $this->menu()->id, 'label' => ['en' => 'Home'], 'url' => '/']);

        $this->assertSame('Home', $item->t('label'));
    }

    public function test_a_missing_locale_falls_back_to_english(): void
    {
        $item = MenuItem::create(['menu_id' => $this->menu()->id, 'label' => ['en' => 'Home'], 'url' => '/']);

        $this->assertSame('Home', $item->t('label', 'cn'));
    }

    public function test_a_translated_locale_is_used(): void
    {
        $item = MenuItem::create(['menu_id' => $this->menu()->id, 'label' => ['en' => 'Home', 'id' => 'Beranda'], 'url' => '/']);

        $this->assertSame('Beranda', $item->t('label', 'id'));
    }

    public function test_a_linked_item_borrows_the_records_title_when_no_label_is_typed(): void
    {
        // The whole point of linking: translations entered on the record are
        // not retyped on the menu item.
        $category = BlogCategory::create(['name' => 'News', 'slug' => 'news']);

        $item = MenuItem::create([
            'menu_id' => $this->menu()->id,
            'type' => MenuItem::TYPE_CATEGORY,
            'label' => [],
            'linkable_type' => BlogCategory::class,
            'linkable_id' => $category->id,
        ]);

        $this->assertSame('News', $item->t('label'));
    }

    public function test_an_override_beats_the_linked_records_title(): void
    {
        $category = BlogCategory::create(['name' => 'News', 'slug' => 'news']);

        $item = MenuItem::create([
            'menu_id' => $this->menu()->id,
            'type' => MenuItem::TYPE_CATEGORY,
            'label' => ['en' => 'Latest'],
            'linkable_type' => BlogCategory::class,
            'linkable_id' => $category->id,
        ]);

        $this->assertSame('Latest', $item->t('label'));
    }

    public function test_a_deleted_target_sends_the_link_nowhere_explicitly(): void
    {
        // An empty href renders as a link to the current page, which looks
        // like it works.
        $item = MenuItem::create([
            'menu_id' => $this->menu()->id,
            'type' => MenuItem::TYPE_PAGE,
            'label' => ['en' => 'Gone'],
            'linkable_type' => BlogCategory::class,
            'linkable_id' => 9999,
        ]);

        $this->assertSame('#', $item->resolveUrl());
    }

    public function test_a_custom_link_uses_its_own_url(): void
    {
        $item = MenuItem::create([
            'menu_id' => $this->menu()->id,
            'type' => MenuItem::TYPE_CUSTOM,
            'label' => ['en' => 'External'],
            'url' => 'https://example.com',
        ]);

        $this->assertSame('https://example.com', $item->resolveUrl());
    }

    public function test_menu_labels_join_the_translation_coverage_panel(): void
    {
        // Same concern as every other translatable model, so coverage picks
        // them up with no extra wiring.
        $this->assertContains(MenuItem::class, (new \App\Support\TranslationCoverage)->translatableModels());
    }
}
