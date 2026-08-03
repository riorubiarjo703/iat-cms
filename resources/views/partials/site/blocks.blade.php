{{--
    Block dispatcher. The single entry point for rendering a payload — child
    blocks must come back through here rather than reaching into a sibling's
    view directly.

    It never throws. A page may carry blocks whose type was removed in a later
    version, or whose view is missing after a bad deploy, and neither may take
    the page down. In production those degrade to nothing; in debug they say
    what is missing.
--}}
{{-- Uses the block form deliberately. The inline php directive mis-compiles
     an argument holding a namespaced class constant inside a call, emitting an
     unterminated opening tag that swallows the rest of the file.
     NB: naming that directive literally here would break this comment too —
     directives are compiled inside comments. --}}
@php
    $registry = app(\App\PageBuilder\BlockRegistry::class);
@endphp

@foreach ($blocks as $block)
    @php
        $type = $block['type'] ?? null;
        $view = $type ? $registry->resolveRenderView($type) : '';
        $blockData = is_array($block['data'] ?? null) ? $block['data'] : [];
        $blockId = (string) ($block['id'] ?? $loop->index);
    @endphp

    @if ($view !== '' && view()->exists($view))
        @include($view, ['data' => $blockData, 'blockId' => $blockId, 'children' => $block['children'] ?? null])
    @elseif (config('app.debug'))
        <div style="padding:16px 40px; background:#fff8e1; color:#7a5b00; font-size:13px;">
            Block “{{ $type ?? 'unknown' }}” has no renderer.
        </div>
    @endif
@endforeach
