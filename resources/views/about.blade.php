@extends('layouts.app')

@section('content')
<div id="about" class="section  wow fadeIn">
    <div class="container">
      <div class="heading">
         <span class="icon-logo"><img src="{{ asset('images/icon-logo.png') }}" alt="#"></span>
         <h2>Multi-Speciality Care</h2>
      </div>
      <!-- end title -->
      <div class="row">
         <div class="col-md-6">
            <div class="message-box">
               <h4>What We Do</h4>
               <h2>Hospital Services</h2>
               <p class="lead">{{ $site['about'] ?? 'Multi-speciality care under one roof, with departments sharing a single patient record.' }}</p>
               <p>Our {{ $departments->count() }} departments handle diagnostics, pharmacy, surgery and follow-up from the same admission, so patients repeat less paperwork and doctors always see the complete history.</p>
            </div>
            <!-- end messagebox -->
         </div>
         <!-- end col -->
         <div class="col-md-6">
            <div class="post-media wow fadeIn">
               <img src="{{ asset('images/about_03.jpg') }}" alt="" class="img-responsive">
               <a href="http://www.youtube.com/watch?v=nrJtHemSPW4" data-rel="prettyPhoto[gal]" class="playbutton"><i class="flaticon-play-button"></i></a>
            </div>
            <!-- end media -->
         </div>
         <!-- end col -->
      </div>

   </div>
   <!-- end container -->
</div>
@endsection
