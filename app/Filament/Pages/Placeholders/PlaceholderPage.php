<?php

namespace App\Filament\Pages\Placeholders;

use BackedEnum;
use Filament\Pages\Page;

/**
 * A navigable destination for a feature that does not exist yet.
 *
 * The sidebar deliberately shows the product's full intended shape, so most of
 * its entries lead here. The page states plainly that the feature is unbuilt —
 * it never renders empty tables, dummy charts or invented counts, because an
 * editor must not be able to mistake "not built" for "broken".
 */
abstract class PlaceholderPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-square-3-stack-3d';

    /** Non-static on Filament 5. */
    protected string $view = 'filament.pages.placeholder';

    /** One line describing what the feature will do, shown under the title. */
    abstract public static function summary(): string;

    /** Which slice will build it, or null when unscheduled. */
    public static function plannedIn(): ?string
    {
        return null;
    }

    public function getHeading(): string
    {
        return static::getNavigationLabel();
    }

    public function getSubheading(): ?string
    {
        return static::summary();
    }

    /**
     * Placeholders are reachable but excluded from global search — returning
     * them among real results would waste an editor's time.
     */
    public static function canGloballySearch(): bool
    {
        return false;
    }
}
