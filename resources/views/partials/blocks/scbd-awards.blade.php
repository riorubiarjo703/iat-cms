@php
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';
    $heading = BlockData::t($data, 'heading', $locale);

    $items = collect(BlockData::get($data, 'items', []))
        ->filter(fn ($i) => is_array($i) && filled($i['title'] ?? null))
        ->values();
@endphp

@if ($items->isNotEmpty())
    <section id="awards" class="scbd-pad">
        @if ($heading)
            <h2 data-split class="scbd-h2" style="font-size:clamp(30px,4.6vw,72px); line-height:0.98; letter-spacing:-0.035em; text-transform:uppercase; margin:0 0 64px; max-width:16ch;" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h2>
        @endif

        <div data-awards-timeline class="scbd-awards-grid" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:32px;">
            @foreach ($items as $item)
                <article data-award style="border:2px solid rgba(32,30,29,0.35); background:#f3f2f2; display:flex; flex-direction:column;">
                    {{-- Certificates are portrait scans, so they are contained
                         rather than cropped: cover would cut the seal off. --}}
                    <div style="background:#e8e7e6; aspect-ratio:3/4; overflow:hidden;">
                        @if (filled($item['image'] ?? null))
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item['image']) }}"
                                 alt="{{ $item['title'] }}"
                                 loading="lazy"
                                 style="width:100%; height:100%; object-fit:contain; display:block;">
                        @endif
                    </div>

                    <div style="padding:20px; border-top:2px solid rgba(32,30,29,0.35);">
                        @if (filled($item['year'] ?? null))
                            <div style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:#ec3013; margin-bottom:8px;">{{ $item['year'] }}</div>
                        @endif
                        <div style="font-size:15px; line-height:1.35; font-weight:800; letter-spacing:-0.01em;">{{ $item['title'] }}</div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
