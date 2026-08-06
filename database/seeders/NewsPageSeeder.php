<?php

namespace Database\Seeders;

use App\Models\Page;
use App\PageBuilder\Blocks\NewsIndexBlock;
use Illuminate\Database\Seeder;

/**
 * Turns the empty draft "news" page into the News landing page.
 *
 * Only ever fills a page that has no blocks. Once an editor has arranged it,
 * re-seeding must leave their work alone — a seeder that overwrites is a
 * seeder nobody can safely run twice.
 */
class NewsPageSeeder extends Seeder
{
    public function run(): void
    {
        $page = Page::firstOrCreate(
            ['slug' => 'news'],
            ['title' => ['en' => 'News'], 'type' => Page::TYPE_BUILDER, 'status' => Page::STATUS_DRAFT],
        );

        if ($page->blocks() !== []) {
            // Already built. Publishing is still safe and still wanted.
            $page->update(['status' => Page::STATUS_PUBLISHED]);

            return;
        }

        $page->update([
            'type' => Page::TYPE_BUILDER,
            'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => [[
                'id' => 'block_1',
                'type' => NewsIndexBlock::type(),
                'data' => [
                    'eyebrow' => ['en' => 'Newsroom'],
                    'heading' => ['en' => 'News'],
                    'empty_text' => ['en' => 'There are no published posts yet.'],
                    'sidebar_heading' => ['en' => 'Flash news'],
                    'show_filters' => true,
                    'sidebar_limit' => 5,
                ],
                'children' => null,
            ]],
        ]);
    }
}
