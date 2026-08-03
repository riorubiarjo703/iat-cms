@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';

    $eyebrow = BlockData::t($data, 'eyebrow', $locale);
    $heading = BlockData::t($data, 'heading', $locale);
    $ctaLabel = BlockData::t($data, 'cta_label', $locale);
    $emptyText = BlockData::t($data, 'empty_text', $locale);

    $posts = \AjayDhakal\FilamentStory\Models\BlogPost::query()
        ->where('status', \AjayDhakal\FilamentStory\Models\BlogPost::STATUS_PUBLISHED)
        ->orderByDesc('published_at')
        ->limit((int) BlockData::get($data, 'limit', 3) ?: 3)
        ->get();
@endphp

<section id="news" style="padding:140px 40px 120px;">
    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:32px; border-bottom:2px solid rgba(32,30,29,0.4); padding-bottom:24px; margin-bottom:0;">
        <div>
            @if ($eyebrow)
                <div style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:#ec3013; margin-bottom:16px;" data-i18n="{{ BlockData::i18nKey($blockId, 'eyebrow') }}">{{ $eyebrow }}</div>
            @endif
            <h2 data-split style="font-size:clamp(34px,4.4vw,66px); line-height:0.98; letter-spacing:-0.035em; margin:0; text-transform:uppercase;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h2>
        </div>
        @if ($ctaLabel)
            <a href="{{ route('filament-story.index') }}" class="btn btn-secondary" data-magnetic style="justify-content:flex-start; cursor:none;" data-i18n="{{ BlockData::i18nKey($blockId, 'cta_label') }}">{{ $ctaLabel }}</a>
        @endif
    </div>

    @forelse ($posts as $post)
        <a data-news href="{{ route('filament-story.show', $post->slug) }}" style="display:grid; grid-template-columns:100px 1fr 320px; gap:32px; align-items:start; padding:32px 0; border-bottom:2px solid rgba(32,30,29,0.4); text-decoration:none; color:#201e1d;">
            <div style="font-size:12px; letter-spacing:0.14em; text-transform:uppercase; color:rgba(32,30,29,0.55);">{{ $post->published_at?->format('d.m.y') }}</div>
            <h3 style="font-size:clamp(20px,2.2vw,34px); line-height:1.08; letter-spacing:-0.025em; margin:0; text-transform:uppercase;">{{ $post->title }}</h3>
            <p style="font-size:13px; line-height:1.65; margin:0; color:rgba(32,30,29,0.65);">{{ $post->excerpt }}</p>
        </a>
    @empty
        {{-- No published posts yet. The row still carries data-news so the
             hover animation hook always has an element to bind to. --}}
        <div data-news style="display:grid; grid-template-columns:100px 1fr 320px; gap:32px; align-items:start; padding:32px 0; border-bottom:2px solid rgba(32,30,29,0.4); color:#201e1d;">
            <div style="font-size:12px; letter-spacing:0.14em; text-transform:uppercase; color:rgba(32,30,29,0.55);">&nbsp;</div>
            <h3 style="font-size:clamp(20px,2.2vw,34px); line-height:1.08; letter-spacing:-0.025em; margin:0; text-transform:uppercase;" data-i18n="{{ BlockData::i18nKey($blockId, 'empty_text') }}">{{ $emptyText }}</h3>
            <p style="font-size:13px; line-height:1.65; margin:0; color:rgba(32,30,29,0.65);"></p>
        </div>
    @endforelse
</section>
