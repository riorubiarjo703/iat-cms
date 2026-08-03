@props(['page'])

@php
    $settings = \App\Models\SiteSetting::singleton();

    // Per-page SEO wins; Site Settings is the fallback, so a page with nothing
    // filled in still gets sensible metadata rather than an empty title.
    //
    // The homepage is the exception: it is the site's front door, so it falls
    // back to the site meta title and takes no " — site name" suffix. Titling
    // it "Home — SCBD" would be worse than what it replaced.
    $isHomepage = (bool) $page->is_homepage;
    $title = $page->t('seo_title')
        ?: ($isHomepage ? ($settings->t('meta_title') ?: $settings->site_name) : $page->t('title'));
    $suffix = (! $isHomepage && $settings->site_name) ? ' — '.$settings->site_name : '';
    $description = $page->t('seo_description') ?: $settings->t('meta_description');
@endphp
<!DOCTYPE html>
<html lang="{{ $settings->default_locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title }}{{ $suffix }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif

    @if ($settings->favicon)
        <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->favicon) }}">
    @endif

    {{-- Builder pages get the animation bundle: every registered block depends
         on its hooks (data-split, data-parallax, data-stack, data-horizontal),
         and without it their content renders but never becomes visible.
         Standard pages skip it — they have no such hooks, and the bundle's
         `cursor:none` would leave a text page with no pointer. --}}
    @if ($page->usesBuilder())
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
        'cursor:none' => $page->usesBuilder(),
    ])>
        @if ($page->is_homepage)
            @include('partials.home.loader')
        @endif

        @if ($page->usesBuilder())
            <div class="scbd-cursor" data-cursor style="position:fixed;top:0;left:0;width:14px;height:14px;background:#ec3013;z-index:9999;pointer-events:none;transform:translate(-50%,-50%);"></div>
            <div class="scbd-cursor" data-cursor-ring style="position:fixed;top:0;left:0;width:44px;height:44px;border:1.5px solid rgba(32,30,29,0.45);z-index:9998;pointer-events:none;transform:translate(-50%,-50%);"></div>
        @endif

        {{ $slot }}
    </div>

    @if ($page->usesBuilder())
        {{-- Consumed by resources/js/scbd/i18n.js. Blocks publish their
             translatable leaves under per-block keys, so the existing
             switcher works unchanged. --}}
        <script type="application/json" id="scbd-i18n">@json(\App\PageBuilder\SiteTranslations::forPage($page, app(\App\PageBuilder\BlockRegistry::class)))</script>
    @endif
</body>
</html>
