<div>
    <div class="content">
        <div class="container">
            <div class="row page-title row">
                <div class="col">
                    <h3 class="text-info">Website Appointment Requests</h3>
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-primary" wire:click="show_create_form">Add New</button>
                </div>
            </div>
            <div>
                @if (session()->has('message'))
                    <div class="alert alert-success">
                        {{ session('message') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif
            </div>
            <div class="box box-primary">
                <div class="box-body">
                    <div class="text-info" wire:loading>Loading..</div>
                    <br>
                    <hr>
                    <div class="text-capitalize bg-dark p-2 shadow mb-3 text-center text-lg text-light rounded">
                        {{ __('All Requtested Appointments') }}</div>
                    <table width="100%" class="table table-hover" id="">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Doctor</th>
                                <th>Address</th>
                                <th>Message</th>
                                <th>Scheduled Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($appointments as $request)
                                <tr>
                                    <td>{{ $request->name }}</td>
                                    <td>{{ $request->email }}</td>
                                    <td>{{ $request->phone }}</td>
                                    <td>{{ $request->doctor?->employ?->name ?? '-' }}</td>
                                    <td>{{ $request->address }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($request->message, 40) }}</td>
                                    <td>{{ $request->stime ? \Illuminate\Support\Carbon::parse($request->stime)->format('d M Y, h:i A') : '-' }}</td>
                                    <td class="text-right">

                                        @if (
                                            !App\Models\patient::where([
                                                'name' => $request->name,
                                                'email' => $request->email,
                                                'phone' => $request->phone,
                                                'address' => $request->address,
                                            ])->exists())
                                            <button wire:click="add_patient({{ $request->id }})"
                                                title="add as a patient" class="btn btn-sm btn-outline-info"><i
                                                    class="fas fa-plus"></i></button>
                                        @endif

                                        <button title="delete request" onclick="return confirm('Are You Sure ?')"
                                            wire:click="delete({{ $request->id }})"
                                            class="btn btn-sm btn-outline-danger"><i
                                                class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-2">No website appointment requests yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $appointments->links() }}
                </div>
            </div>
        </div>
