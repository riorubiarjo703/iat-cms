<?php

namespace Database\Seeders;

use App\Models\DistrictPlace;
use App\Models\Facility;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Stat;
use App\PageBuilder\HomepagePayload;
use App\Support\MenuLocations;
use App\Support\ReferenceImageFetcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Builds a working SCBD site from the reference data.
 *
 * Since the homepage became an ordinary page, seeding it means creating that
 * page with its block payload — there is no separate homepage content record
 * any more. The header menu is seeded too, because the header renders whatever
 * is assigned to the header location and would otherwise be empty.
 */
class HomepageSeeder extends Seeder
{
    public function __construct(private readonly ReferenceImageFetcher $images = new ReferenceImageFetcher) {}

    public function run(): void
    {
        $data = require database_path('seeders/data/homepage.php');

        $this->seedSettings($data['settings'], $data['content']);
        $this->seedList(DistrictPlace::class, $data['places'], 'uploads/district');
        $this->seedList(Facility::class, $data['facilities'], 'uploads/facilities');
        $this->seedList(Stat::class, $data['stats'], 'uploads/stats');
        $this->seedHeaderMenu($data['menu']);
        $this->seedHomepage($data['content']);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $content
     */
    private function seedSettings(array $settings, array $content): void
    {
        $record = SiteSetting::singleton();

        // The brand subtitle and contact details are organisation facts; they
        // sit in Site Settings rather than in homepage copy.
        //
        // Only filled in where blank. These are edited in the admin, and a
        // re-seed that overwrote them would silently replace a real address
        // with the reference one.
        $seedable = [
            'brand_subtitle' => $content['brand_sub'] ?? null,
            'contact_email' => $content['contact_email'] ?? null,
            'contact_phone' => $content['contact_phone'] ?? null,
            'contact_address' => $content['contact_address'] ?? null,
        ];

        foreach ($seedable as $column => $value) {
            if (blank($record->getAttributes()[$column] ?? null)) {
                $settings[$column] = $value;
            }
        }

        $record->update($settings);
    }

    /**
     * Seeds only into an empty header menu, so an edited menu survives a
     * re-seed.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function seedHeaderMenu(array $rows): void
    {
        if (Menu::query()->exists()) {
            return;
        }

        // The slug is set explicitly rather than left to Menu's creating hook:
        // DatabaseSeeder runs WithoutModelEvents, so that hook does not fire and
        // the insert would violate the NOT NULL constraint.
        $menu = Menu::create([
            'name' => 'Main Navigation',
            'slug' => 'main-navigation',
            'location' => MenuLocations::HEADER,
        ]);

        foreach ($rows as $row) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'type' => MenuItem::TYPE_CUSTOM,
                'label' => $row['label'],
                'url' => $row['url'] ?? '#',
                'target' => $row['target'] ?? '_self',
                'is_cta' => (bool) ($row['is_cta'] ?? false),
                'is_active' => (bool) ($row['is_active'] ?? true),
                'sort' => $row['sort'] ?? 0,
            ]);
        }
    }

    /**
     * The homepage is created once. Re-seeding leaves an existing one alone —
     * it may have been rearranged in the builder since.
     *
     * @param  array<string, mixed>  $content
     */
    private function seedHomepage(array $content): void
    {
        if (Page::query()->where('is_homepage', true)->exists()) {
            return;
        }

        $heroSlot = $content['hero_image_slot'] ?? null;
        $aboutSlot = $content['about_image_slot'] ?? null;

        $content['hero_image'] = $heroSlot ? $this->images->fetch($heroSlot, 'uploads/homepage') : null;
        $content['about_image'] = $aboutSlot ? $this->images->fetch($aboutSlot, 'uploads/homepage') : null;

        Page::create([
            'title' => ['en' => 'Home'],
            'slug' => 'home',
            'type' => Page::TYPE_BUILDER,
            'builder_payload' => HomepagePayload::fromContent($content),
            'status' => Page::STATUS_PUBLISHED,
            'is_homepage' => true,
        ]);
    }

    /**
     * List content seeds only into an empty table, so editor changes survive a
     * re-seed and rows are never duplicated.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function seedList(string $model, array $rows, string $directory): void
    {
        if ($model::query()->exists()) {
            return;
        }

        foreach ($rows as $row) {
            $slot = $row['image_slot'] ?? null;
            unset($row['image_slot']);

            if ($slot !== null) {
                $row['image'] = $this->images->fetch($slot, $directory);
            }

            $model::query()->create($row);
        }
    }
}
