<?php

namespace Tests\Feature\PageBuilder;

use App\Models\Page;
use App\PageBuilder\BlockRegistry;
use App\PageBuilder\Blocks;
use Database\Seeders\ProfilePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentBlocksTest extends TestCase
{
    use RefreshDatabase;

    private function page(array $blocks): Page
    {
        return Page::create([
            'title' => ['en' => 'Built'], 'slug' => 'built',
            'type' => Page::TYPE_BUILDER, 'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => $blocks,
        ]);
    }

    private function block(string $type, array $data, string $id = 'block_1'): array
    {
        return ['id' => $id, 'type' => $type, 'data' => $data, 'children' => null];
    }

    public function test_the_three_content_blocks_are_registered_with_views(): void
    {
        $registry = app(BlockRegistry::class);

        foreach ([Blocks\PageHeroBlock::class, Blocks\VisionMissionBlock::class, Blocks\ValuesBlock::class] as $class) {
            $this->assertTrue($registry->has($class::type()), "[{$class::type()}] is not registered");
            $this->assertTrue(view()->exists($class::renderView()), "[{$class::type()}] has no view");
        }
    }

    public function test_the_page_hero_splits_the_intro_on_blank_lines(): void
    {
        $this->page([$this->block(Blocks\PageHeroBlock::type(), [
            'heading' => ['en' => 'Heading'],
            'body' => ['en' => "First para.\n\nSecond para."],
        ])]);

        $html = $this->get('/built')->getContent();

        $this->assertSame(2, substr_count($html, 'data-fade style="font-size:16px'));
        $this->assertStringContainsString('First para.', $html);
        $this->assertStringContainsString('Second para.', $html);
    }

    public function test_the_page_hero_renders_without_an_image_or_intro(): void
    {
        $this->page([$this->block(Blocks\PageHeroBlock::type(), ['heading' => ['en' => 'Bare']])]);

        $this->get('/built')->assertSuccessful()->assertSee('Bare', false);
    }

    public function test_mission_points_are_numbered_in_order(): void
    {
        $this->page([$this->block(Blocks\VisionMissionBlock::type(), [
            'vision' => ['en' => 'The vision'],
            'mission' => ['en' => [['text' => 'Alpha'], ['text' => 'Bravo']]],
        ])]);

        $html = $this->get('/built')->getContent();
        $alpha = strpos($html, 'Alpha');
        $bravo = strpos($html, 'Bravo');

        $this->assertLessThan($bravo, $alpha);
        $this->assertStringContainsString('01', $html);
        $this->assertStringContainsString('02', $html);
    }

    public function test_mission_points_fall_back_to_english_for_an_untranslated_locale(): void
    {
        // The list is per-locale, so without a fallback a visitor reading in
        // Indonesian would see the vision but no commitments at all.
        \App\Models\SiteSetting::singleton()->update(['default_locale' => 'id']);

        $this->page([$this->block(Blocks\VisionMissionBlock::type(), [
            'mission' => ['en' => [['text' => 'Only in English']]],
        ])]);

        $this->get('/built')->assertSee('Only in English', false);
    }

    public function test_the_values_panel_is_omitted_when_it_has_no_values(): void
    {
        // An empty red band would be a design element with nothing in it.
        $this->page([$this->block(Blocks\ValuesBlock::type(), ['heading' => ['en' => 'Culture']])]);

        $this->get('/built')->assertDontSee('id="values"', false);
    }

    public function test_the_values_panel_keeps_its_declared_order(): void
    {
        // SCBD's values spell SUSTAIN, so order is meaning.
        $this->page([$this->block(Blocks\ValuesBlock::type(), [
            'values' => ['en' => [['name' => 'Smart'], ['name' => 'Unity'], ['name' => 'Safety']]],
        ])]);

        $html = $this->get('/built')->getContent();

        $this->assertLessThan(strpos($html, 'Unity'), strpos($html, 'Smart'));
        $this->assertLessThan(strpos($html, 'Safety'), strpos($html, 'Unity'));
    }

    public function test_the_reveal_target_sits_inside_a_clipping_frame(): void
    {
        // data-reveal starts at scale 1.16; unclipped it pushed the page into
        // a horizontal scrollbar until the animation settled.
        $this->page([$this->block(Blocks\ValuesBlock::type(), [
            'values' => ['en' => [['name' => 'Smart']]],
        ])]);

        $html = $this->get('/built')->getContent();
        $section = substr($html, strpos($html, 'id="values"'), 200);

        $this->assertStringContainsString('overflow:hidden', $section);
    }

    public function test_the_profile_seeder_builds_the_page_and_it_renders(): void
    {
        Page::create(['title' => ['en' => 'Profile'], 'slug' => 'profile', 'status' => Page::STATUS_PUBLISHED]);

        $this->seed(ProfilePageSeeder::class);

        $page = Page::query()->where('slug', 'profile')->first();
        $this->assertCount(3, $page->blocks());

        $this->get('/profile')
            ->assertSuccessful()
            ->assertSee('world-class', false)
            ->assertSee('SUSTAIN', false)
            ->assertSee('To provide best service to stakeholders.', false);
    }

    public function test_the_profile_seeder_says_so_rather_than_failing_when_the_page_is_missing(): void
    {
        $this->seed(ProfilePageSeeder::class);

        $this->assertSame(0, Page::query()->where('slug', 'profile')->count());
    }
}
