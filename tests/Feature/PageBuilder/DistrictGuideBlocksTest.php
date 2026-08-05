<?php

namespace Tests\Feature\PageBuilder;

use App\Models\DistrictPlace;
use App\Models\Facility;
use App\Models\Page;
use App\Models\SiteSetting;
use App\PageBuilder\BlockRegistry;
use App\PageBuilder\Blocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four blocks that make up the District Facilities page below its hero.
 */
class DistrictGuideBlocksTest extends TestCase
{
    use RefreshDatabase;

    private function page(array $blocks): Page
    {
        return Page::create([
            'title' => ['en' => 'Guide'], 'slug' => 'guide',
            'type' => Page::TYPE_BUILDER, 'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => $blocks,
        ]);
    }

    private function block(string $type, array $data, string $id = 'block_1'): array
    {
        return ['id' => $id, 'type' => $type, 'data' => $data, 'children' => null];
    }

    /**
     * One section's markup, by id.
     *
     * Assertions have to be scoped to the section rather than run against the
     * whole document: the layout publishes every block's translatable strings
     * into the `scbd-i18n` payload for the language switcher — whether or not
     * the block rendered anything — and the footer prints the site's address
     * on every page. Both make a naive assertDontSee on copy meaningless.
     */
    private function section(string $html, string $id): string
    {
        $start = strpos($html, 'id="'.$id.'"');

        if ($start === false) {
            return '';
        }

        $end = strpos($html, '</section>', $start);

        return substr($html, $start, $end === false ? null : $end - $start);
    }

    public function test_the_four_blocks_are_registered_with_views(): void
    {
        $registry = app(BlockRegistry::class);

        foreach ([Blocks\PlacesBlock::class, Blocks\LocationBlock::class, Blocks\OperationsBlock::class, Blocks\CtaBlock::class] as $class) {
            $this->assertTrue($registry->has($class::type()), "[{$class::type()}] is not registered");
            $this->assertTrue(view()->exists($class::renderView()), "[{$class::type()}] has no view");
        }
    }

    public function test_places_renders_a_row_per_place_with_its_tags_and_statistic(): void
    {
        DistrictPlace::create([
            'title' => ['en' => 'The towers'],
            'body' => ['en' => 'Grade A office towers.'],
            'tags' => ['en' => 'Artha Graha, Landmark Tower'],
            'stat_label' => ['en' => 'Visitors per day'],
            'stat_value' => '18K+',
        ]);

        $this->page([$this->block(Blocks\PlacesBlock::type(), ['heading' => ['en' => 'Places of interest']])]);

        $html = $this->get('/guide')->assertSuccessful()->getContent();

        $this->assertStringContainsString('The towers', $html);
        $this->assertStringContainsString('Grade A office towers.', $html);
        $this->assertStringContainsString('>Artha Graha</span>', $html);
        $this->assertStringContainsString('>Landmark Tower</span>', $html);
        $this->assertStringContainsString('Visitors per day', $html);
        $this->assertStringContainsString('18K+', $html);
    }

    /**
     * The row's sides swap down the list, which is a single class doing the
     * work — and a class Blade will silently drop if it is written beside a
     * `class` attribute rather than inside the same @class call.
     */
    public function test_places_rows_alternate_which_side_the_image_is_on(): void
    {
        foreach (['One', 'Two', 'Three'] as $index => $title) {
            DistrictPlace::create(['title' => ['en' => $title], 'sort' => $index]);
        }

        $this->page([$this->block(Blocks\PlacesBlock::type(), ['heading' => ['en' => 'Places']])]);

        $html = $this->get('/guide')->getContent();

        // Asserted against the whole rendered attribute, not the class name
        // on its own. Written beside a `class` attribute the directive emits a
        // second one, which the parser discards — the class name is still
        // present in the source, so a substring assertion passes while the
        // rows do not actually alternate.
        $this->assertSame(
            2,
            substr_count($html, 'class="scbd-guide-row scbd-card-split"'),
            'expected two upright rows',
        );
        $this->assertSame(
            1,
            substr_count($html, 'class="scbd-guide-row scbd-card-split scbd-guide-row-flip"'),
            'expected one flipped row',
        );
        $this->assertSame(
            3,
            substr_count($html, '<article class="scbd-guide-row'),
            'every row must carry exactly one class attribute',
        );
    }

    public function test_places_is_omitted_when_there_are_no_places(): void
    {
        $this->page([$this->block(Blocks\PlacesBlock::type(), ['heading' => ['en' => 'Places of interest']])]);

        $html = $this->get('/guide')->assertSuccessful()->getContent();

        $this->assertSame('', $this->section($html, 'places'));
        $this->assertStringNotContainsString('scbd-guide-row', $html);
    }

    public function test_operations_renders_each_facility_with_its_eyebrow_and_statistic(): void
    {
        Facility::create([
            'title' => ['en' => 'Fire & emergency'],
            'eyebrow' => ['en' => '24/7 Operations'],
            'body' => ['en' => 'A dedicated district fire station.'],
            'stat_label' => ['en' => 'Team strength'],
            'stat_value' => '32 personnel',
        ]);

        $this->page([$this->block(Blocks\OperationsBlock::type(), ['heading' => ['en' => 'District facilities']])]);

        $html = $this->get('/guide')->assertSuccessful()->getContent();

        $this->assertStringContainsString('24/7 Operations', $html);
        $this->assertStringContainsString('A dedicated district fire station.', $html);
        $this->assertStringContainsString('Team strength', $html);
        $this->assertStringContainsString('32 personnel', $html);
    }

    public function test_operations_is_omitted_when_there_are_no_facilities(): void
    {
        $this->page([$this->block(Blocks\OperationsBlock::type(), ['heading' => ['en' => 'District facilities']])]);

        $html = $this->get('/guide')->assertSuccessful()->getContent();

        $this->assertSame('', $this->section($html, 'facilities'));
        $this->assertStringNotContainsString('scbd-guide-row', $html);
    }

    public function test_location_falls_back_to_the_address_in_site_settings(): void
    {
        SiteSetting::singleton()->update([
            'contact_address' => "PT Danayasa Arthatama,\nJakarta 12190",
            'contact_phone' => '+62(21) 515-2390',
        ]);

        $this->page([$this->block(Blocks\LocationBlock::type(), [
            'heading' => ['en' => 'Location & access'],
            'address' => [],
            'contact' => [],
        ])]);

        $section = $this->section($this->get('/guide')->assertSuccessful()->getContent(), 'location');

        $this->assertStringContainsString('PT Danayasa Arthatama', $section);
        $this->assertStringContainsString('+62(21) 515-2390', $section);
    }

    public function test_location_prefers_its_own_address_over_site_settings(): void
    {
        SiteSetting::singleton()->update(['contact_address' => 'Settings address']);

        $this->page([$this->block(Blocks\LocationBlock::type(), [
            'heading' => ['en' => 'Location & access'],
            'address' => ['en' => 'Block address'],
        ])]);

        $section = $this->section($this->get('/guide')->getContent(), 'location');

        $this->assertStringContainsString('Block address', $section);
        $this->assertStringNotContainsString('Settings address', $section);
    }

    public function test_location_renders_the_map_and_its_facts(): void
    {
        $this->page([$this->block(Blocks\LocationBlock::type(), [
            'heading' => ['en' => 'Location & access'],
            'map_embed_url' => 'https://www.google.com/maps?q=Jakarta&output=embed',
            'access' => [['label' => 'Metro', 'text' => 'Sudirman station']],
            'facts' => [['label' => 'Distance from airport', 'value' => '22 km']],
        ])]);

        $html = $this->get('/guide')->getContent();

        $this->assertStringContainsString('output=embed', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('Sudirman station', $html);
        $this->assertStringContainsString('22 km', $html);
    }

    public function test_location_omits_the_map_frame_when_no_url_is_set(): void
    {
        $this->page([$this->block(Blocks\LocationBlock::type(), [
            'heading' => ['en' => 'Location & access'],
            'map_embed_url' => null,
        ])]);

        $this->get('/guide')->assertSuccessful()->assertDontSee('<iframe', false);
    }

    public function test_the_call_to_action_renders_its_button(): void
    {
        $this->page([$this->block(Blocks\CtaBlock::type(), [
            'heading' => ['en' => 'Ready to explore?'],
            'body' => ['en' => 'Visit SCBD.'],
            'button_label' => ['en' => 'Get in touch'],
            'button_url' => '/contact-us',
        ])]);

        $html = $this->get('/guide')->assertSuccessful()->getContent();

        $this->assertStringContainsString('Ready to explore?', $html);
        $this->assertStringContainsString('href="/contact-us"', $html);
        $this->assertStringContainsString('Get in touch', $html);
    }

    public function test_the_call_to_action_omits_a_button_with_no_label(): void
    {
        $this->page([$this->block(Blocks\CtaBlock::type(), [
            'heading' => ['en' => 'Ready to explore?'],
            'button_label' => [],
            'button_url' => '/contact-us',
        ])]);

        $this->get('/guide')->assertSuccessful()->assertDontSee('href="/contact-us"', false);
    }
}
