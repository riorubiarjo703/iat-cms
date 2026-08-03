{{--
    Brand moved into the sidebar. Filament renders it in the topbar by default,
    but the reference puts the logo above the nav and confines the topbar to the
    content column.
--}}
@php($settings = \App\Models\SiteSetting::singleton())

<a href="{{ \Filament\Facades\Filament::getUrl() }}" class="fi-sidebar-brand">
    @if ($settings->logo)
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo) }}"
             alt="{{ $settings->site_name ?: config('app.name') }}"
             class="fi-sidebar-brand-logo">
    @else
        <span class="fi-sidebar-brand-name">{{ $settings->site_name ?: config('app.name') }}</span>
    @endif
</a>
