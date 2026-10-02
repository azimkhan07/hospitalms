@extends('layouts.app')

@section('content')
<div id="service" class="services wow fadeIn">
    <div class="container">
       <div class="row">
           <div class="col">
            <center><h1>{{ config('app.name') }} Services</h1><hr>
            </center>
           </div>
          <div class="col-lg-8 col-md-8 col-sm-6 col-xs-12">
             <div class="inner-services">
                <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                   <div class="serv">
                      <span class="icon-service"><img src="{{ asset('images/service-icon1.png') }}" alt="#" /></span>
                      <h4>PREMIUM FACILITIES</h4>
                      <p>Lorem Ipsum is simply dummy text of the printing.</p>
                   </div>
                </div>
                <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                   <div class="serv">
                      <span class="icon-service"><img src="{{ asset('images/service-icon2.png') }}" alt="#" /></span>
                      <h4>LARGE LABORATORY</h4>
                      <p>Lorem Ipsum is simply dummy text of the printing.</p>
                   </div>
                </div>
                <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                   <div class="serv">
                      <span class="icon-service"><img src="{{ asset('images/service-icon3.png') }}" alt="#" /></span>
                      <h4>DETAILED SPECIALIST</h4>
                      <p>Lorem Ipsum is simply dummy text of the printing.</p>
                   </div>
                </div>
                <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                   <div class="serv">
                      <span class="icon-service"><img src="{{ asset('images/service-icon4.png') }}" alt="#" /></span>
                      <h4>CHILDREN CARE CENTER</h4>
                      <p>Lorem Ipsum is simply dummy text of the printing.</p>
                   </div>
                </div>
                <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                   <div class="serv">
                      <span class="icon-service"><img src="{{ asset('images/service-icon5.png') }}" alt="#" /></span>
                      <h4>FINE INFRASTRUCTURE</h4>
                      <p>Lorem Ipsum is simply dummy text of the printing.</p>
                   </div>
                </div>
                <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                   <div class="serv">
                      <span class="icon-service"><img src="{{ asset('images/service-icon6.png') }}" alt="#" /></span>
                      <h4>ANYTIME BLOOD BANK</h4>
                      <p>Lorem Ipsum is simply dummy text of the printing.</p>
                   </div>
                </div>
             </div>
          </div>
          <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
             <div class="appointment-form">
                <h3><span>+</span> Book Appointment</h3>
                <div class="form">
                   <form action="/">
                      <fieldset>
                         <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                            <div class="row">
                               <div class="form-group">
                                  <input type="text" id="name" placeholder="Your Name"  />
                               </div>
                            </div>
                         </div>
                         <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                            <div class="row">
                               <div class="form-group">
                                  <input type="email" placeholder="Email Address" id="email" />
                               </div>
                            </div>
                         </div>
                         <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 select-section">
                            <div class="row">
                               <div class="form-group">
                                  <select class="form-control">
                                     <option>Day</option>
                                     <option>Sunday</option>
                                     <option>Monday</option>
                                  </select>
                               </div>
                               <div class="form-group">
                                  <select class="form-control">
                                     <option>Time</option>
                                     <option>AM</option>
                                     <option>PM</option>
                                  </select>
                               </div>
                            </div>
                         </div>
                         <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                            <div class="row">
                               <div class="form-group">
                                  <select class="form-control">
                                     <option>Doctor Name</option>
                                     <option>Mr.XYZ</option>
                                     <option>Mr.ABC</option>
                                  </select>
                               </div>
                            </div>
                         </div>
                         <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                            <div class="row">
                               <div class="form-group">
                                  <textarea rows="4" id="textarea_message" class="form-control" placeholder="Your Message..."></textarea>
                               </div>
                            </div>
                         </div>
                         <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                            <div class="row">
                               <div class="form-group">
                                  <div class="center"><button type="submit">Submit</button></div>
                               </div>
                            </div>
                         </div>
                      </fieldset>
                   </form>
                </div>
             </div>
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
