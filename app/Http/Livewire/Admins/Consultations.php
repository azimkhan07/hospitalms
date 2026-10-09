<?php

namespace App\Http\Livewire\Admins;

use App\Models\appointment;
use App\Models\InvestigationReport;
use App\Models\InvestigationTest;
use App\Models\Vital;
use App\Models\doctor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The doctor's OPD desk (PLAN.md section 6). A doctor only ever sees the
 * appointments whose doctor profile is linked to this login, walks them
 * through waiting -> in consult -> treated, records the complaint and
 * diagnosis, orders lab tests and hands over to prescriptions.
 */
#[Layout('admins.layouts.app')]
class Consultations extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $search = '';

    #[Url]
    public string $tab = 'today';

    public ?int $openId = null;

    public string $chiefComplaint = '';

    public string $diagnosis = '';

    public string $followUpAt = '';

    public array $orderTests = [];

    public string $orderPriority = 'routine';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTab(): void
    {
        $this->resetPage();
        $this->openId = null;
    }

    public function start(int $id): void
    {
        $appt = $this->myAppointments()->findOrFail($id);

        if (! in_array($appt->status, ['pending', 'confirmed', 'waiting'], true)) {
            session()->flash('error', 'That appointment is not waiting.');

            return;
        }

        $appt->update(['status' => 'in_consult']);

        session()->flash('message', 'Consultation started for '.$appt->patient?->name.'.');
    }

    public function complete(int $id): void
    {
        $appt = $this->myAppointments()->findOrFail($id);

        $appt->update([
            'status' => 'completed',
            'outtime' => $appt->outtime ?? now(),
        ]);

        session()->flash('message', 'Treated: '.$appt->patient?->name.'.');
    }

    public function open(int $id): void
    {
        $appt = $this->myAppointments()->findOrFail($id);

        $this->openId = $appt->id;
        $this->chiefComplaint = (string) $appt->chief_complaint;
        $this->diagnosis = (string) $appt->diagnosis;
        $this->followUpAt = optional($appt->follow_up_at)->format('Y-m-d') ?? '';
        $this->orderTests = [];
        $this->orderPriority = 'routine';
        $this->resetValidation();
    }

    public function close(): void
    {
        $this->openId = null;
    }

    public function saveConsult(): void
    {
        $appt = $this->myAppointments()->findOrFail($this->openId ?? 0);

        $this->validate([
            'chiefComplaint' => 'nullable|string|max:2000',
            'diagnosis' => 'nullable|string|max:2000',
            'followUpAt' => 'nullable|date',
        ]);

        $appt->update([
            'chief_complaint' => $this->chiefComplaint ?: null,
            'diagnosis' => $this->diagnosis ?: null,
            'follow_up_at' => $this->followUpAt ?: null,
        ]);

        // Opening the chart starts the consult if it was not started already.
        if (in_array($appt->status, ['pending', 'confirmed', 'waiting'], true)) {
            $appt->update(['status' => 'in_consult']);
        }

        session()->flash('message', 'Consultation saved.');
    }

    public function orderInvestigations(): void
    {
        $appt = $this->myAppointments()->findOrFail($this->openId ?? 0);

        $this->validate([
            'orderTests' => 'required|array|min:1',
            'orderTests.*' => 'integer|exists:investigation_tests,id',
            'orderPriority' => 'required|in:routine,stat',
        ]);

        foreach (array_unique($this->orderTests) as $testId) {
            $test = InvestigationTest::where('is_active', true)->find($testId);

            if (! $test) {
                continue;
            }

            $report = InvestigationReport::create([
                'investigation_test_id' => $test->id,
                'patient_id' => $appt->patient_id,
                'appointment_id' => $appt->id,
                'ordered_by' => auth()->id(),
                'priority' => $this->orderPriority,
                'is_urgent' => $this->orderPriority === 'stat',
                'status' => 'pending',
            ]);

            $report->recalculate();
        }

        $this->orderTests = [];
        $this->orderPriority = 'routine';
        session()->flash('message', 'Lab order sent.');
    }

    public function prescribe(int $id): void
    {
        $appt = $this->myAppointments()->findOrFail($id);

        $this->redirect(route('admin_prescriptions', ['appointment' => $appt->id]));
    }

    public function goVitals(int $id): void
    {
        $appt = $this->myAppointments()->findOrFail($id);

        $this->redirect(route('admin_vitals', ['appointment' => $appt->id]));
    }

    /** Every appointment query in this screen goes through the doctor scope. */
    private function myAppointments()
    {
        return appointment::query()->ownedBy((int) auth()->id());
    }

    private function myDoctor(): ?doctor
    {
        return doctor::where('user_id', auth()->id())->first();
    }

    public function render()
    {
        if (! hms_can('appointments') || ! auth()->user()?->hasRole('doctor')) {
            abort(403);
        }

        $doctorProfile = $this->myDoctor();

        $appointments = $this->myAppointments()
            ->with(['patient:id,name,age,gender,bloodgroup,phone', 'doctor.employ:id,name'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->whereHas('patient', fn ($p) => $p->whereRaw('LOWER(name) LIKE ?', [$term]));
            })
            ->when($this->tab === 'today', function ($q) {
                $q->where(function ($q) {
                    $q->whereDate('intime', today())
                        ->orWhereIn('status', ['waiting', 'in_consult']);
                })
                    ->whereNotIn('status', ['cancelled', 'terminated', 'completed'])
                    ->orderBy('intime');
            })
            ->when($this->tab === 'treated', function ($q) {
                $q->where('status', 'completed')
                    ->orderByDesc('outtime');
            })
            ->when($this->tab === 'all', function ($q) {
                $q->orderByDesc('intime');
            })
            ->paginate(12);

        $open = $this->openId
            ? $this->myAppointments()->with(['patient:id,name,age,gender,bloodgroup,phone,address', 'prescriptions.items'])->find($this->openId)
            : null;

        // Prefer the observation taken against this visit; fall back to the
        // patient's most recent set (e.g. captured at reception check-in).
        $latestVital = null;

        if ($open) {
            $latestVital = Vital::where('appointment_id', $open->id)
                ->latest('taken_at')
                ->first()
                ?? Vital::where('patient_id', $open->patient_id)
                    ->latest('taken_at')
                    ->first();
        }

        $appointmentReports = $open
            ? InvestigationReport::with(['test:id,name', 'patient:id,name'])
                ->where('appointment_id', $open->id)
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('livewire.admins.consultations', [
            'doctorProfile' => $doctorProfile,
            'appointments' => $appointments,
            'open' => $open,
            'latestVital' => $latestVital,
            'appointmentReports' => $appointmentReports,
            'availableTests' => InvestigationTest::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'todayCount' => $this->myAppointments()
                ->whereDate('intime', today())
                ->whereNotIn('status', ['cancelled', 'terminated', 'completed'])
                ->count(),
            'waitingCount' => $this->myAppointments()->whereIn('status', ['waiting', 'in_consult'])->count(),
            'treatedToday' => $this->myAppointments()
                ->where('status', 'completed')
                ->whereDate('outtime', today())
                ->count(),
        ]);
    }
}
