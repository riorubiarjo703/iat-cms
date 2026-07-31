<x-layouts.public :data="$data">
    @include('partials.home.loader')
    @include('partials.home.header', ['data' => $data])

    <main style="position:relative;">
        @include('partials.home.hero', ['data' => $data])
        @include('partials.home.marquee', ['data' => $data])
        @include('partials.home.about', ['data' => $data])
        @include('partials.home.district', ['data' => $data])
        @include('partials.home.facilities', ['data' => $data])
        @include('partials.home.news', ['data' => $data])
        @include('partials.home.contact', ['data' => $data])
    </main>
</x-layouts.public>
