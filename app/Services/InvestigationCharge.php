<?php

namespace App\Services;

use App\Models\InvestigationTest;

/**
 * The one place a diagnostic charge is worked out.
 *
 * Counter staff, the rate card screen, the bed report and the printed bill all
 * call this, so they cannot disagree. Every result also carries the arithmetic
 * that produced it, because the user asked for the calculation to be visible
 * rather than a number that merely appeared (PLAN.md 9d.2).
 */
class InvestigationCharge
{
    private function __construct(
        private readonly float $gross,
        private readonly float $urgentFactor,
        private readonly float $discount,
        private readonly float $taxPercent,
        private readonly array $steps,
    ) {
    }

    /**
     * @param  array{units?:int, is_urgent?:bool, discount?:float, tax_percent?:float}  $options
     */
    public static function for(InvestigationTest $test, array $options = []): self
    {
        $units = max(1, (int) ($options['units'] ?? 1));
        $urgent = (bool) ($options['is_urgent'] ?? false);
        $discount = max(0.0, (float) ($options['discount'] ?? 0));
        $taxPercent = max(0.0, (float) ($options['tax_percent'] ?? 0));

        $base = (float) $test->base_rate;
        $perUnit = (float) $test->per_unit_rate;
        $machineRate = (float) ($test->machine?->rate ?? 0);

        $steps = [];

        switch ($test->calc_type) {
            case 'per_unit':
                $gross = $base + ($perUnit * $units);
                $steps[] = self::money($base).' + '.self::money($perUnit).' x '.$units;
                break;

            case 'machine_rate':
                // The machine owns the price; a machine with no rate falls back to
                // the test's own base rate rather than billing nothing.
                $gross = ($machineRate > 0 ? $machineRate : $base) * $units;
                $steps[] = self::money($machineRate > 0 ? $machineRate : $base).' x '.$units
                    .($machineRate > 0 ? ' (machine rate)' : ' (no machine rate, base used)');
                break;

            default: // flat
                $gross = $base;
                $steps[] = self::money($base);
        }

        $factor = 1.0;

        if ($urgent) {
            $factor = max(1.0, (float) $test->urgent_factor);
            $steps[] = 'urgent x '.$factor;
        }

        $afterUrgent = $gross * $factor;

        if ($discount > 0) {
            $steps[] = 'discount -'.self::money($discount);
        }

        $afterDiscount = max(0.0, $afterUrgent - $discount);

        if ($taxPercent > 0) {
            $steps[] = 'tax +'.$taxPercent.'%';
        }

        $tax = $afterDiscount * ($taxPercent / 100);

        return new self($afterDiscount + $tax, $factor, $discount, $taxPercent, $steps);
    }

    /** Final payable, rounded to the paisa. */
    public function total(): float
    {
        return round($this->gross, 2);
    }

    /** What the test cost before urgency, discount and tax. */
    public function subtotal(): float
    {
        $factor = $this->urgentFactor ?: 1.0;
        $gross = $this->total() / ($factor ?: 1.0);

        return round($gross, 2);
    }

    public function tax(): float
    {
        if ($this->taxPercent <= 0) {
            return 0.0;
        }

        return round($this->total() - ($this->total() / (1 + $this->taxPercent / 100)), 2);
    }

    /**
     * The arithmetic, as printed on the bill.
     *
     * Deliberately plain text: the patient reads this on paper, so it must not
     * depend on a formatter being loaded.
     */
    public function formula(): string
    {
        $formula = implode(' ', $this->steps);

        if ($this->taxPercent > 0 || $this->discount > 0) {
            $formula .= ' = '.self::money($this->total());
        }

        return mb_substr($formula, 0, 190);
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 2);
    }
}