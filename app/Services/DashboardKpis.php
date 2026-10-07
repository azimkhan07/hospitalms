<?php

namespace App\Services;

use App\Models\appointment;
use App\Models\beds;
use App\Models\bill;
use App\Models\birthreport;
use App\Models\block;
use App\Models\department;
use App\Models\employee;
use App\Models\hod;
use App\Models\LeaveRequest;
use App\Models\medicine;
use App\Models\Meeting;
use App\Models\operationreport;
use App\Models\patient;
use App\Models\Prescription;
use App\Models\requestedAppointment;
use App\Models\rooms;
use App\Models\subscriber;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Per-role KPI cards for the admin dashboard.
 *
 * One role-agnostic query set (`values()`), each role picks the cards that
 * matter to it. Livewire and the API v1 dashboard both render from here so the
 * two surfaces never drift.
 */
class DashboardKpis
{
    public static function cards(?User $user = null): array
    {
        $user ??= Auth::user();

        if (! $user) {
            return [];
        }

        $values = static::values();

        $currency = fn (float $n): string => '₹'.number_format($n, 2);

        $cards = [
            'admin' => [
                ['label' => 'Active Staff', 'value' => $values['staff'], 'icon' => 'fa-user-friends', 'color' => '#0f7fd4'],
                ['label' => 'Patients', 'value' => $values['patients'], 'icon' => 'fa-procedures', 'color' => '#0b3c66'],
                ['label' => 'Appointments Today', 'value' => $values['appointments_today'], 'icon' => 'fa-calendar-check', 'color' => '#1a9d6b'],
                ['label' => 'Collected Today', 'value' => $currency($values['collected_today']), 'icon' => 'fa-money-bill-wave', 'color' => '#149d46'],
                ['label' => 'Pending Leave', 'value' => $values['pending_leave'], 'icon' => 'fa-plane-departure', 'color' => '#e67e22'],
                ['label' => 'Expired Medicines', 'value' => $values['expired_medicine'], 'icon' => 'fa-exclamation-triangle', 'color' => '#d43f3a'],
            ],
            'moderator' => [
                ['label' => 'Active Staff', 'value' => $values['staff'], 'icon' => 'fa-user-friends', 'color' => '#0f7fd4'],
                ['label' => 'Patients', 'value' => $values['patients'], 'icon' => 'fa-procedures', 'color' => '#0b3c66'],
                ['label' => 'Appointments Today', 'value' => $values['appointments_today'], 'icon' => 'fa-calendar-check', 'color' => '#1a9d6b'],
                ['label' => 'Upcoming Meetings', 'value' => $values['upcoming_meetings'], 'icon' => 'fa-calendar-alt', 'color' => '#8e44ad'],
                ['label' => 'Pending Leave', 'value' => $values['pending_leave'], 'icon' => 'fa-plane-departure', 'color' => '#e67e22'],
                ['label' => 'Alloted Beds', 'value' => $values['alloted_beds'], 'icon' => 'fa-bed', 'color' => '#2c3e50'],
            ],
            'doctor' => [
                ['label' => 'Patients', 'value' => $values['patients'], 'icon' => 'fa-procedures', 'color' => '#0b3c66'],
                ['label' => 'Appointments Today', 'value' => $values['appointments_today'], 'icon' => 'fa-calendar-check', 'color' => '#0f7fd4'],
                ['label' => 'Operation Reports', 'value' => $values['operations'], 'icon' => 'fa-user-md', 'color' => '#8e44ad'],
                ['label' => 'Birth Reports', 'value' => $values['births'], 'icon' => 'fa-baby', 'color' => '#e67e22'],
                ['label' => 'My Approved Leave', 'value' => $values['my_approved_leave'], 'icon' => 'fa-plane-departure', 'color' => '#1a9d6b'],
            ],
            'nurse' => [
                ['label' => 'Alloted Beds', 'value' => $values['alloted_beds'], 'icon' => 'fa-bed', 'color' => '#0f7fd4'],
                ['label' => 'Available Beds', 'value' => $values['available_beds'], 'icon' => 'fa-bed', 'color' => '#1a9d6b'],
                ['label' => 'Rooms', 'value' => $values['rooms'], 'icon' => 'fa-door-open', 'color' => '#0b3c66'],
                ['label' => 'Patients', 'value' => $values['patients'], 'icon' => 'fa-procedures', 'color' => '#8e44ad'],
            ],
            'receptionist' => [
                ['label' => 'Appointments Today', 'value' => $values['appointments_today'], 'icon' => 'fa-calendar-check', 'color' => '#0f7fd4'],
                ['label' => 'Requested Appointments', 'value' => $values['requested'], 'icon' => 'fa-calendar-plus', 'color' => '#e67e22'],
                ['label' => 'Patients', 'value' => $values['patients'], 'icon' => 'fa-procedures', 'color' => '#0b3c66'],
                ['label' => 'Available Beds', 'value' => $values['available_beds'], 'icon' => 'fa-bed', 'color' => '#1a9d6b'],
                ['label' => 'Subscribers', 'value' => $values['subscribers'], 'icon' => 'fa-user-plus', 'color' => '#8e44ad'],
            ],
            'pharmacist' => [
                ['label' => 'Medicines', 'value' => $values['medicines'], 'icon' => 'fa-capsules', 'color' => '#0f7fd4'],
                ['label' => 'Low Stock', 'value' => $values['low_stock'], 'icon' => 'fa-exclamation-triangle', 'color' => '#e67e22'],
                ['label' => 'Expired Medicines', 'value' => $values['expired_medicine'], 'icon' => 'fa-calendar-times', 'color' => '#d43f3a'],
                ['label' => 'Prescriptions', 'value' => $values['prescriptions'], 'icon' => 'fa-prescription-bottle', 'color' => '#0b3c66'],
            ],
            'laboratorist' => [
                ['label' => 'Patients', 'value' => $values['patients'], 'icon' => 'fa-procedures', 'color' => '#0b3c66'],
                ['label' => 'Appointments Today', 'value' => $values['appointments_today'], 'icon' => 'fa-calendar-check', 'color' => '#0f7fd4'],
                ['label' => 'Operation Reports', 'value' => $values['operations'], 'icon' => 'fa-user-md', 'color' => '#8e44ad'],
                ['label' => 'Birth Reports', 'value' => $values['births'], 'icon' => 'fa-baby', 'color' => '#e67e22'],
                ['label' => 'Pending Leave', 'value' => $values['pending_leave'], 'icon' => 'fa-plane-departure', 'color' => '#1a9d6b'],
            ],
            'accountant' => [
                ['label' => 'Collected Today', 'value' => $currency($values['collected_today']), 'icon' => 'fa-money-bill-wave', 'color' => '#149d46'],
                ['label' => 'Outstanding', 'value' => $currency($values['outstanding']), 'icon' => 'fa-hourglass-half', 'color' => '#e67e22'],
                ['label' => 'Salaries Due', 'value' => $currency($values['salaries_due']), 'icon' => 'fa-user-tie', 'color' => '#d43f3a'],
                ['label' => 'Bills', 'value' => $values['bills'], 'icon' => 'fa-file-invoice', 'color' => '#0b3c66'],
                ['label' => 'Patients', 'value' => $values['patients'], 'icon' => 'fa-procedures', 'color' => '#0f7fd4'],
            ],
            'storekeeper' => [
                ['label' => 'Medicines', 'value' => $values['medicines'], 'icon' => 'fa-capsules', 'color' => '#0f7fd4'],
                ['label' => 'Low Stock', 'value' => $values['low_stock'], 'icon' => 'fa-exclamation-triangle', 'color' => '#e67e22'],
                ['label' => 'Expired Medicines', 'value' => $values['expired_medicine'], 'icon' => 'fa-calendar-times', 'color' => '#d43f3a'],
                ['label' => 'Blocks', 'value' => $values['blocks'], 'icon' => 'fa-cube', 'color' => '#0b3c66'],
            ],
            'hr' => [
                ['label' => 'Employees', 'value' => $values['employees'], 'icon' => 'fa-users', 'color' => '#0f7fd4'],
                ['label' => 'Active Staff', 'value' => $values['staff'], 'icon' => 'fa-user-friends', 'color' => '#1a9d6b'],
                ['label' => 'Departments', 'value' => $values['departments'], 'icon' => 'fa-building', 'color' => '#0b3c66'],
                ['label' => 'HODs', 'value' => $values['hods'], 'icon' => 'fa-user-tie', 'color' => '#8e44ad'],
                ['label' => 'Pending Leave', 'value' => $values['pending_leave'], 'icon' => 'fa-plane-departure', 'color' => '#e67e22'],
            ],
        ];

        return $cards[$user->roleSlug()] ?? $cards['admin'];
    }

    /**
     * Single-pass computed KPI values, only evaluated once per request.
     */
    public static function values(): array
    {
        $today = now()->startOfDay();

        return [
            'staff' => static::once('staff', fn () => User::where('is_active', true)->count()),
            'patients' => static::once('patients', fn () => patient::count()),
            'appointments_today' => static::once('appointments_today', fn () => appointment::whereDate('intime', $today)->count()),
            'requested' => static::once('requested', fn () => requestedAppointment::count()),
            'collected_today' => static::once('collected_today', fn () => Accounting::collectedToday()),
            'outstanding' => static::once('outstanding', fn () => Accounting::outstanding()),
            'salaries_due' => static::once('salaries_due', fn () => Accounting::salariesDue()),
            'pending_leave' => static::once('pending_leave', fn () => LeaveRequest::where('status', 'pending')->count()),
            'my_approved_leave' => static::once('my_approved_leave', fn () => LeaveRequest::where('user_id', Auth::id())->where('status', 'approved')->count()),
            'upcoming_meetings' => static::once('upcoming_meetings', fn () => Meeting::upcoming()->count()),
            'expired_medicine' => static::once('expired_medicine', fn () => medicine::whereNull('deleted_at')->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', $today)->count()),
            'low_stock' => static::once('low_stock', fn () => medicine::where(function ($q) {
                $q->where('stock', '<=', 0)->orWhereColumn('stock', '<', 'reorder_level');
            })->count()),
            'alloted_beds' => static::once('alloted_beds', fn () => beds::where('status', 'alloted')->count()),
            'available_beds' => static::once('available_beds', fn () => beds::where('status', 'available')->count()),
            'rooms' => static::once('rooms', fn () => rooms::count()),
            'employees' => static::once('employees', fn () => employee::count()),
            'departments' => static::once('departments', fn () => department::count()),
            'hods' => static::once('hods', fn () => hod::count()),
            'blocks' => static::once('blocks', fn () => block::count()),
            'subscribers' => static::once('subscribers', fn () => subscriber::count()),
            'operations' => static::once('operations', fn () => operationreport::count()),
            'births' => static::once('births', fn () => birthreport::count()),
            'bills' => static::once('bills', fn () => bill::count()),
            'medicines' => static::once('medicines', fn () => medicine::count()),
            'prescriptions' => static::once('prescriptions', fn () => Prescription::count()),
        ];
    }

    private static array $resolved = [];

    private static function once(string $key, callable $resolver): mixed
    {
        if (! array_key_exists($key, static::$resolved)) {
            static::$resolved[$key] = $resolver();
        }

        return static::$resolved[$key];
    }
}