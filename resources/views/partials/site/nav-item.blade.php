{{--
    One navigation entry, recursing into its dropdown.

    $node is {item, key, children} from MenuRenderer::withKeys(). The key is
    derived there rather than here so the markup and the translation payload
    cannot drift.
--}}
@php
    $item = $node['item'];
    $children = $node['children'];
    $hasChildren = $children !== [];
@endphp

<li @class(['scbd-nav-item', 'scbd-nav-has-children' => $hasChildren])>
    <a href="{{ $item->resolveUrl() }}"
       data-navlink
       @if ($item->target && $item->target !== '_self') target="{{ $item->target }}" rel="noopener" @endif
       @if ($hasChildren) aria-haspopup="true" aria-expanded="false" @endif
       class="scbd-nav-link {{ $depth > 0 ? 'scbd-nav-link-sub' : '' }}"
       data-i18n="{{ $node['key'] }}">{{ $item->t('label') }}</a>

    @if ($hasChildren)
        {{-- A submenu opens sideways rather than downwards, so a third level
             does not run off the bottom of a fixed header. --}}
        <ul @class(['scbd-nav-menu', 'scbd-nav-menu-sub' => $depth > 0])>
            @foreach ($children as $child)
                @include('partials.site.nav-item', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @endif
</li>
