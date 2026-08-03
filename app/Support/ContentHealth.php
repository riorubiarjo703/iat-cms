<?php

namespace App\Support;

use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\Page;
use App\Models\PublicMenuItem;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Every dashboard query lives here, so the numbers can be tested without
 * rendering a widget and no widget can quietly introduce an N+1.
 *
 * Counts return null — not zero — when the underlying table is absent. Zero is
 * a legitimate answer meaning "nothing yet"; null means "cannot say", and the
 * dashboard renders those as an em dash rather than implying emptiness.
 */
class ContentHealth
{
    /** @var array<string, bool> */
    private array $loggedMissing = [];

    public function pages(): ?int
    {
        return $this->count(Page::class);
    }

    public function publishedPosts(): ?int
    {
        return $this->count(BlogPost::class, fn ($query) => $query->where('status', BlogPost::STATUS_PUBLISHED));
    }

    /** Drafts and scheduled posts — the amber pill on the Posts card. */
    public function pendingPosts(): ?int
    {
        return $this->count(BlogPost::class, fn ($query) => $query->whereIn('status', [
            BlogPost::STATUS_DRAFT,
            BlogPost::STATUS_SCHEDULED,
        ]));
    }

    public function mediaFiles(): ?int
    {
        // The media manager is a third-party package; if it is uninstalled or
        // its migrations are unrun, the tile says so instead of throwing.
        if (! class_exists(\Slimani\MediaManager\Models\File::class)) {
            return null;
        }

        return $this->count(\Slimani\MediaManager\Models\File::class);
    }

    public function users(): ?int
    {
        return $this->count(User::class);
    }

    public function menuItems(): ?int
    {
        return $this->count(PublicMenuItem::class);
    }

    /** Locales the site is configured for, not locales that have content. */
    public function localesConfigured(): int
    {
        return count(SiteSetting::LOCALES);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  (callable(\Illuminate\Database\Eloquent\Builder): \Illuminate\Database\Eloquent\Builder)|null  $scope
     */
    private function count(string $model, ?callable $scope = null): ?int
    {
        try {
            $instance = new $model;

            if (! Schema::connection($instance->getConnectionName())->hasTable($instance->getTable())) {
                $this->logMissingOnce($instance->getTable());

                return null;
            }

            $query = $model::query();

            return (int) ($scope ? $scope($query) : $query)->count();
        } catch (Throwable $e) {
            $this->logMissingOnce($model, $e);

            return null;
        }
    }

    private function logMissingOnce(string $key, ?Throwable $e = null): void
    {
        if (isset($this->loggedMissing[$key])) {
            return;
        }

        $this->loggedMissing[$key] = true;

        Log::warning("Dashboard could not count [{$key}]", ['exception' => $e?->getMessage()]);
    }
}
