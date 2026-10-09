<?php

namespace App\Http\Livewire\Admins;

use App\Models\BillItem;
use App\Models\bill;
use App\Models\patient;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class Bills extends Component
{

    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $patients_id;
    public $amount;
    public $edit_bill_id;
    public $button_text = "Add New Bill";

    public $discount_amount = 0;
    public $advance_used = 0;
    public $remarks;

    // Line item drawer
    public $itemBillId;
    public $item_description;
    public $item_category = 'other';
    public $item_qty = 1;
    public $item_rate = 0;

    public function add_bill()
    {
        if ($this->edit_bill_id) {

            $this->update($this->edit_bill_id);

            return;
        }

        $this->normaliseMoney();

        $this->validate([
            'patients_id' => 'required',
            'amount' => 'required|numeric',
            'discount_amount' => 'nullable|numeric|min:0',
            'advance_used' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:255',
        ]);

        $bill = bill::create([
            'patients_id' => $this->patients_id,
            'amount' => $this->amount,
            'discount_amount' => $this->discount_amount,
            'advance_used' => $this->advance_used,
            'remarks' => $this->remarks,
        ]);

        $bill->recalculate();

        $this->resetBillForm();

        session()->flash('message', 'Bill Created successfully.');
    }


     public function edit($id)
    {
        $bill = bill::findOrFail($id);
        $this->edit_bill_id = $id;
        $this->amount = $bill->amount;
        $this->patients_id = $bill->patients_id;
        $this->discount_amount = $bill->discount_amount;
        $this->advance_used = $bill->advance_used;
        $this->remarks = $bill->remarks;

        $this->button_text="Update Bill";
    }

    public function update($id)
    {
        $this->normaliseMoney();

        $this->validate([
            'amount' => 'required|numeric',
            'patients_id' => 'required|numeric',
            'discount_amount' => 'nullable|numeric|min:0',
            'advance_used' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:255',
        ]);

        $bill = bill::findOrFail($id);
        $bill->amount = $this->amount;
        $bill->patients_id = $this->patients_id;
        $bill->discount_amount = $this->discount_amount;
        $bill->advance_used = $this->advance_used;
        $bill->remarks = $this->remarks;

        $bill->save();
        $bill->recalculate();

        $this->resetBillForm();
        $this->edit_bill_id=null;

        session()->flash('message', 'Bill Updated Successfully.');

        $this->button_text = "Add New Bill";

}

     public function delete($id)
    {
        bill::findOrFail($id)->delete();
        session()->flash('message', 'Bill Deleted Successfully.');

        $this->resetBillForm();
        $this->edit_bill_id=null;
    }

    public function openItems($billId)
    {
        $this->itemBillId = (int) $billId;
        $this->resetItemForm();
    }

    public function closeItems()
    {
        $this->itemBillId = null;
        $this->resetItemForm();
    }

    public function addItem()
    {
        if (! $this->itemBillId) {
            session()->flash('error', 'Open a bill before adding line items.');

            return;
        }

        $this->validate([
            'item_description' => 'required|string|max:255',
            'item_category' => 'nullable|in:'.implode(',', BillItem::CATEGORIES),
            'item_qty' => 'required|numeric|min:0.01',
            'item_rate' => 'required|numeric|min:0',
        ]);

        $bill = bill::findOrFail($this->itemBillId);

        BillItem::create([
            'bill_id' => $bill->id,
            'description' => $this->item_description,
            'category' => $this->item_category ?: 'other',
            'qty' => $this->item_qty,
            'rate' => $this->item_rate,
            'amount' => round((float) $this->item_qty * (float) $this->item_rate, 2),
            'created_by' => auth()->id(),
        ]);

        $bill->recalculate();

        $this->resetItemForm();

        session()->flash('message', 'Line item added.');
    }

    public function removeItem($id)
    {
        $item = BillItem::findOrFail($id);
        $bill = bill::findOrFail($item->bill_id);

        $item->delete();
        $bill->recalculate();

        session()->flash('message', 'Line item removed.');
    }

    /**
     * Close an IPD bill: charge the accommodation nights of the patient's
     * latest stay onto the bill (PLAN billing milestone).
     */
    public function finaliseStay($billId)
    {
        $bill = bill::findOrFail($billId);

        if (! $bill->patients_id) {
            session()->flash('error', 'Attach a patient to the bill before finalising it.');

            return;
        }

        if (! $bill->finaliseFromStay()) {
            session()->flash('error', 'No IPD stay found for this patient.');

            return;
        }

        session()->flash('message', 'Final bill generated from the IPD stay.');
    }

    private function normaliseMoney(): void
    {
        $this->discount_amount = ($this->discount_amount === '' || $this->discount_amount === null)
            ? 0
            : $this->discount_amount;
        $this->advance_used = ($this->advance_used === '' || $this->advance_used === null)
            ? 0
            : $this->advance_used;
        $this->remarks = $this->remarks ?: null;
    }

    private function resetBillForm(): void
    {
        $this->amount = null;
        $this->patients_id = null;
        $this->discount_amount = 0;
        $this->advance_used = 0;
        $this->remarks = null;
    }

    private function resetItemForm(): void
    {
        $this->item_description = null;
        $this->item_category = 'other';
        $this->item_qty = 1;
        $this->item_rate = 0;
    }

    public function render()
    {
        if (! hms_can('bills')) { abort(403); }

        $itemBill = $this->itemBillId ? bill::find($this->itemBillId) : null;

        return view('livewire.admins.bills',[
            'bills' =>bill::with('patient')->withCount('items')->latest()->paginate(10),
            'patients' =>patient::all(),
            'itemBill' => $itemBill,
            'items' => $itemBill ? $itemBill->items()->orderBy('id')->get() : collect(),
            'categories' => BillItem::CATEGORIES,
        ]);
    }
}
