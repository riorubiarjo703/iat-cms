<?php

namespace App\Console\Commands;

use App\Models\DistrictPlace;
use App\Models\Facility;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Slimani\MediaManager\Models\File;
use Slimani\MediaManager\Models\Folder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Moves the images uploaded before the media library existed into it, and
 * repoints everything that referenced them.
 *
 * Two phases. The first imports every file under the public disk's uploads/
 * directory, rebuilding that directory tree as media-manager folders so the
 * library opens on familiar groupings. The second rewrites every stored
 * reference — two model columns, two site settings and the block payload of
 * every page — from a path to the id of the imported file.
 *
 * Safe to run more than once: a file already imported is recognised by the
 * legacy path recorded against it, and a reference already rewritten is no
 * longer a path so nothing matches it.
 *
 * The originals under uploads/ are left where they are. Nothing points at them
 * afterwards, but deleting the only copy of the site's photographs is not
 * something a migration should do on its own — remove them once you are
 * satisfied, with `--prune`.
 */
class MigrateUploadsToMediaLibrary extends Command
{
    protected $signature = 'media:migrate-uploads
        {--dry-run : Report what would change without writing anything}
        {--prune : Delete the original uploads/ files once every reference has been rewritten}';

    protected $description = 'Import legacy uploads into the media library and repoint every reference at it';

    /** @var array<string, int> legacy path => media file id */
    private array $map = [];

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('Dry run — nothing will be written.');
        }

        $this->import();
        $rewritten = $this->relink();

        if ($this->option('prune')) {
            $this->prune();
        }

        $this->newLine();
        $this->info(sprintf(
            '%d file(s) in the library, %d reference(s) rewritten.',
            count($this->map),
            $rewritten,
        ));

        return self::SUCCESS;
    }

    // ── Phase one: the files ────────────────────────────────────────────

    private function import(): void
    {
        $disk = Storage::disk('public');
        $paths = collect($disk->allFiles('uploads'))
            // Ignore anything that is not an image or a video: the uploads
            // tree has only ever held media, but a stray .DS_Store must not
            // become a library entry.
            ->filter(fn (string $path): bool => in_array(
                strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'mp4', 'webm', 'mov'],
                true,
            ))
            ->sort()
            ->values();

        $this->line("Found {$paths->count()} file(s) under uploads/.");

        foreach ($paths as $path) {
            $existing = $this->findImported($path);

            if ($existing !== null) {
                $this->map[$path] = $existing->id;
                $this->line("  = {$path} (already imported, #{$existing->id})");

                continue;
            }

            if ($this->dryRun) {
                // A placeholder id, so the relink phase below can still report
                // what it would rewrite. A dry run that could only preview half
                // the operation would hide the half that matters.
                $this->map[$path] = -(count($this->map) + 1);
                $this->line("  + {$path}");

                continue;
            }

            $file = File::create([
                'name' => pathinfo($path, PATHINFO_FILENAME),
                'folder_id' => $this->folderFor($path),
            ]);

            $media = $file->addMedia($disk->path($path))
                ->preservingOriginal()
                // The legacy path is how a re-run recognises this file, and how
                // anyone auditing the library later can tell where it came from.
                ->withCustomProperties(['legacy_path' => $path])
                ->toMediaCollection('default');

            $file->update([
                'size' => $media->size,
                'mime_type' => $media->mime_type,
                'extension' => $media->extension,
            ]);

            $this->map[$path] = $file->id;
            $this->line("  + {$path} → #{$file->id}");
        }
    }

    private function findImported(string $path): ?File
    {
        $media = Media::query()
            ->where('model_type', File::class)
            ->whereJsonContains('custom_properties->legacy_path', $path)
            ->first();

        return $media ? File::query()->find($media->model_id) : null;
    }

    /**
     * Rebuilds "uploads/pages/awards" as folder "pages" containing "awards",
     * and returns the id of the innermost one. The uploads segment itself is
     * dropped — it named the disk directory, not a grouping an editor cares
     * about.
     */
    private function folderFor(string $path): ?int
    {
        $segments = explode('/', trim(dirname($path), '/'));
        $segments = array_values(array_filter($segments, fn (string $s): bool => $s !== '' && $s !== 'uploads' && $s !== '.'));

        $parentId = null;

        foreach ($segments as $segment) {
            $parentId = Folder::firstOrCreate(['name' => $segment, 'parent_id' => $parentId])->id;
        }

        return $parentId;
    }

    // ── Phase two: the references ───────────────────────────────────────

    private function relink(): int
    {
        $count = 0;

        foreach (DistrictPlace::all() as $place) {
            $count += $this->rewriteColumn($place, 'image');
        }

        foreach (Facility::all() as $facility) {
            $count += $this->rewriteColumn($facility, 'image');
        }

        $settings = SiteSetting::singleton();

        foreach (['logo', 'favicon'] as $column) {
            $count += $this->rewriteColumn($settings, $column);
        }

        foreach (Page::all() as $page) {
            $payload = $page->builder_payload;

            if (! is_array($payload)) {
                continue;
            }

            $changed = 0;
            $rewritten = $this->rewriteTree($payload, $changed);

            if ($changed === 0) {
                continue;
            }

            $count += $changed;
            $this->line("  ~ page \"{$page->slug}\": {$changed} reference(s)");

            if (! $this->dryRun) {
                $page->update(['builder_payload' => $rewritten]);
            }
        }

        return $count;
    }

    private function rewriteColumn(object $record, string $column): int
    {
        $value = $record->{$column};

        if (! is_string($value) || ! isset($this->map[$value])) {
            return 0;
        }

        $this->line('  ~ '.class_basename($record)."#{$record->id}.{$column}: {$value} → #{$this->map[$value]}");

        if (! $this->dryRun) {
            $record->update([$column => (string) $this->map[$value]]);
        }

        return 1;
    }

    /**
     * Every string anywhere in a block payload that names an imported file
     * becomes that file's id.
     *
     * Walking the whole tree rather than the keys known to hold images — image,
     * vision_image, photo, and whatever the next block introduces — means a new
     * block type needs no change here. The values being matched are full paths
     * that only ever came from an upload field, so nothing else can collide
     * with one.
     */
    private function rewriteTree(array $node, int &$changed): array
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->rewriteTree($value, $changed);

                continue;
            }

            if (is_string($value) && isset($this->map[$value])) {
                $node[$key] = (string) $this->map[$value];
                $changed++;
            }
        }

        return $node;
    }

    // ── Optional cleanup ────────────────────────────────────────────────

    private function prune(): void
    {
        $disk = Storage::disk('public');
        $remaining = $this->remainingPathReferences();

        if ($remaining !== []) {
            $this->error('Not pruning: '.count($remaining).' reference(s) still hold a path.');

            foreach (array_slice($remaining, 0, 10) as $reference) {
                $this->line("  ! {$reference}");
            }

            return;
        }

        foreach (array_keys($this->map) as $path) {
            $this->line("  - {$path}");

            if (! $this->dryRun) {
                $disk->delete($path);
            }
        }
    }

    /** @return array<int, string> */
    private function remainingPathReferences(): array
    {
        $remaining = [];

        foreach (DistrictPlace::all() as $place) {
            if (is_string($place->image) && str_contains($place->image, '/')) {
                $remaining[] = "district_place#{$place->id}.image = {$place->image}";
            }
        }

        foreach (Facility::all() as $facility) {
            if (is_string($facility->image) && str_contains($facility->image, '/')) {
                $remaining[] = "facility#{$facility->id}.image = {$facility->image}";
            }
        }

        foreach (Page::all() as $page) {
            foreach ($this->stringsIn($page->builder_payload ?? []) as $value) {
                if (str_starts_with($value, 'uploads/')) {
                    $remaining[] = "page \"{$page->slug}\" = {$value}";
                }
            }
        }

        return $remaining;
    }

    /** @return array<int, string> */
    private function stringsIn(array $node): array
    {
        $out = [];

        foreach ($node as $value) {
            if (is_array($value)) {
                $out = array_merge($out, $this->stringsIn($value));
            } elseif (is_string($value)) {
                $out[] = $value;
            }
        }

        return $out;
    }
}
