@php($heroImg = $site['hero_image'] ? storage_url($site['hero_image']) : asset('images/slider-bg.png'))
<div class="hms-page-hero" style="background-image:url('{{ $heroImg }}');">
    <div class="container">
        <h1>{{ $title }}</h1>
        @if (!empty($subtitle))
            <p>{{ $subtitle }}</p>
        @endif
        <span class="hms-crumb"><a href="{{ url('/') }}">Home</a> <i class="fa fa-angle-right"></i> {{ $crumb ?? $title }}</span>
    </div>
</div>