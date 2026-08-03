{{--
    Block dispatcher. The registry and the block set arrive in the next slice;
    until then a builder page renders nothing rather than erroring, which is the
    same contract the finished renderer will have: never throw on a payload.
--}}
@foreach ($blocks as $block)
    @php($type = $block['type'] ?? null)
    @if ($type)
        {{-- Unknown types degrade silently in production. --}}
        @if (config('app.debug'))
            <div style="padding:16px 40px; background:#fff8e1; color:#7a5b00; font-size:13px;">
                Block “{{ $type }}” has no renderer yet.
            </div>
        @endif
    @endif
@endforeach
