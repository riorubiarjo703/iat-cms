<?php

namespace App\Filament\Widgets;

use App\Support\TranslationCoverage;
use Filament\Widgets\Widget;

class TranslationCoveragePanel extends Widget
{
    protected string $view = 'filament.widgets.translation-coverage';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;


    /**
     * Rendered with the page rather than lazily. These are a few COUNT queries
     * on a company-profile-sized dataset, so the extra round trip buys nothing
     * — and a lazy widget's render failure never reaches the initial response,
     * which is how a broken dashboard passed its tests once already. If
     * coverage ever gets slow, cache it rather than hiding it behind a spinner.
     */
    protected static bool $isLazy = false;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $coverage = app(TranslationCoverage::class);

        return [
            'hasContent' => $coverage->hasTranslatableContent(),
            'locales' => $coverage->perLocale(),
        ];
    }
}
