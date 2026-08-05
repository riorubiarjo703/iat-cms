<?php

namespace Tests\Unit\Models;

use App\Models\DistrictPlace;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderedContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_ordered_scope_sorts_by_sort_ascending(): void
    {
        DistrictPlace::create(['title' => ['en' => 'Third'], 'sort' => 3]);
        DistrictPlace::create(['title' => ['en' => 'First'], 'sort' => 1]);
        DistrictPlace::create(['title' => ['en' => 'Second'], 'sort' => 2]);

        $titles = DistrictPlace::query()->ordered()->get()->map(fn ($p) => $p->t('title', 'en'));

        $this->assertSame(['First', 'Second', 'Third'], $titles->all());
    }

    public function test_ordered_scope_orders_by_sort_then_id(): void
    {
        $sql = DistrictPlace::query()->ordered()->toSql();

        // The id tiebreak keeps ordering stable when rows share a sort value.
        $this->assertMatchesRegularExpression(
            '/order by .*"sort" asc, .*"id" asc/i',
            $sql,
            "scopeOrdered must break ties on id; got: {$sql}",
        );
    }

    public function test_active_scope_excludes_inactive_rows(): void
    {
        DistrictPlace::create(['title' => ['en' => 'Shown'], 'is_active' => true]);
        DistrictPlace::create(['title' => ['en' => 'Hidden'], 'is_active' => false]);

        $titles = DistrictPlace::query()->active()->get()->map(fn ($p) => $p->t('title', 'en'));

        $this->assertSame(['Shown'], $titles->all());
    }

    public function test_rows_default_to_active(): void
    {
        $this->assertTrue(DistrictPlace::create(['title' => ['en' => 'X']])->fresh()->is_active);
        $this->assertTrue(Facility::create(['title' => ['en' => 'Y']])->fresh()->is_active);
    }

    public function test_district_place_translatable_fields(): void
    {
        $this->assertSame(
            ['title', 'caption', 'body', 'tags', 'stat_label'],
            (new DistrictPlace)->translatableFields(),
        );
    }

    public function test_facility_translatable_fields(): void
    {
        $this->assertSame(
            ['title', 'eyebrow', 'body', 'stat_label'],
            (new Facility)->translatableFields(),
        );
    }

    /**
     * The stat figure is deliberately not translatable — it is one string for
     * every language, and the cast list is what decides that.
     */
    public function test_stat_value_is_not_translatable(): void
    {
        $this->assertNotContains('stat_value', (new DistrictPlace)->translatableFields());
        $this->assertNotContains('stat_value', (new Facility)->translatableFields());

        $place = DistrictPlace::create(['title' => ['en' => 'Towers'], 'stat_value' => '18K+']);

        $this->assertSame('18K+', $place->fresh()->stat_value);
    }

    public function test_district_place_tag_list_splits_on_commas(): void
    {
        $place = DistrictPlace::create([
            'title' => ['en' => 'Towers'],
            'tags' => ['en' => 'Artha Graha, Plaza Sudirman ,Landmark Tower'],
        ]);

        $this->assertSame(
            ['Artha Graha', 'Plaza Sudirman', 'Landmark Tower'],
            $place->tagList('en'),
        );
    }

    public function test_district_place_tag_list_drops_blanks(): void
    {
        $place = DistrictPlace::create([
            'title' => ['en' => 'Towers'],
            'tags' => ['en' => 'Galleries, , Events,'],
        ]);

        $this->assertSame(['Galleries', 'Events'], $place->tagList('en'));
    }

    public function test_district_place_tag_list_is_empty_without_tags(): void
    {
        $place = DistrictPlace::create(['title' => ['en' => 'Towers']]);

        $this->assertSame([], $place->tagList('en'));
    }

    public function test_facility_falls_back_to_english(): void
    {
        $facility = Facility::create([
            'title' => ['en' => 'Fire Service', 'id' => 'Pemadam Kebakaran'],
            'body' => ['en' => 'Run in-house, around the clock.'],
        ]);

        $this->assertSame('Pemadam Kebakaran', $facility->t('title', 'id'));
        $this->assertSame('Run in-house, around the clock.', $facility->t('body', 'id'));
    }
}
