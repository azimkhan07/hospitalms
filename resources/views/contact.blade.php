@extends('layouts.app')

@section('content')
@include('partials.page-hero', [
    'title' => $site['contact_heading'] ?: 'Contact Us',
    'subtitle' => $site['tagline'],
    'crumb' => 'Contact',
])
@livewire('contactus')
@endsection
