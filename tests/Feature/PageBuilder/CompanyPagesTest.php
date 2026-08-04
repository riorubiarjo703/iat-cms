<?php

namespace Tests\Feature\PageBuilder;

use App\Models\Page;
use App\PageBuilder\BlockRegistry;
use App\PageBuilder\Blocks;
use Database\Seeders\CompanyPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyPagesTest extends TestCase
{
    use RefreshDatabase;

    private function page(array $blocks, string $slug = 'built'): Page
    {
        return Page::create([
            'title' => ['en' => 'Built'], 'slug' => $slug,
            'type' => Page::TYPE_BUILDER, 'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => $blocks,
        ]);
    }

    private function block(string $type, array $data, string $id = 'block_1'): array
    {
        return ['id' => $id, 'type' => $type, 'data' => $data, 'children' => null];
    }

    public function test_the_three_new_blocks_are_registered_with_views(): void
    {
        $registry = app(BlockRegistry::class);

        foreach ([Blocks\TimelineBlock::class, Blocks\PeopleBlock::class, Blocks\AwardsBlock::class] as $class) {
            $this->assertTrue($registry->has($class::type()));
            $this->assertTrue(view()->exists($class::renderView()), "[{$class::type()}] has no view");
        }
    }

    public function test_the_timeline_keeps_its_declared_order(): void
    {
        // A timeline out of order is worse than no timeline.
        $this->page([$this->block(Blocks\TimelineBlock::type(), [
            'entries' => [
                ['year' => '1987', 'title' => 'First'],
                ['year' => '1995', 'title' => 'Second'],
                ['year' => '2017', 'title' => 'Third'],
            ],
        ])]);

        $html = $this->get('/built')->getContent();

        $this->assertLessThan(strpos($html, 'Second'), strpos($html, 'First'));
        $this->assertLessThan(strpos($html, 'Third'), strpos($html, 'Second'));
    }

    public function test_a_timeline_entry_renders_without_an_image_or_body(): void
    {
        $this->page([$this->block(Blocks\TimelineBlock::type(), [
            'entries' => [['year' => '1995', 'title' => 'Bare entry']],
        ])]);

        $this->get('/built')->assertSuccessful()->assertSee('Bare entry', false);
    }

    public function test_an_empty_timeline_is_omitted(): void
    {
        $this->page([$this->block(Blocks\TimelineBlock::type(), ['entries' => []])]);

        $this->get('/built')->assertDontSee('id="milestones"', false);
    }

    public function test_people_render_in_their_groups(): void
    {
        $this->page([$this->block(Blocks\PeopleBlock::type(), [
            'groups' => [
                ['title' => 'Board of Commissioners', 'people' => [['name' => 'Alpha', 'role' => 'Chair']]],
                ['title' => 'Board of Directors', 'people' => [['name' => 'Bravo', 'role' => 'Director']]],
            ],
        ])]);

        $html = $this->get('/built')->getContent();

        $this->assertStringContainsString('Board of Commissioners', $html);
        $this->assertStringContainsString('Alpha', $html);
        $this->assertLessThan(strpos($html, 'Bravo'), strpos($html, 'Alpha'));
    }

    public function test_a_person_without_a_photo_still_renders(): void
    {
        // One board member's portrait was not among the usable assets.
        $this->page([$this->block(Blocks\PeopleBlock::type(), [
            'groups' => [['title' => 'Secretary', 'people' => [['name' => 'No Photo', 'role' => 'Secretary']]]],
        ])]);

        $this->get('/built')->assertSuccessful()->assertSee('No Photo', false);
    }

    public function test_a_group_with_no_named_people_is_dropped(): void
    {
        // The + operator keeps the left-hand key, so filtering people used to
        // silently do nothing and the group rendered empty.
        $this->page([$this->block(Blocks\PeopleBlock::type(), [
            'groups' => [['title' => 'Empty group', 'people' => [['name' => '']]]],
        ])]);

        $this->get('/built')->assertSuccessful()->assertDontSee('Empty group', false);
    }

    public function test_awards_render_with_and_without_a_year(): void
    {
        $this->page([$this->block(Blocks\AwardsBlock::type(), [
            'items' => [
                ['title' => 'Water Hero', 'year' => '2023'],
                ['title' => 'ISO 9001', 'year' => null],
            ],
        ])]);

        $html = $this->get('/built')->getContent();

        $this->assertStringContainsString('Water Hero', $html);
        $this->assertStringContainsString('2023', $html);
        $this->assertStringContainsString('ISO 9001', $html);
    }

    public function test_an_empty_awards_block_is_omitted(): void
    {
        $this->page([$this->block(Blocks\AwardsBlock::type(), ['items' => []])]);

        $this->get('/built')->assertDontSee('id="awards"', false);
    }

    public function test_the_seeder_fills_all_three_pages_and_they_render(): void
    {
        foreach (['milestone', 'organisation-structure', 'awards-certification'] as $slug) {
            Page::create(['title' => ['en' => $slug], 'slug' => $slug, 'status' => Page::STATUS_PUBLISHED]);
        }

        $this->seed(CompanyPagesSeeder::class);

        $this->get('/milestone')->assertSuccessful()->assertSee('Artha Graha Building', false);
        $this->get('/organisation-structure')->assertSuccessful()->assertSee('Arpin Wiradisastra', false);
        $this->get('/awards-certification')->assertSuccessful()->assertSee('ISO 45001', false);
    }

    public function test_the_seeder_reports_a_missing_page_rather_than_failing(): void
    {
        $this->seed(CompanyPagesSeeder::class);

        $this->assertSame(0, Page::query()->count());
    }

    public function test_every_registered_block_view_exists(): void
    {
        // A typo here degrades to a silent placeholder on the front end.
        foreach (app(BlockRegistry::class)->all() as $type => $class) {
            $this->assertTrue(view()->exists($class::renderView()), "[{$type}] resolves to a missing view");
        }
    }
}
