<x-filament-panels::page>
    <div class="fi-placeholder mx-auto flex max-w-xl flex-col items-center gap-4 rounded-xl border border-gray-200 bg-white px-8 py-14 text-center dark:border-white/10 dark:bg-white/5">
        <div class="rounded-xl bg-primary-50 p-3 text-primary-600 dark:bg-primary-500/10">
            <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="h-6 w-6" />
        </div>

        <h2 class="text-base font-semibold text-gray-950 dark:text-white">
            {{ static::getNavigationLabel() }} isn’t built yet
        </h2>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ static::summary() }}
        </p>

        @if (static::plannedIn())
            <p class="text-xs text-gray-400 dark:text-gray-500">
                Planned in {{ static::plannedIn() }}.
            </p>
        @else
            <p class="text-xs text-gray-400 dark:text-gray-500">
                Not yet scheduled.
            </p>
        @endif

        <p class="text-xs text-gray-400 dark:text-gray-500">
            This screen is a placeholder so the menu shows the product’s full shape.
            Nothing here is functional.
        </p>
    </div>
</x-filament-panels::page>
