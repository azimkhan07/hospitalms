<?php

namespace App\Services;

use App\Models\AccountingVoucher;
use App\Models\appointment;
use App\Models\beds;
use App\Models\bill;
use App\Models\InvestigationTest;
use App\Models\Machine;
use App\Models\medicine;
use App\Models\patient;
use App\Models\payment;
use App\Models\Prescription;
use App\Models\stay;
use Illuminate\Support\Carbon;

/**
 * Phase 10 - the reports hub.
 *
 * One aggregate snapshot of the facility (patients, OPD/IPD census, beds,
 * revenue, pharmacy, diagnostics, doctor performance and the due list) bound
 * to an optional date range. The admin web page, the CSV export and the
 * mobile API all render this same array, so numbers never drift between the
 * three surfaces.
 *
 * Every query is tenant-scoped by the BelongsToTenant global scope, so two
 * facilities on the same database can never leak into each other's reports.
 */
class ReportBuilder
{
    public static function aggregate(?string $from = null, ?string $to = null): array
    {
        [$start, $end] = self::bounds($from, $to);

        $inRange = fn ($query, string $column = 'created_at') => $query
            ->whereBetween($column, [$start, $end]);

$billed = (float) $inRange(bill::query())->sum('amount');
        $tax = (float) $inRange(bill::query())->sum('tax');
        $collected = (float) $inRange(payment::query()->where('status', 'paid'))
            ->sum('amount');
        $outstanding = 0.0;

        foreach (bill::where('status', 'unpaid')->get() as $b) {
            $outstanding += (float) $b->amountDue();
        }

        $active = null; // placeholder (status enum drives admitted/discharged below)
        $patients = [
            'total' => patient::count(),
            'new' => $inRange(patient::query())->count(),
            'male' => patient::where('gender', 'male')->count(),
            'female' => patient::where('gender', 'female')->count(),
            'admitted' => patient::where('status', 'admitted')->count(),
            'discharged' => patient::where('status', 'discharged')->count(),
            'pending' => patient::where('status', 'pending')->count(),
        ];

        $bedRows = beds::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $bedCensus = [
            'total' => beds::count(),
            'alloted' => (int) ($bedRows['alloted'] ?? 0),
            'available' => (int) ($bedRows['available'] ?? 0),
            'cleaning' => (int) ($bedRows['cleaning'] ?? 0),
            'reserved' => (int) ($bedRows['reserved'] ?? 0),
            'maintenance' => (int) ($bedRows['maintenance'] ?? 0),
        ];

        $apptStatus = $inRange(appointment::query())
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $admissions = stay::query();
        $ipd = [
            'current_stays' => stay::where('status', 'active')->count(),
            'admissions' => static::countRange($admissions, $start, $end),
        ];

        $lowStock = medicine::where(function ($q) {
            $q->where('stock', '<=', 0)
                ->orWhereColumn('stock', '<', 'reorder_level');
        })->count();
        $expiring = medicine::whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [Carbon::today(), Carbon::today()->addDays(90)])
            ->count();

        $monthly = collect(range(5, 0))->map(function ($i) {
            $monthStart = Carbon::now()->startOfMonth()->subMonthsNoOverflow($i);
            $monthEnd = $monthStart->copy()->endOfMonth();

            return [
                'key' => $monthStart->format('Y-m'),
                'label' => $monthStart->format('M'),
                'billed' => (float) bill::whereBetween('created_at', [$monthStart, $monthEnd])->sum('amount'),
                'collected' => (float) payment::where('status', 'paid')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])->sum('amount'),
            ];
        })->toArray();

        $revenue = [
            'billed' => $billed,
            'tax' => $tax,
            'collected' => $collected,
            'outstanding' => round($outstanding, 2),
            'ledger_moves' => $inRange(AccountingVoucher::query(), 'occurred_at')->count(),
            'monthly' => $monthly,
        ];

        $pharmacy = [
            'items' => medicine::count(),
            'low_stock' => $lowStock,
            'expiring_90d' => $expiring,
            'prescriptions' => $inRange(Prescription::query())->count(),
        ];

        $lab = [
            'tests' => InvestigationTest::count(),
            'machines_working' => Machine::where('status', 'working')->count(),
            'machines_total' => Machine::count(),
        ];

        $doctorPerformance = $inRange(appointment::query())
            ->where('status', '!=', 'cancelled')
            ->with('doctor.employ')
            ->get()
            ->groupBy('doctor_id')
            ->map(function ($rows, $doctorId) {
                $first = $rows->first();
                $doctor = $first?->doctor;

                return [
                    'doctor' => $doctor?->employ?->name ?? ($doctor ? 'Doctor #'.$doctor->id : 'Staff #'.$doctorId),
                    'visits' => $rows->count(),
                ];
            })
            ->sortByDesc('visits')
            ->take(6)
            ->values()
            ->all();

        $dueList = bill::with('patient:id,name,phone')
            ->where('status', 'unpaid')
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (bill $b) => [
                'id' => $b->id,
                'invoice' => $b->invoice_no ?: 'INV-'.$b->id,
                'patient' => $b->patient?->name ?? 'Patient #'.$b->patients_id,
                'phone' => $b->patient?->phone,
                'due' => round((float) $b->amountDue(), 2),
            ]);

        return [
            'range' => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            'kpIs' => [
                'patients' => $patients['total'],
                'new_patients' => $patients['new'],
                'appointments' => array_sum($apptStatus->all()),
                'confirmed_appointments' => (int) ($apptStatus['confirmed'] ?? 0) + (int) ($apptStatus['completed'] ?? 0),
                'admitted' => $patients['admitted'],
                'billed' => $billed,
                'collected' => $collected,
                'outstanding' => round($outstanding, 2),
                'low_stock' => $lowStock,
            ],
            'patients' => $patients,
            'appointments' => $apptStatus->all(),
            'ipd' => $ipd,
            'beds' => $bedCensus,
            'revenue' => $revenue,
            'pharmacy' => $pharmacy,
            'lab' => $lab,
            'doctor_performance' => $doctorPerformance,
            'due_list' => $dueList,
        ];
    }

    private static function bounds(?string $from, ?string $to): array
    {
        $from ??= 'all';

        if ($from === 'all') {
            $start = Carbon::create(2000, 1, 1);
            $end = Carbon::now()->endOfDay();

            return [$start, $end];
        }

        $start = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->startOfMonth();
        $end = $to ? Carbon::parse($to)->endOfDay()
            : ($from ? $start->copy()->endOfDay() : Carbon::now()->endOfDay());

        return [$start, $end];
    }

    private static function countRange($query, Carbon $start, Carbon $end): int
    {
        return $query->whereBetween('created_at', [$start, $end])->count();
    }
}