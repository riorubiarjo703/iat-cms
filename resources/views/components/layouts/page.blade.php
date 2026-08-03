@props(['page'])

@php
    $settings = \App\Models\SiteSetting::singleton();

    // Per-page SEO wins; Site Settings is the fallback, so a page with nothing
    // filled in still gets sensible metadata rather than an empty title.
    $title = $page->t('seo_title') ?: $page->t('title');
    $description = $page->t('seo_description') ?: $settings->t('meta_description');
@endphp
<!DOCTYPE html>
<html lang="{{ $settings->default_locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title }}{{ $settings->site_name ? ' — '.$settings->site_name : '' }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif

    @if ($settings->favicon)
        <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->favicon) }}">
    @endif

    {{-- Pages do not load the homepage's GSAP/Lenis bundle. That bundle drives
         the homepage's pinned scroll and custom cursor, none of which a content
         page needs, and its `cursor:none` would leave a page with no pointer. --}}
    @vite(['resources/css/scbd.css'])
</head>
<body>
    <div style="position:relative; width:100%; background:#f3f2f2; color:#201e1d; font-family:'Archivo',system-ui,sans-serif;">
        {{ $slot }}
    </div>
</body>
</html>
