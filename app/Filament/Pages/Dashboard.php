<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ContentStats;
use App\Filament\Widgets\QuickActions;
use App\Filament\Widgets\TranslationCoveragePanel;
use App\Filament\Widgets\WelcomeBanner;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Composes the dashboard widgets and holds no queries of its own — every
 * number comes from App\Support\ContentHealth or App\Support\TranslationCoverage.
 */
class Dashboard extends BaseDashboard
{
    /** The banner greets the user by name, so a second heading is redundant. */
    public function getHeading(): string
    {
        return '';
    }

    /** @return array<int, class-string> */
    public function getWidgets(): array
    {
        return [
            WelcomeBanner::class,
            ContentStats::class,
            TranslationCoveragePanel::class,
            QuickActions::class,
        ];
    }

    /** @return int|array<string, int|null> */
    public function getColumns(): int|array
    {
        return 1;
    }
}
