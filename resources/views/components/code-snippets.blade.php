@props(['position'])

{{--
    Injection point for operator-supplied markup.

    `code` is emitted unescaped on purpose — this renders scripts, styles and
    meta tags entered in the admin, and escaping it would turn every snippet
    into visible text on the page. The trust boundary is panel access, which
    has no self-registration: accounts exist only when an administrator makes
    one. Do not "fix" this to {{ '{{ }}' }}.
--}}
@foreach (app(\App\Support\SnippetRenderer::class)->for(\App\Enums\SnippetPosition::from($position)) as $snippet)
{!! $snippet->code !!}
@endforeach
