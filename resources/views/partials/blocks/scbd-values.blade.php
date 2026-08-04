@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';

    $heading = BlockData::t($data, 'heading', $locale) ?: 'Corporate culture';
    $acronym = BlockData::t($data, 'acronym', $locale);

    $raw = BlockData::get($data, 'values', []);
    $values = $raw[$locale] ?? $raw[\App\Models\SiteSetting::FALLBACK_LOCALE] ?? [];
    $values = collect(is_array($values) ? $values : [])->filter(fn ($v) => filled($v['name'] ?? null))->values();
@endphp

@if ($values->isNotEmpty())
    {{-- The reveal starts the panel at scale 1.16, so it needs a frame to
         grow into. Without one it extended past the viewport and gave the
         whole page a horizontal scrollbar until the animation settled. --}}
    <section id="values" style="padding:0 40px 120px; overflow:hidden;">
        <div data-reveal style="background:#ec3013; color:#f3f2f2; padding:56px 48px; border:2px solid #ec3013;">
            <div style="display:flex; align-items:baseline; justify-content:space-between; gap:32px; flex-wrap:wrap; margin-bottom:40px;">
                <h2 style="font-size:clamp(26px,3vw,40px); line-height:1; letter-spacing:-0.03em; text-transform:uppercase; margin:0;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{{ $heading }}</h2>

                @if ($acronym)
                    <div style="font-weight:800; font-size:clamp(30px,5vw,72px); line-height:0.9; letter-spacing:-0.05em; opacity:0.35;" data-i18n="{{ BlockData::i18nKey($blockId, 'acronym') }}">{{ $acronym }}</div>
                @endif
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:32px;">
                @foreach ($values as $value)
                    <div data-fade style="border-left:2px solid rgba(243,242,242,0.4); padding-left:16px;">
                        <div style="font-weight:800; font-size:14px; letter-spacing:0.1em; text-transform:uppercase; margin-bottom:8px;">{{ $value['name'] }}</div>
                        @if (filled($value['description'] ?? null))
                            <p style="font-size:13px; line-height:1.65; margin:0; opacity:0.9;">{{ $value['description'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
