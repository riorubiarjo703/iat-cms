@php
    $locales = \App\Models\SiteSetting::LOCALES;
    $activeLocale = $data->settings->default_locale ?? 'en';
@endphp

<header data-header style="position:fixed; top:0; left:0; right:0; z-index:900; background:rgba(243,242,242,0.92); backdrop-filter:blur(10px); border-bottom:2px solid rgba(32,30,29,0.4);">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:32px; padding:14px 40px;">
        <a href="#top" data-magnetic style="display:flex; align-items:baseline; gap:10px; text-decoration:none; color:#201e1d;">
            @php
                $brandName = $data->settings->site_name ?: config('app.name');
            @endphp
            @if ($data->settings->logo)
                <img src="{{ Storage::disk('public')->url($data->settings->logo) }}"
                     alt="{{ $brandName }}"
                     style="height:26px; width:auto; display:block;">
            @else
                {{-- No logo uploaded: the brand must still be visible, so fall back
                     to the configured site name rather than a hardcoded string. --}}
                <span style="font-weight:800; font-size:22px; letter-spacing:-0.03em;">{{ $brandName }}</span>
            @endif
            <span style="font-size:10px; letter-spacing:0.2em; text-transform:uppercase; color:rgba(32,30,29,0.55);" data-i18n="brandsub">{{ $data->content->t('brand_sub') }}</span>
        </a>
        <nav style="display:flex; align-items:center; gap:28px;">
            @foreach ($data->menu as $item)
                <a href="{{ $item->resolveUrl() }}"
                   data-navlink
                   @if ($item->target && $item->target !== '_self') target="{{ $item->target }}" @endif
                   style="font-size:12px; letter-spacing:0.14em; text-transform:uppercase; text-decoration:none; color:#201e1d;"
                   data-i18n="nav{{ $loop->iteration }}">{{ $item->t('label') }}</a>
            @endforeach
            <div style="display:flex; align-items:center; gap:2px; border-left:1px solid rgba(32,30,29,0.3); padding-left:20px;">
                @foreach ($locales as $code => $label)
                    <button data-lang="{{ $code }}"
                            style="border:0; background:{{ $code === $activeLocale ? '#201e1d' : 'transparent' }}; color:{{ $code === $activeLocale ? '#f3f2f2' : '#201e1d' }}; font-family:inherit; font-weight:800; font-size:11px; letter-spacing:0.1em; padding:6px 9px; cursor:none;">{{ strtoupper($code) }}</button>
                @endforeach
            </div>
            @if ($data->cta)
                <a href="{{ $data->cta->resolveUrl() }}"
                   class="btn btn-primary"
                   data-magnetic
                   @if ($data->cta->target && $data->cta->target !== '_self') target="{{ $data->cta->target }}" @endif
                   style="justify-content:flex-start; cursor:none;"
                   data-i18n="cta">{{ $data->cta->t('label') }}</a>
            @endif
        </nav>
    </div>
</header>
