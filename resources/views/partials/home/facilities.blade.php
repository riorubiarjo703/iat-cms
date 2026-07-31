@php
    $locale = $data->settings->default_locale ?? 'en';
    $facilitiesHeading = $data->i18n[$locale]['fach'] ?? '';
@endphp

@if ($data->facilities->isNotEmpty())
    <section id="facilities" style="padding:120px 40px 0;">
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:32px; border-bottom:2px solid rgba(32,30,29,0.4); padding-bottom:24px;">
            <div>
                <div style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:#ec3013; margin-bottom:16px;">District facilities</div>
                <h2 data-split style="font-size:clamp(34px,4.4vw,66px); line-height:0.98; letter-spacing:-0.035em; margin:0; text-transform:uppercase;" data-i18n="fach">{!! $facilitiesHeading !!}</h2>
            </div>
            <p style="font-size:14px; line-height:1.7; max-width:34ch; margin:0; color:rgba(32,30,29,0.7);" data-i18n="facp">{{ $data->content->t('facilities_body') }}</p>
        </div>

        <div data-stack style="position:relative; padding:60px 0 40vh;">
            @foreach ($data->facilities as $facility)
                <article data-card style="position:sticky; top:110px; background:#f3f2f2; border:2px solid rgba(32,30,29,0.4); display:grid; grid-template-columns:1fr 1fr; gap:0; margin-bottom:56px; transform-origin:center top; will-change:transform;">
                    <div style="padding:48px;">
                        <h3 style="font-size:clamp(26px,3vw,44px); line-height:1; letter-spacing:-0.03em; text-transform:uppercase; margin:0 0 16px;">{{ $facility->t('title') }}</h3>
                        <p style="font-size:14px; line-height:1.7; color:rgba(32,30,29,0.72); margin:0;">{{ $facility->t('body') }}</p>
                    </div>
                    <div style="overflow:hidden; border-left:2px solid rgba(32,30,29,0.4);">
                        @if ($facility->image)
                            <img class="grayscale" src="{{ Storage::disk('public')->url($facility->image) }}" alt="{{ $facility->t('title') }}" style="width:100%; height:100%; min-height:280px; object-fit:cover;">
                        @else
                            <div style="width:100%; height:100%; min-height:280px; background:#201e1d; opacity:0.08;"></div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
