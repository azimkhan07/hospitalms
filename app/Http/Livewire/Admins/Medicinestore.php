<?php

namespace App\Http\Livewire\Admins;

use App\Models\medicine;
use App\Models\StockMovement;
use App\Services\Pharmacy;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class Medicinestore extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $search = '';

    public ?int $edit_medicine_id = null;

    public string $button_text = 'Add New Medicine';

    // Master fields (a row is one medicine / batch).
    public $name;
    public $code;
    public $generic;
    public $composition;
    public $price;
    public $mrp;
    public $manufacturer;
    public $supplier;
    public $batch_no;
    public $expiry_date;
    public $mfg_date;
    public $reorder_level;
    public $quantity; // opening stock when a new medicine is first added

    // Stock actions live in a small drawer under one row at a time.
    public ?int $stockInId = null;

    public bool $showStockIn = false;

    public $inQty;

    public $inNote;

    public ?int $ledgerId = null;

    public ?int $writeOffId = null;

    public $writeOffQty;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:60',
            'price' => 'required|numeric|min:0',
            'mrp' => 'nullable|numeric|min:0',
            'expiry_date' => 'nullable|date|after_or_equal:today',
            'mfg_date' => 'nullable|date|before:expiry_date',
            'reorder_level' => 'nullable|integer|min:0',
        ];
    }

    public function add_medicine(): void
    {
        $this->validate();

        if (! hms_can('medicines.manage')) {
            abort_if(hms_can('medicines') === false, 403);
        }

        $medicine = medicine::create([
            'name' => $this->name,
            'code' => $this->code,
            'generic' => $this->generic ?: null,
            'composition' => $this->composition ?: null,
            'price' => $this->price ?? 0,
            'mrp' => $this->mrp ?: null,
            'manufacturer' => $this->manufacturer ?: null,
            'supplier' => $this->supplier ?: null,
            'batch_no' => $this->batch_no ?: null,
            'expiry_date' => $this->expiry_date,
            'mfg_date' => $this->mfg_date,
            'reorder_level' => $this->reorder_level,
            'stock' => 0,
            'quantity' => 0,
        ]);

        $opening = (int) $this->quantity;

        if ($opening > 0) {
            app(Pharmacy::class)->purchase($medicine, $opening, $medicine->batch_no, 'Opening stock');
        }

        session()->flash('message', $medicine->name.' has been added to the store.');

        $this->resetForm();
    }

    public function edit($id): void
    {
        if (! hms_can('medicines.manage')) {
            abort(403);
        }

        $medicine = medicine::findOrFail($id);

        $this->edit_medicine_id = $id;
        $this->name = $medicine->name;
        $this->code = $medicine->code;
        $this->generic = $medicine->generic;
        $this->composition = $medicine->composition;
        $this->price = $medicine->price;
        $this->mrp = $medicine->mrp;
        $this->manufacturer = $medicine->manufacturer;
        $this->supplier = $medicine->supplier;
        $this->batch_no = $medicine->batch_no;
        $this->expiry_date = $medicine->expiry_date?->format('Y-m-d');
        $this->mfg_date = $medicine->mfg_date?->format('Y-m-d');
        $this->reorder_level = $medicine->reorder_level;

        $this->button_text = 'Update Medicine';
    }

    public function update(): void
    {
        if (! hms_can('medicines.manage')) {
            abort(403);
        }

        $this->validate();

        $medicine = medicine::findOrFail($this->edit_medicine_id);

        $medicine->update([
            'name' => $this->name,
            'code' => $this->code,
            'generic' => $this->generic ?: null,
            'composition' => $this->composition ?: null,
            'price' => $this->price ?? 0,
            'mrp' => $this->mrp ?: null,
            'manufacturer' => $this->manufacturer ?: null,
            'supplier' => $this->supplier ?: null,
            'batch_no' => $this->batch_no ?: null,
            'expiry_date' => $this->expiry_date,
            'mfg_date' => $this->mfg_date,
            'reorder_level' => $this->reorder_level,
        ]);

        session()->flash('message', $medicine->name.' updated.');

        $this->resetForm();
    }

    public function delete($id): void
    {
        if (! hms_can('medicines.manage')) {
            abort(403);
        }

        medicine::findOrFail($id)->delete();

        session()->flash('message', 'Medicine removed.');
    }

    public function openStockIn(int $id): void
    {
        $this->stockInId = $id;
        $this->showStockIn = true;
    }

    public function recordStockIn(): void
    {
        if (! hms_can('medicines.manage')) {
            abort(403);
        }

        $this->validate(['inQty' => 'required|integer|min:1', 'inNote' => 'nullable|string|max:255']);

        $medicine = medicine::findOrFail($this->stockInId);

        app(Pharmacy::class)->purchase($medicine, (int) $this->inQty, $medicine->batch_no, $this->inNote ?: 'Stock in');

        session()->flash('message', $this->inQty.' units added to '.$medicine->name.'.');

        $this->stockInId = null;
        $this->showStockIn = false;
        $this->inQty = null;
        $this->inNote = null;
    }

    public function openWriteOff(int $id): void
    {
        $this->writeOffId = $id;
        $this->writeOffQty = null;
    }

    public function recordWriteOff(string $type = 'expiry'): void
    {
        if (! hms_can('medicines.manage')) {
            abort(403);
        }

        $this->validate(['writeOffQty' => 'required|integer|min:1']);

        $medicine = medicine::findOrFail($this->writeOffId);

        app(Pharmacy::class)->writeOff($medicine, (int) $this->writeOffQty, $type, ucfirst($type).' write-off');

        session()->flash('message', $this->writeOffQty.' units written off from '.$medicine->name.'.');

        $this->writeOffId = null;
        $this->writeOffQty = null;
    }

    public function openLedger(int $id): void
    {
        $this->ledgerId = $id;
    }

    public function closeLedger(): void
    {
        $this->ledgerId = null;
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'code', 'generic', 'composition', 'price', 'mrp', 'manufacturer',
            'supplier', 'batch_no', 'expiry_date', 'mfg_date', 'reorder_level', 'quantity', 'edit_medicine_id']);
        $this->button_text = 'Add New Medicine';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        if (! hms_can('medicines')) {
            abort(403);
        }

        $medicines = medicine::query()
            ->when($this->search !== '', fn ($q) => $q->where(function ($w) {
                $term = '%'.mb_strtolower($this->search).'%';
                $w->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(generic) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(batch_no) LIKE ?', [$term]);
            }))
            ->orderBy('expiry_date')
            ->paginate(12);

        $movements = $this->ledgerId
            ? StockMovement::where('medicine_id', $this->ledgerId)->latest()->limit(40)->get()
            : collect();

        $ledgerMedicine = $this->ledgerId ? medicine::find($this->ledgerId) : null;

        return view('livewire.admins.medicinestore', [
            'medicines' => $medicines,
            'movements' => $movements,
            'ledgerMedicine' => $ledgerMedicine,
            'canManage' => hms_can('medicines.manage'),
            'alerts' => [
                'expired' => medicine::whereNull('deleted_at')->whereNotNull('expiry_date')->whereDate('expiry_date', '<', today())->where('stock', '>', 0)->count(),
                'soon30' => medicine::whereNull('deleted_at')->whereNotNull('expiry_date')->whereBetween('expiry_date', [today(), today()->addDays(30)])->where('stock', '>', 0)->count(),
                'soon60' => medicine::whereNull('deleted_at')->whereNotNull('expiry_date')->whereBetween('expiry_date', [today()->addDays(30), today()->addDays(60)])->where('stock', '>', 0)->count(),
                'soon90' => medicine::whereNull('deleted_at')->whereNotNull('expiry_date')->whereBetween('expiry_date', [today()->addDays(60), today()->addDays(90)])->where('stock', '>', 0)->count(),
                'low' => medicine::whereNull('deleted_at')->where('stock', '>', 0)->whereColumn('stock', '<=', 'reorder_level')->count(),
                'out' => medicine::whereNull('deleted_at')->where(function ($q) {
                    $q->whereNull('stock')->orWhere('stock', '<=', 0);
                })->count(),
            ],
        ]);
    }
}