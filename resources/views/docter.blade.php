@extends('layouts.app')

@section('content')
<div id="doctors" class="parallax section db" data-stellar-background-ratio="0.4" style="background:#fff;" data-scroll-id="doctors" tabindex="-1">
    <div class="container">

        <div class="heading">
            <span class="icon-logo"><img src="{{ asset('images/icon-logo.png') }}" alt="#"></span>
            <h2>Our Specialists</h2>
            <p class="lead">Meet the consultants looking after you — each specialist is attached to a department so your records, prescriptions and follow-ups stay on one file.</p>
        </div>

        <div class="row dev-list text-center">
            @forelse ($doctors as $doctor)
                <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12 wow fadeIn" data-wow-duration="1s" data-wow-delay="0.2s">
                    <div class="widget clearfix">
                        <img src="{{ storage_url($doctor->image ?? null, 'employee-placeholder.jpg') }}" alt="{{ $doctor->name }}" class="img-responsive img-rounded">
                        <div class="widget-title">
                            <h3>{{ $doctor->name }}</h3>
                            <small>{{ $doctor->qualification ?: 'Consultant' }}</small>
                        </div>
                        <!-- end title -->
                        <p>{{ $doctor->about ?: 'Consultant at ' . $site['name'] . '.' }}</p>

                        <div class="footer-social">
                            <a href="{{ $doctor->facebook ?: $site['facebook'] }}" class="btn grd1"><i class="fa fa-facebook"></i></a>
                            <a href="{{ $doctor->twitter ?: $site['twitter'] }}" class="btn grd1"><i class="fa fa-twitter"></i></a>
                            <a href="{{ $doctor->linkedin ?: $site['linkedin'] }}" class="btn grd1"><i class="fa fa-linkedin"></i></a>
                            <a href="{{ $doctor->instagram ?: $site['instagram'] }}" class="btn grd1"><i class="fa fa-instagram"></i></a>
                        </div>
                    </div><!--widget -->
                </div><!-- end col -->
            @empty
                @for ($i = 1; $i <= 6; $i++)
                    <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12 wow fadeIn">
                        <div class="widget clearfix">
                            <img src="{{ asset('images/doctor_0' . (($i - 1) % 3 + 1) . '.jpg') }}" alt="" class="img-responsive img-rounded">
                            <div class="widget-title">
                                <h3>Consultant</h3>
                                <small>{{ ['Cardiology', 'Neurology', 'Orthopaedics', 'Paediatrics', 'Gynaecology', 'General Medicine'][$i - 1] }}</small>
                            </div>
                            <p>Doctor profiles will appear here once consultants are added from the admin panel.</p>
                        </div>
                    </div>
                @endfor
            @endforelse
        </div><!-- end row -->
    </div><!-- end container -->
</div>

@endsection