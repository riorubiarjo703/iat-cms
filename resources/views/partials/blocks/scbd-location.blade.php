@php
    use App\PageBuilder\BlockData;

    $settings = \App\Models\SiteSetting::singleton();
    $locale = $settings->default_locale ?? 'en';

    $eyebrow = BlockData::t($data, 'eyebrow', $locale);
    $heading = BlockData::t($data, 'heading', $locale);

    $addressHeading = BlockData::t($data, 'address_heading', $locale);
    $contactHeading = BlockData::t($data, 'contact_heading', $locale);
    $accessHeading = BlockData::t($data, 'access_heading', $locale);

    // Site settings are the source of truth for the company's address and
    // phone number; the block only overrides them where an editor has typed
    // something specific to this page.
    $address = BlockData::t($data, 'address', $locale) ?: $settings->contact_address;
    $contact = BlockData::t($data, 'contact', $locale) ?: $settings->contact_phone;

    $access = collect(BlockData::get($data, 'access', []))
        ->filter(fn ($row) => is_array($row) && filled($row['text'] ?? null))
        ->values();

    $facts = collect(BlockData::get($data, 'facts', []))
        ->filter(fn ($row) => is_array($row) && filled($row['value'] ?? null))
        ->values();

    $mapUrl = BlockData::get($data, 'map_embed_url');
@endphp

<section id="location" class="scbd-pad scbd-location" style="background:#201e1d; color:#f3f2f2;">
    <div style="border-bottom:2px solid rgba(243,242,242,0.25); padding-bottom:24px; margin-bottom:80px;">
        @if ($eyebrow)
            {{-- The brand red is too dark to read on the near-black ground, so
                 the section uses the lighter tint the design specifies. --}}
            <div style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:#ff563c; margin-bottom:16px;" data-i18n="{{ BlockData::i18nKey($blockId, 'eyebrow') }}">{{ $eyebrow }}</div>
        @endif
        <h2 data-split class="scbd-h2" style="font-size:clamp(34px,4.4vw,66px); line-height:0.98; letter-spacing:-0.035em; margin:0; text-transform:uppercase;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h2>
    </div>

    <div class="scbd-location-grid">
        <div data-fade>
            @if ($address)
                @if ($addressHeading)
                    <h3 class="scbd-location-title" data-i18n="{{ BlockData::i18nKey($blockId, 'address_heading') }}">{{ $addressHeading }}</h3>
                @endif
                <p class="scbd-location-text">{!! nl2br(e($address)) !!}</p>
            @endif

            @if ($contact)
                @if ($contactHeading)
                    <h3 class="scbd-location-title" style="margin-top:32px;" data-i18n="{{ BlockData::i18nKey($blockId, 'contact_heading') }}">{{ $contactHeading }}</h3>
                @endif
                <p class="scbd-location-text">{!! nl2br(e($contact)) !!}</p>
            @endif

            @if ($access->isNotEmpty())
                <div style="margin-top:48px; padding-top:32px; border-top:2px solid rgba(243,242,242,0.25);">
                    @if ($accessHeading)
                        <h3 class="scbd-location-title" data-i18n="{{ BlockData::i18nKey($blockId, 'access_heading') }}">{{ $accessHeading }}</h3>
                    @endif
                    <ul class="scbd-location-list">
                        @foreach ($access as $row)
                            <li>
                                @if (filled($row['label'] ?? null))
                                    <strong>{{ $row['label'] }}</strong>
                                @endif
                                {{ $row['text'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @if ($mapUrl || $facts->isNotEmpty())
            <div data-fade class="scbd-location-map">
                @if ($mapUrl)
                    {{-- Loaded lazily: an interior page should not pay for a
                         third-party frame before the map is anywhere near the
                         viewport. --}}
                    <iframe src="{{ $mapUrl }}"
                            title="{{ $heading ?: 'Map' }}"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen
                            style="width:100%; height:380px; border:0; display:block; margin-bottom:20px; filter:grayscale(1) contrast(1.05);"></iframe>
                @endif

                @if ($facts->isNotEmpty())
                    <div class="scbd-location-facts">
                        @foreach ($facts as $fact)
                            <div class="scbd-location-fact">
                                <div class="scbd-location-fact-label">{{ $fact['label'] ?? '' }}</div>
                                <div class="scbd-location-fact-value">{{ $fact['value'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
