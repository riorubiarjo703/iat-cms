<?php

namespace Tests\Feature\Seeders;

use App\Models\DistrictPlace;
use App\Models\Facility;
use App\Models\MenuItem;
use App\Models\Page;
use App\PageBuilder\BlockData;
use App\Models\SiteSetting;
use App\Models\Stat;
use Database\Seeders\HomepageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Http::fake(['scbd.com/*' => Http::response('image-bytes', 200)]);
    }

    public function test_it_seeds_every_content_type(): void
    {
        $this->seed(HomepageSeeder::class);

        $this->assertSame(1, SiteSetting::query()->count());
        $this->assertSame(5, MenuItem::query()->count());
        $this->assertSame(1, Page::query()->where('is_homepage', true)->count());
        $this->assertSame(3, DistrictPlace::query()->count());
        $this->assertSame(4, Facility::query()->count());
        $this->assertSame(3, Stat::query()->count());
    }

    public function test_it_seeds_district_place_copy(): void
    {
        $this->seed(HomepageSeeder::class);

        $first = DistrictPlace::query()->ordered()->first();

        $this->assertSame('The towers', $first->t('title', 'en'));
        $this->assertSame('Grade A office', $first->t('caption', 'en'));
    }

    public function test_it_seeds_facility_copy(): void
    {
        $this->seed(HomepageSeeder::class);

        $clinic = Facility::query()->ordered()->get()->firstWhere(fn ($f) => $f->t('title', 'en') === 'District clinic');

        $this->assertNotNull($clinic);
        $this->assertSame('District clinic', $clinic->t('title', 'en'));
    }

    public function test_it_seeds_stat_labels(): void
    {
        $this->seed(HomepageSeeder::class);

        $this->assertSame(
            ['Hectares masterplanned', 'Established', 'District security & response'],
            Stat::query()->ordered()->get()->map(fn ($s) => $s->t('label', 'en'))->all(),
        );
    }

    public function test_it_seeds_all_three_locales_into_the_homepage_blocks(): void
    {
        // Homepage copy lives in the block payload now, not in a content row.
        $this->seed(HomepageSeeder::class);

        $hero = collect(Page::homepage()->blocks())->firstWhere('type', 'scbd_hero');

        $this->assertSame("A district\nthat never\nclocks out", BlockData::t($hero['data'], 'heading', 'en'));
        $this->assertSame("Kawasan\nyang tak\npernah tidur", BlockData::t($hero['data'], 'heading', 'id'));
        $this->assertSame("永不\n停歇的\n商务区", BlockData::t($hero['data'], 'heading', 'cn'));
    }

    public function test_the_seeded_homepage_actually_renders(): void
    {
        // A payload that seeds but does not render would be a broken install.
        $this->seed(HomepageSeeder::class);

        $this->get('/')->assertSuccessful()->assertSee('id="top"', false);
    }

    public function test_it_seeds_four_nav_links_and_one_cta_into_the_header_menu(): void
    {
        $this->seed(HomepageSeeder::class);

        $this->assertSame(
            ['Company', 'District', 'Facilities', 'News'],
            MenuItem::query()->links()->orderBy('sort')->get()->map(fn ($i) => $i->t('label', 'en'))->all(),
        );
        $this->assertSame('Leasing enquiry', MenuItem::query()->cta()->first()->t('label', 'en'));
        $this->assertSame('Ajukan sewa', MenuItem::query()->cta()->first()->t('label', 'id'));
    }

    public function test_re_seeding_does_not_overwrite_edited_contact_details(): void
    {
        // These are admin-edited organisation facts; a re-seed replacing a real
        // address with the reference one would be data loss.
        $this->seed(HomepageSeeder::class);
        SiteSetting::singleton()->update(['contact_address' => 'A real address']);

        $this->seed(HomepageSeeder::class);

        $this->assertSame('A real address', SiteSetting::singleton()->contact_address);
    }

    public function test_re_seeding_leaves_an_edited_homepage_alone(): void
    {
        // It may have been rearranged in the builder since.
        $this->seed(HomepageSeeder::class);
        Page::homepage()->update(['builder_payload' => []]);

        $this->seed(HomepageSeeder::class);

        $this->assertSame([], Page::homepage()->blocks());
    }

    public function test_it_seeds_the_established_stat_as_plain_format(): void
    {
        $this->seed(HomepageSeeder::class);

        $established = Stat::query()->get()->firstWhere(fn ($s) => (int) $s->value === 1987);

        $this->assertTrue($established->isPlain());
    }

    /**
     * The reference design's About CTA is an in-page anchor. It previously
     * pointed at /pages/company-profile, a Graper page route that exists but
     * was never seeded — a 404 on a fresh install.
     */
    public function test_the_about_cta_points_at_an_anchor_that_exists_on_a_fresh_install(): void
    {
        $this->seed(HomepageSeeder::class);

        $about = collect(Page::homepage()->blocks())->firstWhere('type', 'scbd_about');

        $this->assertSame('#contact', $about['data']['cta_url']);
        $this->get('/')->assertSee('href="#contact"', false);
    }

    public function test_it_attaches_downloaded_images(): void
    {
        $this->seed(HomepageSeeder::class);

        $hero = collect(Page::homepage()->blocks())->firstWhere('type', 'scbd_hero');

        $this->assertSame('uploads/homepage/hero1.jpg', $hero['data']['image']);
        Storage::disk('public')->assertExists('uploads/homepage/hero1.jpg');
        $this->assertNotNull(Facility::query()->ordered()->first()->image);
    }

    public function test_it_leaves_images_null_when_the_host_is_unreachable(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('offline'));

        $this->seed(HomepageSeeder::class);

        $hero = collect(Page::homepage()->blocks())->firstWhere('type', 'scbd_hero');

        $this->assertNull($hero['data']['image']);
        $this->assertSame(3, DistrictPlace::query()->count(), 'Content must still seed without images.');
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(HomepageSeeder::class);
        $this->seed(HomepageSeeder::class);

        $this->assertSame(3, DistrictPlace::query()->count());
        $this->assertSame(5, MenuItem::query()->count());
        $this->assertSame(1, Page::query()->where('is_homepage', true)->count());
    }

    public function test_it_does_not_overwrite_editor_changes_to_list_content(): void
    {
        $this->seed(HomepageSeeder::class);
        DistrictPlace::query()->ordered()->first()->update(['title' => ['en' => 'Renamed by editor']]);

        $this->seed(HomepageSeeder::class);

        $this->assertSame('Renamed by editor', DistrictPlace::query()->ordered()->first()->t('title', 'en'));
    }
}
