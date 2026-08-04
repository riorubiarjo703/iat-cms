{{-- The footer for standalone pages: the same band the homepage uses. --}}
@php($settings = \App\Models\SiteSetting::singleton())

<footer class="scbd-pad" style="background:#ec3013; color:#f3f2f2; padding:80px 40px;">
    @include('partials.site.footer-band', ['settings' => $settings])
</footer>
