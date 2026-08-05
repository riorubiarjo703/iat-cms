{{-- The footer for standalone pages: the same band the homepage uses. --}}
@php($settings = \App\Models\SiteSetting::singleton())

{{-- The inner wrapper exists so Task 3 can scale the footer's contents without
     transforming the sticky element itself — a transform on the <footer> would
     give its own descendants a transformed containing block. --}}
<footer class="scbd-pad scbd-reveal-footer" style="background:#ec3013; color:#f3f2f2; padding:80px 40px;">
    <div class="scbd-reveal-footer-inner">
        @include('partials.site.footer-band', ['settings' => $settings])
    </div>
</footer>
