@php
    $settings = \App\Models\SiteSetting::singleton();
    $suffix = $settings->site_name ? ' — '.$settings->site_name : '';
@endphp

<x-layouts.page
    :title="($post->seo_title ?: $post->title).$suffix"
    :description="$post->seo_description ?: $post->excerpt"
    :animated="true"
    :show-loader="false"
    :i18n="[]">

    @include('partials.site.header')

    <main class="scbd-shade" style="min-height:50vh;">
        <article class="scbd-pad-top">
            <h1>{{ $post->title }}</h1>
            <div class="scbd-prose">{!! $post->content !!}</div>
        </article>
    </main>

    @include('partials.site.footer')
</x-layouts.page>
