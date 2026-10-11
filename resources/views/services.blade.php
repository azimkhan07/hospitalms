@extends('layouts.app')

@section('content')
<div id="service" class="hms-inner hms-services wow fadeIn">
    <div class="container">
        <div class="heading">
            <span class="icon-logo"><img src="{{ $site['icon'] ? storage_url($site['icon']) : asset('images/icon-logo.png') }}" alt="#"></span>
            <h2>{{ $site['services_page_heading'] ?: ($site['services_heading'] ?: 'Our Services') }}</h2>
        </div>

        <div class="row">
            <div class="col-lg-8 col-md-7 col-sm-12 col-xs-12">
                <div class="row hms-service-tiles">
                    @forelse (\App\Models\SiteContent::features() as $feature)
                        <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                            <div class="hms-serv">
                                <span class="icon-service">
                                    @if ($feature->icon)
                                        <img src="{{ storage_url($feature->icon) }}" alt="{{ $feature->title }}" style="width:26px;height:26px;object-fit:contain;">
                                    @else
                                        <i class="fa fa-stethoscope"></i>
                                    @endif
                                </span>
                                <h4>{{ $feature->title }}</h4>
                                <p>{{ $feature->text }}</p>
                            </div>
                        </div>
                    @empty
                        @foreach (['Emergency Care', 'Diagnostics', 'Pharmacy', 'Outpatient', 'Day Care', 'Health Checkups'] as $name)
                            <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                                <div class="hms-serv">
                                    <span class="icon-service"><i class="fa fa-stethoscope"></i></span>
                                    <h4>{{ $name }}</h4>
                                    <p>Professional care from our specialist team.</p>
                                </div>
                            </div>
                        @endforeach
                    @endforelse
                </div>
            </div>
            <div class="col-lg-4 col-md-5 col-sm-12 col-xs-12">
                @livewire('appointmentform')
            </div>
        </div>

        @if ($departments->count())
            <div class="row hms-dept-row">
                @foreach ($departments->take(4) as $dept)
                    <div class="col-md-3 col-sm-6 col-xs-12">
                        <div class="service-widget">
                            <div class="post-media wow fadeIn">
                                <img src="{{ storage_url($dept->photo_path, 'department-placeholder.jpg') }}" alt="{{ $dept->name }}" class="img-responsive">
                            </div>
                            <h3>{{ $dept->name }}</h3>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection