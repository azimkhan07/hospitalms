<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsActivity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class bill extends Model
{
    use BelongsToTenant, HasFactory, RecordsActivity, SoftDeletes;
    protected $fillable=[
        'patients_id',
        'stay_id',
        'status',
        'amount',
        'tax',
        'discount',
        'discount_amount',
        'advance_used',
        'remarks',
        'invoice_no',
        'paid_at',
        'issued_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'advance_used' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function patient(){
        return $this->belongsTo(patient::class, 'patients_id');
    }

    public function payments()
    {
        return $this->hasMany(payment::class, 'bill_id');
    }

    public function items()
    {
        return $this->hasMany(BillItem::class, 'bill_id');
    }

    public function stay()
    {
        return $this->belongsTo(stay::class, 'stay_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /** Itemised charges when they exist, otherwise the legacy typed amount. */
    public function grossAmount(): float
    {
        if ($this->items()->exists()) {
            return (float) $this->items()->sum('amount');
        }

        return (float) $this->amount;
    }

    public function netAmount(): float
    {
        return $this->grossAmount() + (float) $this->tax
            - (float) $this->discount - (float) $this->discount_amount;
    }

    public function advanceApplied(): float
    {
        return (float) $this->advance_used;
    }

    public function paidTotal(): float
    {
        return (float) $this->payments()->where('status', 'paid')->sum('amount');
    }

    public function amountDue(): float
    {
        return max(0.0, $this->netAmount() - $this->advanceApplied() - $this->paidTotal());
    }

    /**
     * Persist the billing base (line items + tax, or the typed amount for a
     * legacy bill) and settle the status. Discounts and advances never fold
     * into the stored base, so repeated recalcs stay idempotent.
     */
    public function recalculate(): self
    {
        if ($this->items()->exists()) {
            $this->amount = round((float) $this->items()->sum('amount') + (float) $this->tax, 2);
        }

        $net = $this->netAmount();
        $due = max(0.0, $net - $this->advanceApplied() - $this->paidTotal());

        $this->status = $due <= 0 ? 'paid' : ($this->status === 'paid' ? 'paid' : 'unpaid');
        $this->paid_at = $this->status === 'paid' ? ($this->paid_at ?? now()) : null;
        $this->save();

        return $this;
    }

    /**
     * Close the bill against the patient's latest IPD stay: one accommodation
     * line covering the nights from admission to discharge (or now), the stay
     * stamped on the bill and the totals rebuilt. Returns null when there is
     * no patient or the patient was never admitted.
     */
    public function finaliseFromStay(): ?BillItem
    {
        if (! $this->patients_id) {
            return null;
        }

        $stay = stay::where('patient_id', $this->patients_id)
            ->orderByDesc('start_time')
            ->first();

        if (! $stay) {
            return null;
        }

        $since = $stay->discharged_at ?? now();
        $start = $stay->start_time;
        $nights = $start ? max(1, (int) ceil(abs($since->diffInDays($start)))) : 1;
        $rate = (float) ($stay->room?->daily_rate ?? 0);

        $item = BillItem::create([
            'bill_id' => $this->id,
            'description' => 'Accommodation & nursing: '.$nights.' night(s)',
            'category' => 'room',
            'qty' => $nights,
            'rate' => $rate,
            'amount' => round($nights * $rate, 2),
            'created_by' => auth()->id(),
        ]);

        $this->stay_id = $stay->id;

        if (blank($this->remarks)) {
            $this->remarks = 'Final bill from IPD stay';
        }

        $this->recalculate();

        return $item;
    }
}
