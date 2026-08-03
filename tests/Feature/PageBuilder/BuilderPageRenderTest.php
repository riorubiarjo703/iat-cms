<?php

namespace Tests\Feature\PageBuilder;

use App\Models\DistrictPlace;
use App\Models\Facility;
use App\Models\HomepageContent;
use App\Models\Page;
use App\Models\Stat;
use App\PageBuilder\BlockRegistry;
use App\PageBuilder\BlockTranslations;
use App\PageBuilder\Blocks;
use App\PageBuilder\HomepagePayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuilderPageRenderTest extends TestCase
{
    use RefreshDatabase;

    private function builderPage(array $payload): Page
    {
        return Page::create([
            'title' => ['en' => 'Built'],
            'slug' => 'built',
            'type' => Page::TYPE_BUILDER,
            'builder_payload' => $payload,
            'status' => Page::STATUS_PUBLISHED,
        ]);
    }

    private function block(string $type, array $data = [], string $id = 'block_1'): array
    {
        return ['id' => $id, 'type' => $type, 'data' => $data, 'children' => null];
    }

    public function test_a_known_block_renders(): void
    {
        $this->builderPage([
            $this->block(Blocks\HeroBlock::type(), ['heading' => ['en' => 'A district that never clocks out']]),
        ]);

        $this->get('/built')
            ->assertSuccessful()
            ->assertSee('A district that never clocks out', false);
    }

    public function test_an_unknown_block_type_does_not_take_the_page_down(): void
    {
        // A payload can outlive the block type that produced it.
        $this->builderPage([
            $this->block('removed_in_a_later_version', ['heading' => ['en' => 'X']]),
            $this->block(Blocks\MarqueeBlock::type(), ['text' => ['en' => 'Still here']], 'block_2'),
        ]);

        $this->get('/built')->assertSuccessful()->assertSee('Still here', false);
    }

    public function test_a_block_with_missing_data_keys_still_renders(): void
    {
        // Pages persisted under an older schema are read by newer code.
        $this->builderPage([$this->block(Blocks\AboutBlock::type(), [])]);

        $this->get('/built')->assertSuccessful();
    }

    public function test_the_animation_hooks_survive_into_the_rendered_block(): void
    {
        // The blocks exist to reproduce an animated design; without these
        // attributes the content renders and never becomes visible.
        $this->builderPage([$this->block(Blocks\HeroBlock::type(), ['heading' => ['en' => 'Hi']])]);

        $html = $this->get('/built')->getContent();

        $this->assertStringContainsString('data-split', $html);
        $this->assertStringContainsString('data-parallax', $html);
    }

    public function test_a_builder_page_ships_the_animation_bundle_and_a_standard_page_does_not(): void
    {
        $this->builderPage([$this->block(Blocks\HeroBlock::type(), ['heading' => ['en' => 'Hi']])]);
        Page::create(['title' => ['en' => 'Plain'], 'slug' => 'plain', 'content' => ['en' => 'text'], 'status' => Page::STATUS_PUBLISHED]);

        // Vite content-hashes the filename, so the manifest is the only honest
        // source for what the built entry is called.
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $bundle = $manifest['resources/js/scbd/index.js']['file'];

        $this->assertStringContainsString($bundle, $this->get('/built')->getContent());
        $this->assertStringNotContainsString($bundle, $this->get('/plain')->getContent());
    }

    public function test_the_translation_payload_is_published_for_the_switcher(): void
    {
        $page = $this->builderPage([
            $this->block(Blocks\HeroBlock::type(), ['heading' => ['en' => 'Hello', 'id' => 'Halo']]),
        ]);

        $payload = BlockTranslations::forPage($page, app(BlockRegistry::class));

        // Derived, not hardcoded, so the test cannot drift from the key the
        // block views actually emit.
        $key = \App\PageBuilder\BlockData::i18nKey('block_1', 'heading');

        $this->assertSame('Hello', $payload['en'][$key] ?? null);
        $this->assertSame('Halo', $payload['id'][$key] ?? null);
    }

    public function test_the_payload_skips_blocks_whose_type_is_gone(): void
    {
        $page = $this->builderPage([$this->block('vanished', ['heading' => ['en' => 'X']])]);

        $this->assertSame([], BlockTranslations::forPage($page, app(BlockRegistry::class))['en']);
    }

    public function test_a_standard_page_publishes_no_block_payload(): void
    {
        Page::create(['title' => ['en' => 'Plain'], 'slug' => 'plain', 'content' => ['en' => 'text'], 'status' => Page::STATUS_PUBLISHED]);

        $this->assertStringNotContainsString('scbd-i18n', $this->get('/plain')->getContent());
    }

    public function test_the_homepage_payload_reproduces_every_section(): void
    {
        Stat::create(['label' => ['en' => 'Hectares'], 'value' => 45, 'sort' => 1]);
        DistrictPlace::create(['title' => ['en' => 'Tower'], 'caption' => ['en' => 'Office'], 'sort' => 1, 'is_active' => true]);
        Facility::create(['title' => ['en' => 'Parking'], 'body' => ['en' => 'Body'], 'sort' => 1, 'is_active' => true]);

        HomepageContent::singleton()->update([
            'hero_line' => ['en' => 'Hero line'],
            'marquee_text' => ['en' => 'Marquee'],
            'about_heading' => ['en' => 'About heading'],
            'district_heading' => ['en' => 'District heading'],
            'facilities_heading' => ['en' => 'Facilities heading'],
            'news_heading' => ['en' => 'News heading'],
            'contact_heading' => ['en' => 'Contact heading'],
        ]);

        $payload = HomepagePayload::fromContent(HomepageContent::singleton());
        $this->builderPage($payload);

        $html = $this->get('/built')->assertSuccessful()->getContent();

        foreach (['top', 'about', 'district', 'facilities', 'news', 'contact'] as $section) {
            $this->assertStringContainsString('id="'.$section.'"', $html, "The [{$section}] section is missing");
        }
    }

    public function test_the_homepage_payload_ids_are_stable(): void
    {
        // Random ids would change the i18n keys a page publishes on every
        // regeneration, orphaning anything that cached them.
        HomepageContent::singleton()->update(['hero_line' => ['en' => 'Hero']]);

        $first = HomepagePayload::fromContent(HomepageContent::singleton());
        $second = HomepagePayload::fromContent(HomepageContent::singleton());

        $this->assertSame(array_column($first, 'id'), array_column($second, 'id'));
    }

    public function test_a_section_backed_by_an_empty_collection_is_omitted(): void
    {
        // The pinned horizontal scroll has nothing to pin without places.
        $this->builderPage([$this->block(Blocks\DistrictBlock::type(), ['heading' => ['en' => 'District']])]);

        $this->get('/built')->assertSuccessful()->assertDontSee('id="district"', false);
    }
}
