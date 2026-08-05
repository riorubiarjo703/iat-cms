<?php

namespace Tests\Feature\News;

use App\PageBuilder\BlockRegistry;
use App\PageBuilder\Blocks\NewsIndexBlock;
use Tests\TestCase;

class NewsIndexBlockTest extends TestCase
{
    public function test_the_block_is_registered(): void
    {
        // Registration is explicit, so a class in the directory is not enough.
        $registry = app(BlockRegistry::class);

        $this->assertTrue($registry->has('scbd_news_index'));
        $this->assertSame(NewsIndexBlock::class, $registry->get('scbd_news_index'));
    }

    public function test_its_render_view_exists(): void
    {
        $this->assertSame(
            'partials.blocks.scbd-news-index',
            NewsIndexBlock::renderView(),
        );

        $this->assertTrue(view()->exists(NewsIndexBlock::renderView()));
    }

    public function test_every_translatable_key_has_a_default(): void
    {
        // A key the editor writes but defaultData omits comes back null on a
        // freshly added block, and the view then renders nothing for it.
        $defaults = NewsIndexBlock::defaultData();

        foreach (NewsIndexBlock::translatableKeys() as $key) {
            $this->assertArrayHasKey($key, $defaults);
        }

        $this->assertSame(true, $defaults['show_filters']);
        $this->assertSame(5, $defaults['sidebar_limit']);
    }

    public function test_the_translatable_keys_are_the_copy_fields(): void
    {
        $this->assertSame(
            ['eyebrow', 'heading', 'empty_text', 'sidebar_heading'],
            NewsIndexBlock::translatableKeys(),
        );
    }

    public function test_it_offers_a_schema(): void
    {
        $this->assertNotEmpty(NewsIndexBlock::schema());
    }
}
