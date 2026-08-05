{{--
    A standalone page. The header and footer are part of the layout rather than
    rows in the page's own content, so every page gets them automatically and
    editing one changes them everywhere.
--}}
<x-layouts.page :page="$page">
    @include('partials.site.header')

    <main class="scbd-shade" style="position:relative; min-height:50vh;">
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
