@extends('layouts.app')
@section('content')
    <div class="hms-landing">
        <div id="home" class="hms-hero wow fadeIn"
            style="background-image:url('{{ $site['hero_image'] ? storage_url($site['hero_image']) : asset('images/slider-bg.png') }}');">
            <div class="container">
                <div class="hms-hero-inner">
                    <span class="hms-hero-badge"><i class="fa fa-heartbeat"></i> {{ $site['mode_label'] }}</span>
                    <h1>{{ $site['hero_title'] ?: ('Welcome to ' . $site['name']) }}</h1>
                    <p>{{ $site['hero_subtitle'] ?: ($site['tagline'] . ' — compassionate care, advanced medicine.') }}</p>
                    <div class="hms-hero-actions">
                        <a href="#service" data-scroll class="hms-btn hms-btn-primary">Book Appointment</a>
                        <a href="{{ url('/docters') }}" class="hms-btn hms-btn-ghost">Our Doctors</a>
                    </div>
                </div>
            </div>
        </div>

        <div id="time-table" class="hms-facts">
            <div class="container">
                <div class="row">
                    <div class="col-lg-4 col-md-4 col-sm-12">
                        <div class="hms-fact">
                            <span class="hms-fact-icon"><i class="fa fa-ambulance"></i></span>
                            <div>
                                <h4>{{ $site['emergency_title'] ?: 'Emergency 24x7' }}</h4>
                                <p>{{ $site['emergency_text'] ?: 'Round-the-clock emergency care with on-call consultants.' }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-4 col-sm-12">
                        <div class="hms-fact">
                            <span class="hms-fact-icon"><i class="fa fa-clock-o"></i></span>
                            <div>
                                <h4>Working Hours</h4>
                                <p>{{ $site['working_hours'] }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-4 col-sm-12">
                        <div class="hms-fact">
                            <span class="hms-fact-icon"><i class="fa fa-hospital-o"></i></span>
                            <div>
                                <h4>Departments</h4>
                                <p>{{ $departments->count() ? $departments->take(3)->pluck('name')->join(', ') : 'General Medicine, Diagnostics, Pharmacy' }}{{ $departments->count() > 3 ? ' +' . ($departments->count() - 3) . ' more' : '' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="about" class="hms-section">
            <div class="container">
                <div class="heading">
                    <h2>{{ $site['about_title'] ?: ('About ' . $site['name']) }}</h2>
                </div>
                <div class="row hms-about-row">
                    <div class="col-md-6">
                        <div class="message-box">
                            <h4>{{ $site['mode_label'] }}</h4>
                            <p class="lead">{{ $site['about'] }}</p>
                            <a href="#service" data-scroll class="hms-btn hms-btn-primary">Our Services</a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="post-media wow fadeIn">
                            <img src="{{ $site['hero_image'] ? storage_url($site['hero_image']) : asset('images/about_03.jpg') }}"
                                alt="{{ $site['name'] }}" class="img-responsive" style="border-radius:8px">
                        </div>
                    </div>
                </div>

                @if ($departments->count())
                    <div class="row hms-dept-row">
                        @foreach ($departments->take(4) as $dept)
                            <div class="col-md-3 col-sm-6 col-xs-12">
                                <div class="service-widget">
                                    <div class="post-media wow fadeIn">
                                        <img src="{{ storage_url($dept->photo_path, 'department-placeholder.jpg') }}"
                                            alt="{{ $dept->name }}" class="img-responsive">
                                    </div>
                                    <h3>{{ $dept->name }}</h3>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div id="service" class="hms-section hms-services wow fadeIn">
            <div class="container">
                <div class="heading">
                    <h2>Services &amp; Appointment</h2>
                </div>
                <div class="row">
                    <div class="col-lg-8 col-md-7 col-sm-12 col-xs-12">
                        <div class="row hms-service-tiles">
                            @php
                                $services = $departments->take(6);
                            @endphp
                            @forelse ($services as $service)
                                <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                                    <div class="serv hms-serv">
                                        <span class="icon-service"><i class="fa fa-stethoscope"></i></span>
                                        <h4>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($service->name, 24, '')) }}</h4>
                                        <p>{{ $service->description ? \Illuminate\Support\Str::limit($service->description, 60) : 'Expert care from our ' . $service->name . ' team.' }}</p>
                                    </div>
                                </div>
                            @empty
                                @foreach (['Emergency Care', 'Diagnostics', 'Pharmacy', 'Outpatient', 'Day Care', 'Health Checkups'] as $name)
                                    <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                                        <div class="serv hms-serv">
                                            <span class="icon-service"><i class="fa fa-stethoscope"></i></span>
                                            <h4>{{ \Illuminate\Support\Str::upper($name) }}</h4>
                                            <p>Professional care from our specialist team.</p>
                                        </div>
                                    </div>
                                @endforeach
                            @endforelse
                        </div>
                    </div>
                    @livewire('appointmentform')
                </div>
            </div>
        </div>

        <div id="doctors" class="hms-section db">
            <div class="container">
                <div class="heading">
                    <h2>Our Doctors</h2>
                </div>
                <div class="row dev-list text-center">
                    @forelse ($doctors->take(3) as $doctor)
                        <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12 wow fadeIn">
                            <div class="widget clearfix">
                                <img src="{{ storage_url($doctor->image ?? null, 'employee-placeholder.jpg') }}"
                                    alt="{{ $doctor->name }}" class="img-responsive img-rounded">
                                <div class="widget-title">
                                    <h3>{{ $doctor->name }}</h3>
                                    <small>{{ $doctor->position === 'doctor' ? 'Consultant' : ucfirst($doctor->position) }}</small>
                                </div>
                                <p>{{ $doctor->qualification ?: 'Consultant at ' . $site['name'] . '.' }}</p>
                                <div class="footer-social">
                                    <a href="{{ $site['facebook'] }}" class="btn grd1"><i class="fa fa-facebook"></i></a>
                                    <a href="{{ $site['linkedin'] }}" class="btn grd1"><i class="fa fa-linkedin"></i></a>
                                    <a href="{{ $site['twitter'] }}" class="btn grd1"><i class="fa fa-twitter"></i></a>
                                </div>
                            </div>
                        </div>
                    @empty
                        @for ($i = 1; $i <= 3; $i++)
                            <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                                <div class="widget clearfix">
                                    <img src="{{ asset('images/doctor_0' . $i . '.jpg') }}" alt=""
                                        class="img-responsive img-rounded">
                                    <div class="widget-title">
                                        <h3>Consultant</h3>
                                        <small>{{ ['Cardiology', 'Neurology', 'Orthopaedics'][$i - 1] }}</small>
                                    </div>
                                    <p>Profile will appear once a doctor is added in the admin panel.</p>
                                </div>
                            </div>
                        @endfor
                    @endforelse
                </div>
            </div>
        </div>

        <div id="testimonials" class="hms-section wb wow fadeIn">
            <div class="container">
                <div class="heading">
                    <h2>What Our Patients Say</h2>
                </div>
                <div class="row">
                    @foreach ($doctors->take(2) as $doctor)
                        <div class="col-md-6 col-sm-12 wow fadeIn">
                            <div class="testimonial clearfix">
                                <div class="desc">
                                    <h3><i class="fa fa-quote-left"></i> Trusted care</h3>
                                    <p class="lead">"Coordinated diagnosis, careful treatment and clear follow-up."</p>
                                </div>
                                <div class="testi-meta">
                                    <img src="{{ storage_url($doctor->image ?? null, 'employee-placeholder.jpg') }}"
                                        alt="{{ $doctor->name }}" class="img-responsive alignleft">
                                    <h4>{{ $doctor->name }} <small>- {{ $doctor->qualification ?: 'Consultant' }}</small></h4>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div id="reach-us" class="hms-section wow fadeIn">
            <div class="container">
                <div class="heading">
                    <h2>Get in Touch</h2>
                </div>
                <div class="hms-contact">
                    <div class="hms-contact-card">
                        <h4><i class="fa fa-map-marker"></i>Visit Us</h4>
                        <p>{{ $site['address'] }}</p>
                    </div>
                    <div class="hms-contact-card">
                        <h4><i class="fa fa-phone"></i>Call / Email</h4>
                        <p><a href="tel:{{ $site['phone'] }}">{{ $site['phone'] }}</a><br>
                            <a href="mailto:{{ $site['email'] }}">{{ $site['email'] }}</a></p>
                    </div>
                    <div class="hms-contact-card">
                        <h4><i class="fa fa-clock-o"></i>Working Hours</h4>
                        <p>{{ $site['working_hours'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
