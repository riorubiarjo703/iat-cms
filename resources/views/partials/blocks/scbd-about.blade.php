@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';

    $eyebrow = BlockData::t($data, 'eyebrow', $locale);
    $heading = BlockData::t($data, 'heading', $locale);
    $body = BlockData::t($data, 'body', $locale);
    $ctaLabel = BlockData::t($data, 'cta_label', $locale);
    $badgeLabel = BlockData::t($data, 'badge_label', $locale);
    $badgeText = BlockData::t($data, 'badge_text', $locale);
    $image = BlockData::get($data, 'image');

    // Stats are their own content model with their own admin screen, so the
    // block references them rather than embedding copies that would go stale.
    $stats = BlockData::get($data, 'show_stats', true)
        ? \App\Models\Stat::query()->ordered()->get()
        : collect();
@endphp

<section id="about" class="scbd-pad scbd-split-2" style="grid-template-columns:minmax(0,1fr) minmax(0,1.15fr); align-items:start;">
    <div style="position:sticky; top:110px;">
        @if ($eyebrow)
            <div style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:#ec3013; margin-bottom:20px;" data-i18n="{{ BlockData::i18nKey($blockId, 'eyebrow') }}">{{ $eyebrow }}</div>
        @endif
        <h2 data-fade class="scbd-h2" style="font-size:clamp(34px,4.4vw,66px); line-height:0.98; letter-spacing:-0.035em; margin:0 0 24px; text-transform:uppercase;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h2>
        <p data-fade style="font-size:15px; line-height:1.7; max-width:44ch; color:rgba(32,30,29,0.75);" data-i18n="{{ BlockData::i18nKey($blockId, 'body') }}">{{ $body }}</p>
        @if ($ctaLabel)
            <a href="{{ BlockData::get($data, 'cta_url') ?: '#contact' }}" class="btn btn-secondary" data-magnetic style="margin-top:20px; justify-content:flex-start; cursor:none;" data-i18n="{{ BlockData::i18nKey($blockId, 'cta_label') }}">{{ $ctaLabel }}</a>
        @endif
    </div>
    <div style="display:grid; gap:2px; background:rgba(32,30,29,0.4); border:2px solid rgba(32,30,29,0.4);">
        @if ($stats->isNotEmpty())
            <div class="scbd-stats-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:2px;">
                @foreach ($stats as $stat)
                    <div style="background:#f3f2f2; padding:36px 28px;">
                        <div data-count
                             data-to="{{ $stat->value }}"
                             @if ($stat->suffix) data-suffix="{{ $stat->suffix }}" @endif
                             @if ($stat->isPlain()) data-plain="1" @endif
                             style="font-weight:800; font-size:clamp(48px,7vw,104px); line-height:0.85; letter-spacing:-0.045em;">0</div>
                        <div style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; margin-top:12px; color:rgba(32,30,29,0.6);">{{ $stat->t('label') }}</div>
                    </div>
                @endforeach
                @if ($badgeLabel || $badgeText)
                    <div style="background:#ec3013; color:#f3f2f2; padding:36px 28px; display:flex; flex-direction:column; justify-content:space-between;">
                        <div style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; opacity:0.8;" data-i18n="{{ BlockData::i18nKey($blockId, 'badge_label') }}">{{ $badgeLabel }}</div>
                        <div style="font-weight:800; font-size:clamp(22px,2.6vw,34px); line-height:1; letter-spacing:-0.03em; text-transform:uppercase; margin-top:40px;" data-i18n="{{ BlockData::i18nKey($blockId, 'badge_text') }}">{!! nl2br(e($badgeText)) !!}</div>
                    </div>
                @endif
            </div>
        @endif
        <div style="background:#f3f2f2; overflow:hidden;">
            @if ($image)
                <img data-reveal class="grayscale"
                     src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image) }}"
                     alt="{{ $heading }}"
                     style="width:100%; height:300px; object-fit:cover;">
            @else
                <div data-reveal style="width:100%; height:300px; background:#201e1d; opacity:0.08;"></div>
            @endif
        </div>
    </div>
</section>
