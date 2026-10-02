<div>
    <div class="row">
        @foreach ([
            ['label' => 'Total Employees', 'value' => $employees, 'icon' => 'fa-users', 'bg' => 'primary'],
            ['label' => 'Total Appointments', 'value' => $appointments, 'icon' => 'fa-calendar-check', 'bg' => 'success'],
            ['label' => 'Total Birth Reports', 'value' => $birthreports, 'icon' => 'fa-baby', 'bg' => 'info'],
            ['label' => 'Total Operation Reports', 'value' => $operationreports, 'icon' => 'fa-user-md', 'bg' => 'warning'],
            ['label' => 'Total Patients', 'value' => $patients, 'icon' => 'fa-procedures', 'bg' => 'danger'],
            ['label' => 'Total HODs', 'value' => $hods, 'icon' => 'fa-user-tie', 'bg' => 'secondary'],
            ['label' => 'Total Blocks', 'value' => $blocks, 'icon' => 'fa-cube', 'bg' => 'dark'],
            ['label' => 'Total Departments', 'value' => $departments, 'icon' => 'fa-building', 'bg' => 'light'],
            ['label' => 'Total Rooms', 'value' => $rooms, 'icon' => 'fa-door-open', 'bg' => 'info'],
            ['label' => 'Total Beds', 'value' => $beds, 'icon' => 'fa-bed', 'bg' => 'primary'],
            ['label' => 'Total Subscribers', 'value' => $subscribers, 'icon' => 'fa-user-plus', 'bg' => 'success'],
            ['label' => 'Requested Appointments', 'value' => $requestedAppointment, 'icon' => 'fa-calendar-plus', 'bg' => 'warning'],
            ['label' => 'Active Staff Logins', 'value' => $staffAccounts, 'icon' => 'fa-user-friends', 'bg' => 'primary'],
            ['label' => 'Pending Leave', 'value' => $pendingLeave, 'icon' => 'fa-plane-departure', 'bg' => 'info'],
            ['label' => 'Upcoming Meetings', 'value' => $upcomingMeetings, 'icon' => 'fa-calendar-alt', 'bg' => 'success'],
            ['label' => 'Expired Medicines', 'value' => $expiredMedicines, 'icon' => 'fa-exclamation-triangle', 'bg' => 'danger'],
        ] as $card)
            <div class="col-md-2 col-sm-4 col-6">
                <div class="card bg-{{ $card['bg'] }} {{ in_array($card['bg'], ['light', 'warning'], true) ? '' : 'text-white' }}">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0" style="font-size:11px;font-weight:600">{{ $card['label'] }}</h6>
                            <i class="fas {{ $card['icon'] }} opacity-75" style="font-size:12px"></i>
                        </div>
                        <p class="card-text mb-0" style="font-size:19px;font-weight:700;line-height:1.2">{{ $card['value'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

@if (hms_can('meetings'))
    <div class="mt-2">
        <h4 class="mb-1"><i class="fas fa-calendar-alt text-info mr-1"></i> Meeting Calendar</h4>
        <livewire:admins.meeting-calendar :key="'dash-calendar'" />
    </div>
@endif

@if (hms_can('leave'))
    <div class="mt-2">
        <div class="box box-primary">
            <div class="box-header">
                <h3 class="box-title"><i class="fas fa-plane-departure text-info mr-1"></i> Leave Snapshot</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="border rounded p-2 text-center">
                            <div class="text-muted" style="font-size:10.5px">PENDING</div>
                            <div style="font-size:18px;font-weight:700">{{ $pendingLeave }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2 text-center">
                            <div class="text-muted" style="font-size:10.5px">MY APPROVED</div>
                            <div style="font-size:18px;font-weight:700">{{ $myApprovedLeave }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2 text-center">
                            <div class="text-muted" style="font-size:10.5px">MY UPCOMING MEETINGS</div>
                            <div style="font-size:18px;font-weight:700">{{ $myMeetings }}</div>
                        </div>
                    </div>
                </div>

                @if (hms_can('leave.review') && $pendingLeave > 0)
                    <div class="mt-2" style="font-size:11.5px">
                        <i class="fas fa-bell text-warning mr-1"></i>
                        {{ $pendingLeave }} leave request(s) are waiting for your decision.
                        <a href="{{ route('admin_leave') }}" class="ml-1">Open review queue &rarr;</a>
                    </div>
                @endif
                </div>
            </div>
        </div>
    @endif
</div>