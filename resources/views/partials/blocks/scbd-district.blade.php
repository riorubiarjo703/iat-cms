@php
    use App\PageBuilder\BlockData;

    $settings = \App\Models\SiteSetting::singleton();
    $locale = $settings->default_locale ?? 'en';

    $eyebrow = BlockData::t($data, 'eyebrow', $locale);
    $heading = BlockData::t($data, 'heading', $locale);
    $body = BlockData::t($data, 'body', $locale);
    $locationLabel = BlockData::t($data, 'location_label', $locale);
    $directionsLabel = BlockData::t($data, 'directions_label', $locale);

    $places = \App\Models\DistrictPlace::query()->active()->ordered()->get();
    $locationLines = $settings->contact_address ? e($settings->contact_address) : null;
@endphp

{{-- The pinned horizontal scroll needs panels to scroll through; with no
     places there is nothing to pin, so the section is omitted entirely. --}}
@if ($places->isNotEmpty())
    <section id="district" data-horizontal class="scbd-district" style="position:relative; background:#201e1d; color:#f3f2f2; overflow:hidden;">
        <div data-horizontal-track class="scbd-district-track" style="display:flex; align-items:stretch; height:100vh; will-change:transform;">
            <div class="scbd-district-panel" style="flex:0 0 46vw; min-width:420px; padding:80px 48px; box-sizing:border-box; display:flex; flex-direction:column; justify-content:space-between; border-right:2px solid rgba(243,242,242,0.25);">
                <div>
                    @if ($eyebrow)
                        <div style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:#ff563c; margin-bottom:20px;" data-i18n="{{ BlockData::i18nKey($blockId, 'eyebrow') }}">{{ $eyebrow }}</div>
                    @endif
                    <h2 data-split class="scbd-h2" style="font-size:clamp(34px,4.4vw,66px); line-height:0.95; letter-spacing:-0.035em; margin:0; text-transform:uppercase;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h2>
                </div>
                <p style="font-size:14px; line-height:1.7; max-width:38ch; color:rgba(243,242,242,0.7); margin:0;" data-i18n="{{ BlockData::i18nKey($blockId, 'body') }}">{{ $body }}</p>
            </div>

            @foreach ($places as $place)
                <div style="flex:0 0 34vw; min-width:320px; border-right:2px solid rgba(243,242,242,0.25); display:flex; flex-direction:column;">
                    <div style="flex:1; overflow:hidden;">
                        @if ($place->image)
                            <img class="grayscale" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($place->image) }}" alt="{{ $place->t('title') }}" style="width:100%; height:100%; object-fit:cover;">
                        @else
                            <div style="width:100%; height:100%; background:#f3f2f2; opacity:0.08;"></div>
                        @endif
                    </div>
                    <div style="padding:24px 32px; border-top:2px solid rgba(243,242,242,0.25);">
                        <div style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:rgba(243,242,242,0.55);">{{ $place->t('caption') }}</div>
                        <div style="font-weight:800; font-size:26px; letter-spacing:-0.02em; text-transform:uppercase; margin-top:6px;">{{ $place->t('title') }}</div>
                    </div>
                </div>
            @endforeach

            <div class="scbd-district-panel" style="flex:0 0 40vw; min-width:360px; padding:80px 48px; box-sizing:border-box; display:flex; flex-direction:column; justify-content:center; gap:20px; background:#ec3013;">
                @if ($locationLabel)
                    <div style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; opacity:0.85;" data-i18n="{{ BlockData::i18nKey($blockId, 'location_label') }}">{{ $locationLabel }}</div>
                @endif
                @if ($locationLines)
                    <div style="font-weight:800; font-size:clamp(30px,3.6vw,56px); line-height:0.98; letter-spacing:-0.035em; text-transform:uppercase;">{!! nl2br($locationLines) !!}</div>
                @endif
                @if ($directionsLabel)
                    <a href="{{ BlockData::get($data, 'directions_url') ?: '#contact' }}" data-magnetic style="align-self:flex-start; background:#f3f2f2; color:#201e1d; text-decoration:none; font-weight:800; font-size:13px; letter-spacing:0.1em; text-transform:uppercase; padding:14px 22px; cursor:none;" data-i18n="{{ BlockData::i18nKey($blockId, 'directions_label') }}">{{ $directionsLabel }}</a>
                @endif
            </div>
        </div>
    </section>
@endif
