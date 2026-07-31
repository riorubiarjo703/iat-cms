@php
    $locale = $data->settings->default_locale ?? 'en';
    $aboutHeading = $data->i18n[$locale]['abouth'] ?? '';
@endphp

<section id="about" style="padding:120px 40px; display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.15fr); gap:80px; align-items:start;">
    <div style="position:sticky; top:110px;">
        <div style="font-size:11px; letter-spacing:0.22em; text-transform:uppercase; color:#ec3013; margin-bottom:20px;">Who we are</div>
        <h2 data-fade style="font-size:clamp(34px,4.4vw,66px); line-height:0.98; letter-spacing:-0.035em; margin:0 0 24px; text-transform:uppercase;" data-i18n="abouth">{!! $aboutHeading !!}</h2>
        <p data-fade style="font-size:15px; line-height:1.7; max-width:44ch; color:rgba(32,30,29,0.75);" data-i18n="aboutp">{{ $data->content->t('about_body') }}</p>
        <a href="{{ $data->content->about_cta_url ?: '#contact' }}" class="btn btn-secondary" data-magnetic style="margin-top:20px; justify-content:flex-start; cursor:none;" data-i18n="aboutcta">{{ $data->content->t('about_cta_label') }}</a>
    </div>
    <div style="display:grid; gap:2px; background:rgba(32,30,29,0.4); border:2px solid rgba(32,30,29,0.4);">
        @if ($data->stats->isNotEmpty())
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:2px;">
                @foreach ($data->stats as $stat)
                    <div style="background:#f3f2f2; padding:36px 28px;">
                        <div data-count
                             data-to="{{ $stat->value }}"
                             @if ($stat->suffix) data-suffix="{{ $stat->suffix }}" @endif
                             @if ($stat->isPlain()) data-plain="1" @endif
                             style="font-weight:800; font-size:clamp(48px,7vw,104px); line-height:0.85; letter-spacing:-0.045em;">0</div>
                        <div style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; margin-top:12px; color:rgba(32,30,29,0.6);">{{ $stat->t('label') }}</div>
                    </div>
                @endforeach
                <div style="background:#ec3013; color:#f3f2f2; padding:36px 28px; display:flex; flex-direction:column; justify-content:space-between;">
                    <div style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; opacity:0.8;">Certified</div>
                    <div style="font-weight:800; font-size:clamp(22px,2.6vw,34px); line-height:1; letter-spacing:-0.03em; text-transform:uppercase; margin-top:40px;">ISO &amp; SMK3<br>accredited operations</div>
                </div>
            </div>
        @endif
        <div style="background:#f3f2f2; overflow:hidden;">
            @if ($data->content->about_image)
                <img data-reveal
                     class="grayscale"
                     src="{{ Storage::disk('public')->url($data->content->about_image) }}"
                     alt="SCBD towers"
                     style="width:100%; height:300px; object-fit:cover;">
            @else
                <div data-reveal style="width:100%; height:300px; background:#201e1d; opacity:0.08;"></div>
            @endif
        </div>
    </div>
</section>
