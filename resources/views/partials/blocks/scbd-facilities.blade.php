@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';

    $eyebrow = BlockData::t($data, 'eyebrow', $locale);
    $heading = BlockData::t($data, 'heading', $locale);
    $body = BlockData::t($data, 'body', $locale);

    $facilities = \App\Models\Facility::query()->active()->ordered()->get();
@endphp

{{-- The stacked-card scroll needs cards to stack; with none the section has
     nothing to show. --}}
@if ($facilities->isNotEmpty())
    <section id="facilities" class="scbd-pad" style="padding-bottom:0 !important;">
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:32px; border-bottom:2px solid rgba(32,30,29,0.4); padding-bottom:24px;">
            <div>
                @if ($eyebrow)
                    <div style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:#ec3013; margin-bottom:16px;" data-i18n="{{ BlockData::i18nKey($blockId, 'eyebrow') }}">{{ $eyebrow }}</div>
                @endif
                <h2 data-split class="scbd-h2" style="font-size:clamp(34px,4.4vw,66px); line-height:0.98; letter-spacing:-0.035em; margin:0; text-transform:uppercase;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h2>
            </div>
            <p style="font-size:14px; line-height:1.7; max-width:34ch; margin:0; color:rgba(32,30,29,0.7);" data-i18n="{{ BlockData::i18nKey($blockId, 'body') }}">{{ $body }}</p>
        </div>

        <div data-stack style="position:relative; padding:60px 0 40vh;">
            @foreach ($facilities as $facility)
                <article data-card class="scbd-card-split" style="position:sticky; top:110px; background:#f3f2f2; border:2px solid rgba(32,30,29,0.4); display:grid; grid-template-columns:1fr 1fr; gap:0; margin-bottom:56px; transform-origin:center top; will-change:transform;">
                    <div style="padding:48px;">
                        <h3 style="font-size:clamp(26px,3vw,44px); line-height:1; letter-spacing:-0.03em; text-transform:uppercase; margin:0 0 16px;">{{ $facility->t('title') }}</h3>
                        <p style="font-size:14px; line-height:1.7; color:rgba(32,30,29,0.72); margin:0;">{{ $facility->t('body') }}</p>
                    </div>
                    <div style="overflow:hidden; border-left:2px solid rgba(32,30,29,0.4);">
                        @if ($facility->image)
                            <img class="grayscale" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($facility->image) }}" alt="{{ $facility->t('title') }}" style="width:100%; height:100%; min-height:280px; object-fit:cover;">
                        @else
                            <div style="width:100%; height:100%; min-height:280px; background:#201e1d; opacity:0.08;"></div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
