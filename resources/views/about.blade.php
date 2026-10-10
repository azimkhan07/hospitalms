@extends('layouts.app')

@section('content')
<div id="about" class="section  wow fadeIn">
    <div class="container">
      <div class="heading">
         <span class="icon-logo"><img src="{{ $site['icon'] ? storage_url($site['icon']) : asset('images/icon-logo.png') }}" alt="#"></span>
         <h2>{{ $site['about_title'] }}</h2>
      </div>
      <!-- end title -->
      <div class="row">
         <div class="col-md-6">
            <div class="message-box">
               <h4>{{ $site['about_heading'] }}</h4>
               <h2>{{ $site['about_sub_heading'] }}</h2>
               <p class="lead">{{ $site['about'] }}</p>
               <p>{{ $site['about_intro'] ?: ('Our ' . $departments->count() . ' departments handle diagnostics, pharmacy, surgery and follow-up from the same admission, so patients repeat less paperwork and doctors always see the complete history.') }}</p>
            </div>
            <!-- end messagebox -->
         </div>
         <!-- end col -->
         <div class="col-md-6">
            <div class="post-media wow fadeIn">
               @php($aboutImg = $site['about_image'] ?: $site['hero_image'])
               <img src="{{ $aboutImg ? storage_url($aboutImg) : asset('images/about_03.jpg') }}" alt="{{ $site['name'] }}" class="img-responsive">
            </div>
            <!-- end media -->
         </div>
         <!-- end col -->
      </div>

   </div>
   <!-- end container -->
</div>
@endsection
