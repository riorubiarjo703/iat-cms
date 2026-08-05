@props([
    'title',
    'description' => null,
    'animated' => false,
    'showLoader' => false,
    'i18n' => [],
])

@php
    $settings = \App\Models\SiteSetting::singleton();
@endphp
<!DOCTYPE html>
<html lang="{{ $settings->default_locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Rendered verbatim. Deciding what a title should be — fallbacks, the
         site-name suffix, whether this is the front door — belongs to whoever
         knows what is being rendered. A layout that branched on page identity
         could not serve anything that is not a Page row. --}}
    <title>{{ $title }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif

    @if ($faviconUrl = \App\Support\MediaUrl::resolve($settings->favicon))
        <link rel="icon" href="{{ $faviconUrl }}">
    @endif

    {{-- Animated pages get the bundle: every block and the news pages depend on
         its hooks (data-split, data-parallax, data-reveal, data-news-filter),
         and without it their content renders but never becomes visible.
         Standard pages skip it — they have no such hooks, and the bundle's
         `cursor:none` would leave a text page with no pointer. --}}
    @if ($animated)
        @vite(['resources/css/scbd.css', 'resources/js/scbd/index.js'])
    @else
        @vite(['resources/css/scbd.css'])
    @endif
</head>
<body>
    <div @style([
        'position:relative; width:100%; background:#f3f2f2; color:#201e1d;',
        "font-family:'Archivo',system-ui,sans-serif;",
        // The custom cursor replaces the native one, so it is only hidden where
        // that cursor actually exists.
        'cursor:none' => $animated,
    ])>
        @if ($showLoader)
            @include('partials.site.loader')
        @endif

        @if ($animated)
            <div class="scbd-cursor" data-cursor style="position:fixed;top:0;left:0;width:14px;height:14px;background:#ec3013;z-index:9999;pointer-events:none;transform:translate(-50%,-50%);"></div>
            <div class="scbd-cursor" data-cursor-ring style="position:fixed;top:0;left:0;width:44px;height:44px;border:1.5px solid rgba(32,30,29,0.45);z-index:9998;pointer-events:none;transform:translate(-50%,-50%);"></div>
        @endif

        {{ $slot }}
    </div>

    @if ($animated && $i18n !== [])
        {{-- Consumed by resources/js/scbd/i18n.js. --}}
        <script type="application/json" id="scbd-i18n">@json($i18n)</script>
    @endif
</body>
</html>
