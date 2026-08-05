@php
    /**
     * One card, two sizes. The column count is the caller's grid, not the
     * card's business — `grid` is fluid and serves both the index's 2-up and
     * the detail page's 4-up row.
     */
    $size = $size ?? 'grid';
    $image = \App\Support\MediaUrl::resolve($post->featured_image);
    $category = $post->category;
@endphp

<a class="scbd-news-card scbd-news-card-{{ $size }}"
   href="{{ route('news.show', $post->slug) }}"
   data-news-category="{{ $category?->slug }}">

    {{-- No image, no element. An empty src resolves against the current page
         and makes the browser request it a second time. --}}
    @if ($image)
        <span class="scbd-news-card-thumb">
            <img src="{{ $image }}"
                 alt="{{ $post->title }}"
                 loading="lazy"
                 class="grayscale">
        </span>
    @endif

    <span class="scbd-news-card-body">
        <span class="scbd-news-card-title">{{ $post->title }}</span>

        <span class="scbd-news-card-meta">
            <span class="scbd-news-card-date">{{ $post->published_at?->format('d.m.y') }}</span>

            @if ($size === 'grid' && $category)
                <span class="scbd-news-card-category">{{ $category->name }}</span>
            @endif
        </span>
    </span>
</a>
