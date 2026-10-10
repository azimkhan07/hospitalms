@extends('layouts.app')

@section('content')
<div id="service" class="services wow fadeIn">
    <div class="container">
       <div class="row">
           <div class="col">
            <center><h1>{{ $site['services_page_heading'] ?: (config('app.name') . ' Services') }}</h1><hr>
            </center>
           </div>
          <div class="col-lg-8 col-md-8 col-sm-12 col-xs-12">
             <div class="inner-services">
                @forelse (\App\Models\SiteContent::features() as $feature)
                    <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                       <div class="serv">
                          <span class="icon-service"><img src="{{ $feature->icon ? storage_url($feature->icon) : asset('images/service-icon' . ($loop->iteration > 6 ? 1 : $loop->iteration) . '.png') }}" alt="{{ $feature->title }}" /></span>
                          <h4>{{ $feature->title }}</h4>
                          <p>{{ $feature->text }}</p>
                       </div>
                    </div>
                @empty
                    @for ($i = 1; $i <= 6; $i++)
                        <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                           <div class="serv">
                              <span class="icon-service"><img src="{{ asset('images/service-icon' . $i . '.png') }}" alt="#" /></span>
                              <h4>24x7 Care</h4>
                              <p>Professional care from our specialist team.</p>
                           </div>
                        </div>
                    @endfor
                @endforelse
             </div>
          </div>
          <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
             @livewire('appointmentform')
          </div>
       </div>
       <!-- end row -->
      <hr class="hr1">
      <div class="row">
         @forelse ($departments->take(4) as $dept)
            <div class="col-md-3 col-sm-6 col-xs-12">
               <div class="service-widget">
                  <div class="post-media wow fadeIn">
                     <img src="{{ storage_url($dept->photo_path, 'department-placeholder.jpg') }}" alt="{{ $dept->name }}" class="img-responsive">
                  </div>
                  <h3>{{ $dept->name }}</h3>
               </div>
               <!-- end service -->
            </div>
         @empty
            @for ($i = 1; $i <= 4; $i++)
               <div class="col-md-3 col-sm-6 col-xs-12">
                  <div class="service-widget">
                     <div class="post-media wow fadeIn">
                        <img src="{{ asset('images/clinic_0' . $i . '.jpg') }}" alt="" class="img-responsive">
                     </div>
                     <h3>{{ ['Cardiology', 'Neurology', 'Orthopaedics', 'Paediatrics'][$i - 1] }}</h3>
                  </div>
                  <!-- end service -->
               </div>
            @endfor
         @endforelse
      </div>
      <!-- end row -->
    </div>
 </div>

@endsection