<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    @foreach ($templates as $key => $template)
        <button
            type="button"
            wire:click="applyTemplate('{{ $key }}')"
            class="fi-btn-template flex flex-col items-start gap-2 rounded-xl border border-gray-200 p-4 text-left transition hover:border-primary-500 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5"
        >
            <span class="flex size-10 items-center justify-center rounded-lg bg-gray-100 dark:bg-white/10">
                <x-filament::icon :icon="$template['icon']" class="size-5 text-gray-500 dark:text-gray-400" />
            </span>
            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $template['label'] }}</span>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $template['description'] }}</span>
        </button>
    @endforeach
</div>
