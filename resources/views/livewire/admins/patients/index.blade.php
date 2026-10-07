<div>
    <div class="content">
        <div class="container">
            <div class="row page-title row">
                <div class="col">
                    <h3 class="text-info">{{ env('APP_NAME') }} Patients</h3>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary" wire:click="show_create_form">Add New</button>
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
                    <div class="d-flex align-items-center flex-wrap mb-2">
                        <input type="search" class="form-control form-control-sm mr-2 mb-1" style="max-width:200px"
                            placeholder="Search name, phone, email..." wire:model.live.debounce.300ms="search">
                        <select class="form-control form-control-sm mr-2 mb-1" style="max-width:180px"
                            wire:model.live="schemeFilter">
                            <option value="">All schemes</option>
                            @foreach ($schemes ?? [] as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <select class="form-control form-control-sm mr-2 mb-1" style="max-width:180px"
                            wire:model.live="angioFilter">
                            <option value="">Treatment: all</option>
                            <option value="1">Via angio machine</option>
                            <option value="0">Not via angio</option>
                        </select>
                        <button type="button" class="btn btn-xs btn-default mr-2 mb-1"
                            wire:click="resetFilters">Clear</button>
                    </div>
                    <table width="100%" class="table table-hover" id="">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Scheme</th>
                                <th>Dated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($patients as $patient)
                                <tr>
                                    <td>{{ $patient->name }}</td>
                                    <td>{{ $patient->email }}</td>
                                    <td>{{ $patient->age ?: 'Null' }}</td>
                                    <td>{{ $patient->gender ?: 'Null' }}</td>
                                    <td>
                                        @if ($patient->scheme)
                                            <span class="label label-info">{{ $patient->scheme->name }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $patient->created_at }}</td>
                                    <td class="text-right">
                                        <button wire:click="show_edit_form({{ $patient->id }})"
                                            class="btn btn-outline-info btn-rounded"><i class="fas fa-pen"></i></button>
                                        <button wire:click="delete({{ $patient->id }})"
                                            onclick="return confirm('{{ __('Are You Sure ?') }}')"
                                            class="btn btn-outline-danger btn-rounded"><i
                                                class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-warning" colspan="7">{{ __('Null') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $patients->links() }}
                </div>
            </div>
        </div>
