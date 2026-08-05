@php
    use App\PageBuilder\BlockData;
    use AjayDhakal\FilamentStory\Models\BlogPost;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';

    $eyebrow = BlockData::t($data, 'eyebrow', $locale);
    $heading = BlockData::t($data, 'heading', $locale);
    $emptyText = BlockData::t($data, 'empty_text', $locale);
    $sidebarHeading = BlockData::t($data, 'sidebar_heading', $locale);
    $showFilters = (bool) BlockData::get($data, 'show_filters', true);
    $sidebarLimit = max(1, (int) BlockData::get($data, 'sidebar_limit', 5));

    // Published means published now: a post dated in the future is scheduled,
    // not live, and must not appear here any more than it is reachable by URL.
    $posts = BlogPost::query()
        ->with('category')
        ->where('status', BlogPost::STATUS_PUBLISHED)
        ->where('published_at', '<=', now())
        ->orderByDesc('published_at')
        ->get();

    // Only categories that actually have a published post. A chip that filters
    // to nothing is a dead end the reader cannot tell from a broken one.
    $categories = $posts->pluck('category')->filter()->unique('id')->sortBy('name')->values();

    $sidebarPosts = $posts->take($sidebarLimit);
@endphp

<section id="news" class="scbd-pad-top scbd-news-index" data-news-filter>

    <div class="scbd-news-index-head">
        <div>
            @if ($eyebrow)
                <div class="scbd-news-index-eyebrow" data-i18n="{{ BlockData::i18nKey($blockId, 'eyebrow') }}">{{ $eyebrow }}</div>
            @endif

            <h1 data-split class="scbd-h2 scbd-news-index-heading" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h1>
        </div>

        @if ($showFilters && $categories->isNotEmpty())
            {{-- Buttons, not links: filtering happens in place and changes
                 nothing about the document's address. --}}
            <div class="scbd-news-chips" role="group" aria-label="Filter by category">
                <button type="button" class="scbd-news-chip" data-news-filter-chip="" data-magnetic aria-pressed="true">All</button>

                @foreach ($categories as $category)
                    <button type="button" class="scbd-news-chip" data-news-filter-chip="{{ $category->slug }}" data-magnetic aria-pressed="false">{{ $category->name }}</button>
                @endforeach
            </div>
        @endif
    </div>

    <div class="scbd-news-index-body">
        <div class="scbd-news-index-grid" data-news-grid>
            @forelse ($posts as $post)
                @include('partials.site.news-card', ['post' => $post, 'size' => 'grid'])
            @empty
                <p class="scbd-news-index-empty" data-i18n="{{ BlockData::i18nKey($blockId, 'empty_text') }}">{{ $emptyText }}</p>
            @endforelse
        </div>

        {{-- No posts, no sidebar: a heading over an empty column reads as a
             rendering fault rather than an empty archive. --}}
        @if ($sidebarPosts->isNotEmpty())
            <aside class="scbd-news-index-side">
                @if ($sidebarHeading)
                    <h2 class="scbd-news-index-side-heading" data-i18n="{{ BlockData::i18nKey($blockId, 'sidebar_heading') }}">{{ $sidebarHeading }}</h2>
                @endif

                @foreach ($sidebarPosts as $post)
                    @include('partials.site.news-card', ['post' => $post, 'size' => 'compact'])
                @endforeach
            </aside>
        @endif
    </div>
</section>
