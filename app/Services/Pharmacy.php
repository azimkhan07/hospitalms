<?php

namespace App\Services;

use App\Models\medicine;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7 — the store's one true source of stock.
 *
 * A medicine's stock is a number maintained here, and only here. Nothing in the
 * panel ever writes stock directly; a purchase posts a positive movement, a
 * dispense posts a negative one, and each writes the medicine's balance inside
 * the same transaction, so the balance and its proof cannot disagree.
 *
 * FEFO (first-expiry first-out) is where a medicine exists in several batches:
 * rows with the same code. When the pharmacist hands a medicine over, the
 * earliest-expiry batch is taken first.
 */
class Pharmacy
{
    /**
     * Stock in from a supplier. Returns the medicine with its new balance.
     */
    public function purchase(medicine $medicine, int $quantity, ?string $batchNo = null, ?string $note = null): medicine
    {
        return $this->move($medicine, abs($quantity), 'purchase', $batchNo, $note, null);
    }

    /**
     * Return unused stock to the shelves.
     */
    public function returnStock(medicine $medicine, int $quantity, ?string $batchNo = null, ?string $note = null): medicine
    {
        return $this->move($medicine, abs($quantity), 'return', $batchNo, $note, null);
    }

    /**
     * Remove expired or damaged stock. Negative on purpose, so the ledger reads
     * as a deduction.
     */
    public function writeOff(medicine $medicine, int $quantity, string $type = 'expiry', ?string $note = null): medicine
    {
        return $this->move($medicine, -abs($quantity), $type, $medicine->batch_no, $note, null);
    }

    /**
     * Take stock out, earliest-expiry batch first. Throws with the shortfall
     * when the order cannot be covered, so the caller can tell the pharmacist
     * exactly how many units are missing.
     */
    public function dispense(medicine $medicine, int $quantity, ?User $by = null, ?string $reference = null): int
    {
        return DB::transaction(function () use ($medicine, $quantity, $by, $reference) {
            $remaining = abs($quantity);
            $glad = 0;

            // FEFO: the batch closest to expiry goes first, un-expired before
            // batches that never got a date.
            $batches = medicine::query()
                ->where('code', $medicine->code)
                ->where('stock', '>', 0)
                ->orderByRaw('expiry_date IS NULL')
                ->orderBy('expiry_date')
                ->lockForUpdate()
                ->get();

            if ($batches->isEmpty()) {
                throw new \RuntimeException($medicine->name.' is out of stock.');
            }

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($batch->stock, $remaining);

                if ($take > 0) {
                    $this->move($batch, -$take, 'dispense', $batch->batch_no, $reference, $by);
                    $glad += $take;
                    $remaining -= $take;
                }
            }

            if ($remaining > 0) {
                $held = $quantity - $remaining;

                throw new \RuntimeException(
                    'Only '.$held.' of '.$quantity.' units of '.$medicine->name.' in stock.'
                );
            }

            return $glad;
        });
    }

    /**
     * The batches that still have stock for a medicine, earliest expiry first.
     */
    public function availability(medicine $medicine): Collection
    {
        return medicine::query()
            ->where('code', $medicine->code)
            ->where('stock', '>', 0)
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->get()
            ->map(fn ($batch) => (object) [
                'id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'expiry_date' => $batch->expiry_date,
                'stock' => $batch->stock,
                'mrp' => $batch->mrp ? (float) $batch->mrp : (float) $batch->price,
            ]);
    }

    public function balance(Collection $batches): int
    {
        return $batches->sum(fn ($batch) => $batch->stock);
    }

    /**
     * Dispense the whole prescription. Runs as one transaction so a prescription
     * is never left half-handed: if any item cannot be covered, nothing moves.
     */
    public function dispensePrescription(Prescription $prescription, ?User $by, ?string $reference = null): void
    {
        DB::transaction(function () use ($prescription, $by, $reference) {
            $prescription->load('items');

            foreach ($prescription->items as $item) {
                if ($item->dispensed_qty > 0) {
                    continue;
                }

                if (! $item->medicine_id) {
                    throw new \RuntimeException($item->medicine.' has not been matched to the master.');
                }

                /** @var medicine|null $medicine */
                $medicine = medicine::query()->find($item->medicine_id);

                if (! $medicine) {
                    throw new \RuntimeException($item->medicine.' is no longer in the master.');
                }

                // The form has no quantity yet; a line is one course, so one
                // unit of stock is handed over unless more is expected.
                $qty = 1;

                $this->dispense($medicine, $qty, $by, $reference ?: 'RX #'.$prescription->id);

                $item->dispensed_qty = $qty;
                $item->dispensed_at = now();
                $item->dispensed_by = $by?->id;
                $item->save();
            }

            $prescription->dispensed_at = now();
            $prescription->dispensed_by = $by?->id;
            $prescription->save();
        });
    }

    private function move(
        medicine $medicine,
        int $signedQuantity,
        string $type,
        ?string $batchNo = null,
        ?string $note = null,
        ?User $by = null
    ): medicine {
        if ($signedQuantity === 0) {
            return $medicine;
        }

        $tenantId = $medicine->tenant_id;

        return DB::transaction(function () use ($medicine, $signedQuantity, $type, $batchNo, $note, $by, $tenantId) {
            $locked = medicine::query()->lockForUpdate()->findOrFail($medicine->id);
            $balance = ($locked->stock ?? 0) + $signedQuantity;

            if ($balance < 0) {
                throw new \RuntimeException($locked->name.' cannot go below zero stock.');
            }

            // If this batch has never held n stock, keep its batch and date on
            // the row; a purchase into an existing batch simply adds units.
            $locked->stock = $balance;
            $locked->save();

            DB::table('stock_movements')->insert([
                'tenant_id' => $tenantId,
                'medicine_id' => $locked->id,
                'quantity' => $signedQuantity,
                'type' => $type,
                'batch_no' => $batchNo ?? $locked->batch_no,
                'reference' => $note,
                'user_id' => $by?->id,
                'note' => $note,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $locked;
        });
    }
}