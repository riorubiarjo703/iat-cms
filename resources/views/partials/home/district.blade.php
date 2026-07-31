@php
    $locale = $data->settings->default_locale ?? 'en';
    $districtHeading = $data->i18n[$locale]['disth'] ?? '';
    $locationLines = $data->content->contact_address
        ? e($data->content->contact_address)
        : null;
@endphp

@if ($data->places->isNotEmpty())
    <section id="district" data-horizontal style="position:relative; background:#201e1d; color:#f3f2f2; overflow:hidden;">
        <div data-horizontal-track style="display:flex; align-items:stretch; height:100vh; will-change:transform;">
            <div style="flex:0 0 46vw; min-width:420px; padding:80px 48px; box-sizing:border-box; display:flex; flex-direction:column; justify-content:space-between; border-right:2px solid rgba(243,242,242,0.25);">
                <div>
                    <div style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:#ff563c; margin-bottom:20px;">The district</div>
                    <h2 data-split style="font-size:clamp(34px,4.4vw,68px); line-height:0.95; letter-spacing:-0.035em; margin:0; text-transform:uppercase;" data-i18n="disth">{!! $districtHeading !!}</h2>
                </div>
                <p style="font-size:14px; line-height:1.7; max-width:38ch; color:rgba(243,242,242,0.7); margin:0;" data-i18n="distp">{{ $data->content->t('district_body') }}</p>
            </div>

            @foreach ($data->places as $place)
                <div style="flex:0 0 34vw; min-width:320px; border-right:2px solid rgba(243,242,242,0.25); display:flex; flex-direction:column;">
                    <div style="flex:1; overflow:hidden;">
                        @if ($place->image)
                            <img class="grayscale" src="{{ Storage::disk('public')->url($place->image) }}" alt="{{ $place->t('title') }}" style="width:100%; height:100%; object-fit:cover;">
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

            <div style="flex:0 0 40vw; min-width:360px; padding:80px 48px; box-sizing:border-box; display:flex; flex-direction:column; justify-content:center; gap:20px; background:#ec3013;">
                <div style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; opacity:0.85;">Location</div>
                @if ($locationLines)
                    <div style="font-weight:800; font-size:clamp(30px,3.6vw,56px); line-height:0.98; letter-spacing:-0.035em; text-transform:uppercase;">{!! nl2br($locationLines) !!}</div>
                @endif
                <a href="#contact" data-magnetic style="align-self:flex-start; background:#f3f2f2; color:#201e1d; text-decoration:none; font-weight:800; font-size:13px; letter-spacing:0.1em; text-transform:uppercase; padding:14px 22px; cursor:none;">Get directions</a>
            </div>
        </div>
    </section>
@endif
