<?php

namespace Tests\Feature\PageBuilder;

use App\PageBuilder\BlockData;
use App\PageBuilder\BlockRegistry;
use App\PageBuilder\Blocks;
use Tests\TestCase;

class BlockRegistryTest extends TestCase
{
    private function registry(): BlockRegistry
    {
        return app(BlockRegistry::class);
    }

    public function test_every_scbd_section_is_registered(): void
    {
        $types = array_keys($this->registry()->all());

        foreach ([
            Blocks\HeroBlock::type(),
            Blocks\MarqueeBlock::type(),
            Blocks\AboutBlock::type(),
            Blocks\DistrictBlock::type(),
            Blocks\FacilitiesBlock::type(),
            Blocks\NewsBlock::type(),
            Blocks\ContactHeadingBlock::type(),
        ] as $type) {
            $this->assertContains($type, $types);
        }
    }

    public function test_every_registered_block_has_a_view_that_exists(): void
    {
        // A missing view degrades to a placeholder rather than throwing, which
        // means a typo here would be invisible on the front end.
        foreach ($this->registry()->all() as $type => $class) {
            $this->assertTrue(
                view()->exists($class::renderView()),
                "[{$type}] resolves to a view that does not exist: {$class::renderView()}",
            );
        }
    }

    public function test_an_unknown_type_resolves_to_no_view_rather_than_throwing(): void
    {
        $this->assertSame('', $this->registry()->resolveRenderView('does_not_exist'));
        $this->assertNull($this->registry()->get('does_not_exist'));
    }

    public function test_types_are_namespaced_so_they_cannot_collide(): void
    {
        // Type strings are persistent identifiers stored in every payload.
        foreach (array_keys($this->registry()->all()) as $type) {
            $this->assertStringStartsWith('scbd_', $type);
        }
    }

    public function test_blocks_are_grouped_for_the_picker(): void
    {
        $this->assertArrayHasKey('Sections', $this->registry()->byCategory());
    }

    public function test_a_translatable_leaf_uses_the_requested_locale(): void
    {
        $data = ['heading' => ['en' => 'Hello', 'id' => 'Halo']];

        $this->assertSame('Halo', BlockData::t($data, 'heading', 'id'));
    }

    public function test_a_missing_locale_falls_back_to_english(): void
    {
        $data = ['heading' => ['en' => 'Hello']];

        $this->assertSame('Hello', BlockData::t($data, 'heading', 'cn'));
    }

    public function test_an_empty_locale_falls_back_rather_than_rendering_blank(): void
    {
        $data = ['heading' => ['en' => 'Hello', 'cn' => '']];

        $this->assertSame('Hello', BlockData::t($data, 'heading', 'cn'));
    }

    public function test_a_plain_string_leaf_is_tolerated(): void
    {
        // A block saved before a field became translatable stores a string.
        $this->assertSame('Hello', BlockData::t(['heading' => 'Hello'], 'heading', 'id'));
    }

    public function test_a_missing_key_returns_null_rather_than_erroring(): void
    {
        $this->assertNull(BlockData::t([], 'heading', 'en'));
    }

    public function test_i18n_keys_are_namespaced_by_block(): void
    {
        // Two blocks of the same type on one page would otherwise collide.
        $this->assertNotSame(
            BlockData::i18nKey('block_a', 'heading'),
            BlockData::i18nKey('block_b', 'heading'),
        );
    }
}
