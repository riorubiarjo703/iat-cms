<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\BuildPage;
use App\Filament\Resources\DistrictPlaces\Pages\EditDistrictPlace;
use App\Models\DistrictPlace;
use App\Models\Page;
use App\Models\SiteSetting;
use App\PageBuilder\Blocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Slimani\MediaManager\Models\File;
use Tests\Support\ActsAsSuperAdmin;
use Tests\TestCase;

/**
 * Where a picked media file actually ends up, in each kind of form this admin
 * uses.
 *
 * MediaPicker is built around a foreign key plus an Eloquent relationship. This
 * codebase stores an id in a plain column and, for blocks, inside a JSON
 * payload with no record behind the field at all. When the picker finds no
 * relationship it falls back to writing the value straight onto the form's
 * record:
 *
 *     $record->{$component->getName()} = $identifiers[0] ?? null;
 *
 * For a block that record would be the Page and the field is named "image", so
 * the fallback would attempt `pages.image` — a column that does not exist, and
 * a fatal one to touch. These tests pin down that it does not happen, in every
 * context, so the arrangement cannot quietly start failing.
 */
class MediaPickerPersistenceTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsSuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        // BuildPage now gates on pages.update, and DistrictPlaceResource now
        // carries a policy (C1 and C4 of the roles/permissions final review)
        // — a roleless actor 403s before this test's own assertions ever run.
        $this->actingAsSuperAdmin();
    }

    private function mediaFile(string $name): File
    {
        $file = File::create(['name' => $name]);
        $file->addMediaFromString(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ))->usingFileName("{$name}.png")->toMediaCollection('default');

        return $file;
    }

    /**
     * The block editor: no record is bound to the schema and saving reads
     * getState() rather than calling saveRelationships(), so the id lands in
     * the JSON payload and the Page is never touched.
     */
    public function test_a_block_field_stores_the_id_in_the_builder_payload(): void
    {
        $file = $this->mediaFile('offices');

        $page = Page::create([
            'title' => ['en' => 'Built'], 'slug' => 'built',
            'type' => Page::TYPE_BUILDER, 'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => [[
                'id' => 'block_1',
                'type' => Blocks\AboutBlock::type(),
                'data' => ['heading' => ['en' => 'About'], 'image' => null],
                'children' => null,
            ]],
        ]);

        Livewire::test(BuildPage::class, ['record' => $page->id])
            ->call('editBlock', 'block_1')
            ->set('blockData.image', (string) $file->id)
            ->call('saveBlock')
            ->assertHasNoErrors();

        $stored = $page->fresh()->blocks()[0]['data']['image'] ?? null;

        $this->assertSame((string) $file->id, (string) $stored, 'the media id was not written into the payload');
    }

    /**
     * A picker nested inside a repeater — awards, milestones, portraits — has
     * a deeper state path than a top-level block field, so it is worth pinning
     * separately.
     */
    public function test_a_repeater_field_stores_the_id_in_the_builder_payload(): void
    {
        $file = $this->mediaFile('certificate');

        $page = Page::create([
            'title' => ['en' => 'Built'], 'slug' => 'built',
            'type' => Page::TYPE_BUILDER, 'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => [[
                'id' => 'block_1',
                'type' => Blocks\AwardsBlock::type(),
                'data' => ['heading' => ['en' => 'Awards'], 'items' => [
                    ['title' => 'ISO 9001', 'year' => '2020', 'image' => null],
                ]],
                'children' => null,
            ]],
        ]);

        $component = Livewire::test(BuildPage::class, ['record' => $page->id])
            ->call('editBlock', 'block_1');

        // Filament keys repeater items by uuid once the form is filled, so the
        // item cannot be addressed as items.0 — that would create a second,
        // empty row rather than edit the existing one.
        $itemKey = array_key_first($component->get('blockData.items'));

        $component
            ->set("blockData.items.{$itemKey}.image", (string) $file->id)
            ->call('saveBlock')
            ->assertHasNoErrors();

        $items = $page->fresh()->blocks()[0]['data']['items'];

        $this->assertCount(1, $items, 'the existing row was duplicated');
        $this->assertSame((string) $file->id, (string) array_values($items)[0]['image']);
        $this->assertSame('ISO 9001', array_values($items)[0]['title'], 'the rest of the row was lost');
    }

    /** The settings screen edits a singleton through getState(), like the builder. */
    public function test_the_settings_screen_stores_the_id_on_the_singleton(): void
    {
        $file = $this->mediaFile('logo');

        Livewire::test(\App\Filament\Pages\SiteSettingsPage::class)
            ->fillForm([
                // The screen's other required fields, so the save under test is
                // not rejected by unrelated validation.
                'site_name' => 'SCBD',
                'default_locale' => 'en',
                'meta_title' => ['en' => 'SCBD'],
                'meta_description' => ['en' => 'Sudirman Central Business District'],
                'logo' => (string) $file->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame((string) $file->id, (string) SiteSetting::singleton()->fresh()->logo);
    }

    /**
     * A resource form is the one place saveRelationships() runs. The column is
     * a plain string, so the picker's no-relationship fallback writes the id
     * onto the record — which is exactly what is wanted here.
     */
    public function test_a_resource_field_stores_the_id_on_the_model(): void
    {
        $file = $this->mediaFile('towers');
        $place = DistrictPlace::create(['title' => ['en' => 'The towers']]);

        Livewire::test(EditDistrictPlace::class, ['record' => $place->id])
            ->fillForm(['image' => (string) $file->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame((string) $file->id, (string) $place->fresh()->image);
    }

    /** Clearing the field must empty it, not leave the previous id behind. */
    public function test_clearing_a_resource_field_empties_the_column(): void
    {
        $file = $this->mediaFile('towers');
        $place = DistrictPlace::create(['title' => ['en' => 'The towers'], 'image' => (string) $file->id]);

        Livewire::test(EditDistrictPlace::class, ['record' => $place->id])
            ->fillForm(['image' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEmpty($place->fresh()->image);
    }

    /**
     * The listing thumbnail. The column holds an id now, so reading it off the
     * public disk would ask for /storage/9 and render a broken image.
     */
    public function test_the_listing_thumbnail_resolves_through_the_library(): void
    {
        $file = $this->mediaFile('towers');
        DistrictPlace::create(['title' => ['en' => 'The towers'], 'image' => (string) $file->id]);

        Livewire::test(\App\Filament\Resources\DistrictPlaces\Pages\ListDistrictPlaces::class)
            ->assertSee($file->getUrl(), false)
            ->assertDontSee('storage/'.$file->id.'"', false);
    }

    /**
     * The hazard itself, named so the reason for the arrangement survives:
     * pages has no image column, and writing one is fatal rather than ignored.
     */
    public function test_writing_an_image_attribute_to_a_page_would_be_fatal(): void
    {
        $page = Page::create([
            'title' => ['en' => 'Built'], 'slug' => 'built',
            'type' => Page::TYPE_BUILDER, 'status' => Page::STATUS_PUBLISHED,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $page->image = 1;
        $page->save();
    }
}
