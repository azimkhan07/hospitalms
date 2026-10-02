<div class="col-lg-4 col-md-5 col-sm-12 col-xs-12">
    <div class="appointment-form hms-book">
        <h3><span>+</span> Book Appointment</h3>
        <div class="form">
            @if ($booked)
                <div class="alert alert-success hms-book-alert">
                    <i class="fa fa-check-circle"></i>
                    {{ session('booked') }}
                </div>
                <button type="button" class="btn hms-book-btn" wire:click="$set('booked', false)">
                    Book another
                </button>
            @else
                <form wire:submit.prevent="store_requested_appointment">
                    <div class="hms-book-grid">
                        <div class="form-group">
                            <input type="text" wire:model="name" placeholder="Full name" class="form-control form-control-sm">
                            @error('name') <span class="hms-err">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <input type="tel" wire:model="phone" placeholder="Phone number" class="form-control form-control-sm">
                            @error('phone') <span class="hms-err">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <input type="email" wire:model="email" placeholder="Email (optional)" class="form-control form-control-sm">
                            @error('email') <span class="hms-err">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <select wire:model="doctor_id" class="form-control form-control-sm">
                                <option value="">Choose doctor</option>
                                @forelse ($doctors as $doctor)
                                    <option value="{{ $doctor->id }}">
                                        {{ $doctor->employ?->name ?? 'Doctor #'.$doctor->id }}</option>
                                @empty
                                    <option value="">No doctor available</option>
                                @endforelse
                            </select>
                            @error('doctor_id') <span class="hms-err">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <input type="datetime-local" wire:model="stime" class="form-control form-control-sm">
                            @error('stime') <span class="hms-err">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <input type="text" wire:model="address" placeholder="Address" class="form-control form-control-sm">
                            @error('address') <span class="hms-err">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group hms-book-full">
                            <textarea wire:model="message" rows="2" class="form-control form-control-sm"
                                placeholder="Reason / message (optional)"></textarea>
                            @error('message') <span class="hms-err">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="center">
                        <button type="submit" class="hms-book-btn" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="store_requested_appointment">Request Appointment</span>
                            <span wire:loading wire:target="store_requested_appointment">Sending...</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
