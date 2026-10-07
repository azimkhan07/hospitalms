<?php

namespace App\Http\Livewire\Admins;

use App\Models\DeliveryOrder;
use App\Models\patient;
use App\Models\User;
use App\Services\DeliveryService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('admins.layouts.app')]
class Deliveries extends Component
{
    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $patientId = null;

    public string $patientSearch = '';

    public string $orderType = 'medicine';

    public string $address = '';

    public string $phone = '';

    public string $note = '';

    public string $deliveryFee = '0';

    public string $riderName = '';

    public string $vehicle = '';

    public function mount(): void
    {
        if (! hms_deliveries_enabled()) {
            abort(404);
        }
    }

    public function render()
    {
        if (! hms_can('deliveries')) {
            abort(403);
        }

        $query = DeliveryOrder::with(['patient:id,name,phone', 'creator:id,name'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest();

        $orders = $query->paginate(15);

        $counts = DeliveryOrder::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('livewire.admins.deliveries', [
            'orders' => $orders,
            'counts' => $counts,
            'patients' => $this->patientSearch !== '' ? $this->patients() : collect(),
            'canManage' => hms_can('deliveries.manage'),
        ]);
    }

    private function patients()
    {
        return patient::query()
            ->where(function ($q) {
                $q->where('name', 'like', "%{$this->patientSearch}%")
                    ->orWhere('phone', 'like', "%{$this->patientSearch}%");
            })
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'phone', 'address']);
    }

    public function toggleForm(): void
    {
        if (! hms_can('deliveries')) {
            abort(403);
        }

        $this->showForm = ! $this->showForm;
        $this->patientId = null;
        $this->patientSearch = '';
    }

    public function pickPatient(int $id): void
    {
        $patient = patient::findOrFail($id);

        $this->patientId = $patient->id;
        $this->address = $this->address ?: (string) $patient->address;
        $this->phone = $this->phone ?: (string) $patient->phone;
        $this->patientSearch = $patient->name;
    }

    public function createOrder(): void
    {
        if (! hms_can('deliveries')) {
            abort(403);
        }

        $this->validate([
            'patientId' => 'required|exists:patients,id',
            'orderType' => ['required', 'in:medicine,lab,general'],
            'address' => 'required|max:255',
            'phone' => 'required|max:40',
            'deliveryFee' => 'nullable|numeric|min:0',
            'note' => 'nullable|max:255',
        ]);

        app(DeliveryService::class)->create([
            'patient_id' => $this->patientId,
            'order_type' => $this->orderType,
            'address' => $this->address,
            'phone' => $this->phone,
            'delivery_fee' => $this->deliveryFee === '' ? 0 : $this->deliveryFee,
            'note' => $this->note ?: null,
        ], auth()->user());

        $this->showForm = false;
        $this->reset('patientId', 'patientSearch', 'orderType', 'address', 'phone', 'note', 'deliveryFee');
        $this->statusFilter = '';
    }

    public function assignRider(int $id): void
    {
        if (! hms_can('deliveries.manage')) {
            abort(403);
        }

        $this->validate([
            'riderName' => 'required|max:120',
            'vehicle' => 'nullable|max:40',
        ]);

        DeliveryOrder::findOrFail($id)->update([
            'rider_name' => $this->riderName,
            'vehicle' => $this->vehicle ?: null,
        ]);

        $this->advance($id, DeliveryOrder::ASSIGNED);
        $this->reset('riderName', 'vehicle');
    }

    public function markOutForDelivery(int $id): void
    {
        abort_unless(hms_can('deliveries.manage'), 403);

        $this->advance($id, DeliveryOrder::OUT_FOR_DELIVERY);
    }

    public function complete(int $id): void
    {
        abort_unless(hms_can('deliveries.manage'), 403);

        $this->advance($id, DeliveryOrder::DELIVERED);
    }

    public function cancel(int $id): void
    {
        abort_unless(hms_can('deliveries.manage'), 403);

        $this->advance($id, DeliveryOrder::CANCELLED);
    }

    private function advance(int $id, string $to): void
    {
        try {
            app(DeliveryService::class)->advance(DeliveryOrder::findOrFail($id), $to, auth()->user());
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function staffOptions(): array
    {
        return User::orderBy('name')->limit(8)->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }
}