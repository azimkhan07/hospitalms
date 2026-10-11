@extends('layouts.app')

@section('content')
@include('partials.page-hero', [
    'title' => $site['contact_heading'] ?: 'Contact Us',
    'subtitle' => $site['tagline'],
    'crumb' => 'Contact',
])
@livewire('contactus')

<div class="hms-inner" style="background:#f8fbfe;">
    <div class="container">
        <div class="heading">
            <h2>{{ $site['contact_card3_title'] ?: 'Working Hours' }}</h2>
        </div>
        <div class="row">
            <div class="col-md-8 col-md-offset-2 col-sm-12 col-xs-12">
                @include('partials.opening-hours')
            </div>
        </div>
    </div>
</div>
@endsection
