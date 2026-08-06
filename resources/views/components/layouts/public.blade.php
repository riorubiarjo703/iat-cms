<!DOCTYPE html>
<html lang="{{ $data->settings->default_locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $data->settings->t('meta_title') ?? ($data->settings->site_name ?? config('app.name')) }}</title>
    <meta name="description" content="{{ $data->settings->t('meta_description') }}">

    @if ($faviconUrl = \App\Support\MediaUrl::resolve($data->settings->favicon))
        <link rel="icon" href="{{ $faviconUrl }}">
    @endif

    @vite(['resources/css/scbd.css', 'resources/js/scbd/index.js'])

    {{-- Last in <head> so an operator snippet can never displace the title,
         meta description or the Vite tags above it. --}}
    <x-code-snippets position="head" />
</head>
<body>
    <x-code-snippets position="body_start" />

    {{-- Page-wide wrapper from the reference: sets the base background/text/font
         and — critically — `cursor:none`, which every element on the page relies
         on so the native pointer never shows through underneath the custom
         `.scbd-cursor` dot and ring. --}}
    <div style="position:relative; width:100%; background:#f3f2f2; color:#201e1d; font-family:'Archivo',system-ui,sans-serif; cursor:none;">
        <div class="scbd-cursor" data-cursor style="position:fixed;top:0;left:0;width:14px;height:14px;background:#ec3013;z-index:9999;pointer-events:none;mix-blend-mode:normal;transform:translate(-50%,-50%);"></div>
        <div class="scbd-cursor" data-cursor-ring style="position:fixed;top:0;left:0;width:44px;height:44px;border:1.5px solid rgba(32,30,29,0.45);z-index:9998;pointer-events:none;transform:translate(-50%,-50%);"></div>

        {{ $slot }}
    </div>

    {{-- Translation payload consumed by resources/js/scbd/i18n.js --}}
    <script type="application/json" id="scbd-i18n">@json($data->i18n)</script>

    <x-code-snippets position="body_end" />
</body>
</html>
