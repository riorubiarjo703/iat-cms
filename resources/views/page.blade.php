{{--
    A standalone page. The header and footer are part of the layout rather than
    rows in the page's own content, so every page gets them automatically and
    editing one changes them everywhere.
--}}
@php
    $settings = \App\Models\SiteSetting::singleton();

    // Per-page SEO wins; Site Settings is the fallback, so a page with nothing
    // filled in still gets sensible metadata rather than an empty title.
    //
    // The homepage is the exception: it is the site's front door, so it falls
    // back to the site meta title and takes no " — site name" suffix. Titling
    // it "Home — SCBD" would be worse than what it replaced.
    $isHomepage = (bool) $page->is_homepage;
    $pageTitle = $page->t('seo_title')
        ?: ($isHomepage ? ($settings->t('meta_title') ?: $settings->site_name) : $page->t('title'));
    $suffix = (! $isHomepage && $settings->site_name) ? ' — '.$settings->site_name : '';
@endphp

<x-layouts.page
    :title="$pageTitle.$suffix"
    :description="$page->t('seo_description') ?: $settings->t('meta_description')"
    :animated="$page->usesBuilder()"
    :show-loader="$isHomepage"
    :i18n="$page->usesBuilder()
        ? \App\PageBuilder\SiteTranslations::forPage($page, app(\App\PageBuilder\BlockRegistry::class))
        : []">
    @include('partials.site.header')

    <main class="scbd-shade" style="min-height:50vh;">
        @if ($page->usesBuilder())
            @include('partials.site.blocks', ['blocks' => $page->blocks()])
        @else
            <article style="max-width:820px; margin:0 auto; padding:80px 40px;">
                <h1 style="font-size:clamp(36px,6vw,72px); line-height:0.95; letter-spacing:-0.03em; margin:0 0 32px; text-transform:uppercase;">{{ $page->t('title') }}</h1>

                {{-- Stored as sanitised HTML from the editor. --}}
                <div class="scbd-prose">{!! $page->t('content') !!}</div>
            </article>
        @endif
    </main>

    @include('partials.site.footer')
</x-layouts.page>
