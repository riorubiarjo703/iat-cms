<?php

namespace Database\Seeders;

use App\Models\DistrictPlace;
use App\Models\Facility;
use App\Models\HomepageContent;
use App\Models\PublicMenuItem;
use App\Models\SiteSetting;
use App\Models\Stat;
use App\Support\ReferenceImageFetcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class HomepageSeeder extends Seeder
{
    public function __construct(private readonly ReferenceImageFetcher $images = new ReferenceImageFetcher) {}

    public function run(): void
    {
        $data = require database_path('seeders/data/homepage.php');

        $this->seedContent($data['content']);
        $this->seedSettings($data['settings']);
        $this->seedList(PublicMenuItem::class, $data['menu'], 'uploads/menu');
        $this->seedList(DistrictPlace::class, $data['places'], 'uploads/district');
        $this->seedList(Facility::class, $data['facilities'], 'uploads/facilities');
        $this->seedList(Stat::class, $data['stats'], 'uploads/stats');
    }

    /**
     * Singletons are updated in place so re-seeding refreshes reference copy.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function seedContent(array $attributes): void
    {
        $heroSlot = $attributes['hero_image_slot'] ?? null;
        $aboutSlot = $attributes['about_image_slot'] ?? null;
        unset($attributes['hero_image_slot'], $attributes['about_image_slot']);

        $attributes['hero_image'] = $heroSlot ? $this->images->fetch($heroSlot, 'uploads/homepage') : null;
        $attributes['about_image'] = $aboutSlot ? $this->images->fetch($aboutSlot, 'uploads/homepage') : null;

        HomepageContent::singleton()->update($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedSettings(array $attributes): void
    {
        SiteSetting::singleton()->update($attributes);
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
