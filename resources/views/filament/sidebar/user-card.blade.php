@php
    $user = filament()->auth()->user();
    $initials = collect(explode(' ', trim($user?->name ?? '')))
        ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp

<div class="fi-sidebar-user" x-data="{ open: false }" @click.outside="open = false">
    <button
        type="button"
        @click="open = ! open"
        class="fi-sidebar-user-btn"
        :aria-expanded="open"
        aria-label="{{ $user?->name }} account menu"
    >
        <span class="fi-sidebar-user-avatar">{{ $initials ?: '?' }}</span>

        <span class="fi-sidebar-user-identity">
            <span class="fi-sidebar-user-name">{{ $user?->name }}</span>
            <span class="fi-sidebar-user-email">{{ $user?->email }}</span>
        </span>

        <x-filament::icon icon="heroicon-o-chevron-up-down" class="fi-sidebar-user-chevron" />
    </button>

    <div x-show="open" x-cloak x-transition.opacity class="fi-sidebar-user-menu">
        <div class="fi-sidebar-user-menu-header">
            <span class="fi-sidebar-user-avatar">{{ $initials ?: '?' }}</span>
            <span class="fi-sidebar-user-identity">
                <span class="fi-sidebar-user-name">{{ $user?->name }}</span>
                <span class="fi-sidebar-user-email">{{ $user?->email }}</span>
            </span>
        </div>

        <a href="{{ \App\Filament\Pages\SiteSettingsPage::getUrl() }}" class="fi-sidebar-user-menu-item">
            <x-filament::icon icon="heroicon-o-cog-6-tooth" class="h-5 w-5" />
            <span>Settings</span>
        </a>

        @if (filament()->hasProfile())
            <a href="{{ filament()->getProfileUrl() }}" class="fi-sidebar-user-menu-item">
                <x-filament::icon icon="heroicon-o-key" class="h-5 w-5" />
                <span>Change Password</span>
            </a>
        @endif

        <form method="POST" action="{{ filament()->getLogoutUrl() }}">
            @csrf
            <button type="submit" class="fi-sidebar-user-menu-item fi-sidebar-user-menu-item-danger">
                <x-filament::icon icon="heroicon-o-arrow-right-start-on-rectangle" class="h-5 w-5" />
                <span>Sign out</span>
            </button>
        </form>
    </div>
</div>
