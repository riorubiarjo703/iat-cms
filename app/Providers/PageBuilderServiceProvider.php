<?php

namespace App\Providers;

use App\PageBuilder\BlockRegistry;
use App\PageBuilder\Blocks;
use Illuminate\Support\ServiceProvider;

class PageBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlockRegistry::class, function (): BlockRegistry {
            $registry = new BlockRegistry;

            // Explicit, not auto-discovered: a stray class in the directory
            // must not silently become an option in the editor.
            foreach ([
                Blocks\HeroBlock::class,
                Blocks\MarqueeBlock::class,
                Blocks\AboutBlock::class,
                Blocks\DistrictBlock::class,
                Blocks\FacilitiesBlock::class,
                Blocks\NewsBlock::class,
                Blocks\ContactHeadingBlock::class,
            ] as $block) {
                $registry->register($block);
            }

            return $registry;
        });
    }
}
