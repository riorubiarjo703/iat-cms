<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\BuildPage;
use App\Models\Page;
use App\PageBuilder\Blocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Slimani\MediaManager\Models\File;
use Tests\Support\ActsAsSuperAdmin;
use Tests\TestCase;

/**
 * Opening a block whose image field still holds a legacy "uploads/..." path.
 *
 * MediaPicker hands its state straight to File::find(). On Postgres a path
 * there is a 22P02 and the edit screen 500s; on the SQLite these tests run
 * against it is a silent null, so the driver will never surface this. What is
 * asserted instead is the thing that actually went wrong — a path reaching the
 * picker at all.
 */
class MediaFieldLegacyPathTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsSuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        // BuildPage now gates on pages.update (C1 of the roles/permissions
        // final review) — a roleless actor 403s before this test's own
        // assertions ever run.
        $this->actingAsSuperAdmin();
    }

    /** A file as media:migrate-uploads leaves it: imported, with its origin recorded. */
    private function importedFrom(string $legacyPath): File
    {
        $file = File::create(['name' => pathinfo($legacyPath, PATHINFO_FILENAME)]);

        $file->addMediaFromString(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ))
            ->usingFileName(basename($legacyPath))
            ->withCustomProperties(['legacy_path' => $legacyPath])
            ->toMediaCollection('default');

        return $file;
    }

    /**
     * The value the picker will hand to File::find().
     *
     * FileUpload keys its state by uuid, so the stored reference sits inside
     * that array rather than being the state itself — which is why the crash
     * was not visible from the payload alone.
     */
    private function hydratedImage(Page $page): mixed
    {
        $state = Livewire::test(BuildPage::class, ['record' => $page->id])
            ->call('editBlock', 'block_1')
            ->get('blockData.image');

        return array_values((array) $state)[0] ?? null;
    }

    private function pageHolding(string $image): Page
    {
        return Page::create([
            'title' => ['en' => 'Profile'], 'slug' => 'profile',
            'type' => Page::TYPE_BUILDER, 'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => [[
                'id' => 'block_1',
                'type' => Blocks\AboutBlock::type(),
                'data' => ['heading' => ['en' => 'About'], 'image' => $image],
                'children' => null,
            ]],
        ]);
    }

    public function test_a_block_holding_a_legacy_path_opens_on_the_imported_file(): void
    {
        $file = $this->importedFrom('uploads/pages/profile-hero.jpg');
        $page = $this->pageHolding('uploads/pages/profile-hero.jpg');

        $this->assertSame((string) $file->id, $this->hydratedImage($page));
    }

    public function test_a_block_holding_a_path_that_was_never_imported_opens_empty(): void
    {
        // Empty rather than carrying the path: an unresolvable value is what
        // reached File::find() and took the screen down.
        $page = $this->pageHolding('uploads/pages/never-imported.jpg');

        $this->assertNull($this->hydratedImage($page));
    }

    public function test_a_block_holding_an_id_is_untouched(): void
    {
        $file = $this->importedFrom('uploads/pages/profile-hero.jpg');
        $page = $this->pageHolding((string) $file->id);

        $this->assertSame((string) $file->id, $this->hydratedImage($page));
    }

    public function test_nothing_a_block_hands_the_picker_is_ever_a_path(): void
    {
        // The property the crash turned on: File::find() must never be given
        // something Postgres cannot read as a bigint.
        $this->importedFrom('uploads/pages/profile-hero.jpg');

        foreach (['uploads/pages/profile-hero.jpg', 'uploads/pages/never-imported.jpg'] as $stored) {
            $page = $this->pageHolding($stored);
            $value = $this->hydratedImage($page);

            $this->assertTrue(
                $value === null || ctype_digit((string) $value),
                "Stored {$stored} reached the picker as ".var_export($value, true),
            );

            $page->delete();
        }
    }
}
