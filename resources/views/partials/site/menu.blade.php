{{--
    A menu rendered anywhere on the site, behind @menu('slug') and
    @menuLocation('header').

    Deliberately plain: the header has its own markup in nav-item.blade.php,
    carrying the data-i18n keys and dropdown classes its script and stylesheet
    expect. This is the neutral list for everywhere else, so a menu dropped
    into a page does not silently inherit header behaviour.

    Recurses through itself. $items is already filtered to what the site shows
    — children are filtered here, because only the root list arrives that way.
--}}
@php($depth = $depth ?? 0)

@if ($items->isNotEmpty())
    <ul @class(['scbd-menu', 'scbd-menu-sub' => $depth > 0])>
        @foreach ($items as $item)
            @php($children = $item->loadedChildren()->filter(fn ($child) => $child->isVisible())->values())
            <li class="scbd-menu-item">
                <a href="{{ $item->resolveUrl() }}"
                   class="scbd-menu-link"
                   @if ($item->target && $item->target !== '_self') target="{{ $item->target }}" rel="noopener" @endif
                >{{ $item->t('label') }}</a>

                @include('partials.site.menu', ['items' => $children, 'depth' => $depth + 1])
            </li>
        @endforeach
    </ul>
@endif
