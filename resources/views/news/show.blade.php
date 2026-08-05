@php
    $settings = \App\Models\SiteSetting::singleton();
    $suffix = $settings->site_name ? ' — '.$settings->site_name : '';

    $hero = \App\Support\MediaUrl::resolve($post->featured_image);
    $canonical = route('news.show', $post->slug);
    $shareUrl = urlencode($canonical);
    $shareText = urlencode($post->title);
@endphp

<x-layouts.page
    :title="($post->seo_title ?: $post->title).$suffix"
    :description="$post->seo_description ?: $post->excerpt"
    :animated="true"
    :show-loader="false"
    :i18n="[]">

    @include('partials.site.header')

    <main class="scbd-shade" style="min-height:50vh;">
        <article class="scbd-pad-top scbd-news-detail">

            <nav class="scbd-news-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('page', 'news') }}">News</a>
                <span aria-hidden="true">/</span>
                <span>{{ $post->title }}</span>
            </nav>

            <h1 data-split class="scbd-h2 scbd-news-detail-title">{{ $post->title }}</h1>

            <div class="scbd-news-detail-meta">
                <div class="scbd-news-detail-facts">
                    <span>{{ $post->published_at?->format('d.m.y') }}</span>
                    @if ($post->category)
                        <span class="scbd-news-detail-category">{{ $post->category->name }}</span>
                    @endif
                </div>

                <div class="scbd-news-share">
                    <span class="scbd-news-share-label">Share this article</span>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $shareUrl }}"
                       target="_blank" rel="noopener noreferrer" data-magnetic aria-label="Share on LinkedIn">LI</a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}"
                       target="_blank" rel="noopener noreferrer" data-magnetic aria-label="Share on Facebook">FB</a>
                    <a href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&text={{ $shareText }}"
                       target="_blank" rel="noopener noreferrer" data-magnetic aria-label="Share on X">X</a>
                </div>
            </div>

            @if ($hero)
                <figure class="scbd-news-hero">
                    <img data-reveal src="{{ $hero }}" alt="{{ $post->title }}" class="grayscale">
                </figure>
            @endif

            {{-- Stored as sanitised HTML by the post editor. --}}
            <div class="scbd-prose scbd-news-body">{!! $post->content !!}</div>

            @if ($previous || $next)
                <nav class="scbd-news-nav" aria-label="More posts">
                    @if ($previous)
                        <a class="scbd-news-nav-prev" href="{{ route('news.show', $previous->slug) }}">
                            <span class="scbd-news-nav-label">← Prev</span>
                            <span class="scbd-news-nav-title">{{ $previous->title }}</span>
                        </a>
                    @endif

                    @if ($next)
                        <a class="scbd-news-nav-next" href="{{ route('news.show', $next->slug) }}">
                            <span class="scbd-news-nav-label">Next →</span>
                            <span class="scbd-news-nav-title">{{ $next->title }}</span>
                        </a>
                    @endif
                </nav>
            @endif
        </article>

        {{-- A heading over an empty row reads as a rendering fault, so the
             whole section goes when there is nothing to put in it. --}}
        @if ($latest->isNotEmpty())
            <section class="scbd-pad scbd-news-latest">
                <h2 class="scbd-news-latest-heading">LATEST NEWS</h2>

                <div class="scbd-news-latest-grid">
                    @foreach ($latest as $item)
                        @include('partials.site.news-card', ['post' => $item, 'size' => 'grid'])
                    @endforeach
                </div>
            </section>
        @endif
    </main>

    @include('partials.site.footer')
</x-layouts.page>
