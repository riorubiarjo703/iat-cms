<?php

namespace Tests\Unit\Models;

use App\Models\PublicMenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMenuItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_scope_returns_active_non_cta_items_in_order(): void
    {
        PublicMenuItem::create(['label' => ['en' => 'News'], 'url' => '#news', 'sort' => 4]);
        PublicMenuItem::create(['label' => ['en' => 'Company'], 'url' => '#about', 'sort' => 1]);
        PublicMenuItem::create(['label' => ['en' => 'Enquire'], 'url' => '#contact', 'sort' => 9, 'is_cta' => true]);
        PublicMenuItem::create(['label' => ['en' => 'Hidden'], 'url' => '#x', 'sort' => 2, 'is_active' => false]);

        $labels = PublicMenuItem::query()->links()->get()->map(fn ($i) => $i->t('label', 'en'));

        $this->assertSame(['Company', 'News'], $labels->all());
    }

    public function test_cta_scope_returns_only_active_cta_items(): void
    {
        PublicMenuItem::create(['label' => ['en' => 'Company'], 'url' => '#about', 'sort' => 1]);
        PublicMenuItem::create(['label' => ['en' => 'Enquire'], 'url' => '#contact', 'sort' => 9, 'is_cta' => true]);
        PublicMenuItem::create(['label' => ['en' => 'Old CTA'], 'url' => '#y', 'sort' => 8, 'is_cta' => true, 'is_active' => false]);

        $labels = PublicMenuItem::query()->cta()->get()->map(fn ($i) => $i->t('label', 'en'));

        $this->assertSame(['Enquire'], $labels->all());
    }

    public function test_items_default_to_active_and_not_cta(): void
    {
        $item = PublicMenuItem::create(['label' => ['en' => 'X'], 'url' => '#x'])->fresh();

        $this->assertTrue($item->is_active);
        $this->assertFalse($item->is_cta);
    }

    public function test_target_defaults_to_self(): void
    {
        $this->assertSame('_self', PublicMenuItem::create(['label' => ['en' => 'X'], 'url' => '#x'])->fresh()->target);
    }

    public function test_label_falls_back_to_english(): void
    {
        $item = PublicMenuItem::create(['label' => ['en' => 'Company', 'id' => 'Perusahaan'], 'url' => '#about']);

        $this->assertSame('Perusahaan', $item->t('label', 'id'));
        $this->assertSame('Company', $item->t('label', 'cn'));
    }
}
