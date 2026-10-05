<?php

namespace App\Http\Livewire\Admins;

use App\Models\beds;
use App\Models\Concerns\BelongsToTenant;
use App\Models\InvestigationReport;
use App\Models\InvestigationTest;
use App\Models\Machine;
use App\Models\rooms;
use App\Services\InvestigationCharge;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('admins.layouts.app')]

/**
 * Everything recorded against one bed or one room (PLAN.md 9d.3).
 *
 * A doctor picks a bed number from the live bed map and gets the whole picture
 * in one place: the investigations with their calculated charges, the machines
 * used there, and the bill. The same view is the printable sheet.
 */
class BedReports extends Component
{
    public string $placement = 'bed';

    public string $bedId = '';

    public string $roomId = '';

    public string $search = '';

    /** A nurse or doctor may record a machine in the ward; nobody else may. */
    public bool $showMachineForm = false;

    public string $machineName = '';

    public string $machineModality = '';

    public string $machineLocation = '';

    public ?int $machineBedId = null;

    public ?int $machineRoomId = null;

    /** Recording an investigation is the doctor's job; the Dean owns the card. */
    public bool $showReportForm = false;

    public string $reportTestId = '';

    public int $reportUnits = 1;

    public bool $reportUrgent = false;

    public string $reportDiscount = '';

    public string $reportTaxPercent = '';

    public string $reportMachineId = '';

    public string $reportFindings = '';

    public string $reportResult = '';

    /** Live preview of what the patient will be charged, from the rate card. */
    public function updatedReportTestId(): void
    {
        $this->reportUnits = $this->reportUnitsForTest();
    }

    public function updatedReportMachineId(): void
    {
        //
    }

    public function pickBed(string $id): void
    {
        $this->bedId = $id;
        $this->roomId = '';
        $this->placement = 'bed';

        // Keep the ward machine form pointed at the bed that is on screen. The
        // form used to read this off a hidden input, but wire:model never syncs
        // a hidden field (it only listens for user events), so the machine was
        // saved with no bed at all.
        $this->machineBedId = (int) $id;
        $this->machineRoomId = null;
    }

    public function pickRoom(string $id): void
    {
        $this->roomId = $id;
        $this->bedId = '';
        $this->placement = 'room';
        $this->machineRoomId = (int) $id;
        $this->machineBedId = null;
    }

    public function clearSelection(): void
    {
        $this->bedId = '';
        $this->roomId = '';
        $this->machineBedId = null;
        $this->machineRoomId = null;
    }

    /**
     * In the ICU a machine is added by whoever is standing at the bedside, so
     * the nurse and the doctor may do it without waiting for the Dean's setup
     * round. Only a working machine can be added.
     */
    public function createMachine(): void
    {
        abort_unless($this->canAddWardMachine(), 403);

        $this->validate([
            'machineName' => ['required', 'string', 'max:150'],
            'machineModality' => ['nullable', 'string', 'max:80'],
            'machineLocation' => ['nullable', 'string', 'max:150'],
            'machineBedId' => ['nullable', 'integer'],
            'machineRoomId' => ['nullable', 'integer'],
        ]);

        Machine::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $this->machineName,
            'modality' => $this->machineModality ?: null,
            'location' => $this->machineLocation ?: null,
            'status' => 'working',
            'rate' => 0,
            'bed_id' => $this->machineBedId ?: null,
            'room_id' => $this->machineRoomId ?: null,
        ]);

        session()->flash('message', 'Machine "'.$this->machineName.'" recorded in the ward.');

        $this->machineName = '';
        $this->machineModality = '';
        $this->machineLocation = '';
        $this->showMachineForm = false;
    }

    /**
     * A doctor records what was done at the bedside; the charge is worked out by
     * InvestigationCharge and frozen onto the row, never typed in (PLAN.md 9d.2).
     */
    public function recordInvestigation(): void
    {
        abort_unless($this->canRecordInvestigation(), 403);

        $this->validate([
            'reportTestId' => ['required', 'integer'],
            'reportUnits' => ['required', 'integer', 'min:1', 'max:999'],
            'reportDiscount' => ['nullable', 'numeric', 'min:0'],
            'reportTaxPercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reportMachineId' => ['nullable', 'integer'],
            'reportFindings' => ['nullable', 'string', 'max:2000'],
            'reportResult' => ['nullable', 'string', 'max:2000'],
        ]);

        $test = InvestigationTest::findOrFail((int) $this->reportTestId);
        $machineId = $this->reportMachineId !== '' ? (int) $this->reportMachineId : null;
        $bed = $this->bedId !== '' ? beds::find($this->bedId) : null;
        $room = $this->roomId !== '' ? rooms::find($this->roomId) : null;

        // The machine the test ran on must be one this facility owns, and the
        // machine_rate tests are priced off it.
        if ($machineId && ! Machine::whereKey($machineId)->exists()) {
            throw ValidationException::withMessages([
                'reportMachineId' => 'That machine is not in this facility.',
            ]);
        }

        $report = InvestigationReport::create([
            'tenant_id' => auth()->user()->tenant_id,
            'investigation_test_id' => $test->id,
            'machine_id' => $machineId ?: $test->machine_id,
            'patient_id' => $bed?->patient_id,
            'room_id' => $room?->id,
            'bed_id' => $bed?->id,
            'units' => $this->reportUnitsForTest(),
            'is_urgent' => $this->reportUrgent,
            'discount' => (float) ($this->reportDiscount ?: 0),
            'tax_percent' => (float) ($this->reportTaxPercent ?: 0),
            'findings' => $this->reportFindings ?: null,
            'result' => $this->reportResult ?: null,
            'status' => 'reported',
            'reported_at' => now(),
        ]);

        $report->recalculate();

        session()->flash('message', $test->name.' recorded. Charge '.number_format((float) $report->charge, 2).'.');

        $this->resetReportForm();
    }

    protected function resetReportForm(): void
    {
        $this->showReportForm = false;
        $this->reportTestId = '';
        $this->reportUnits = 1;
        $this->reportUrgent = false;
        $this->reportDiscount = '';
        $this->reportTaxPercent = '';
        $this->reportMachineId = '';
        $this->reportFindings = '';
        $this->reportResult = '';
    }

    /**
     * Machines are added in the ICU by the nurse or doctor standing there, or by
     * the Dean who owns the setup. The admin reads the ward and stays out of it.
     */
    protected function canAddWardMachine(): bool
    {
        if (hms_can('machines.manage')) {
            return true;
        }

        return auth()->user()?->hasRole('doctor', 'nurse') ?? false;
    }

    /** The doctor records it; the Dean may too, since they own the rate card. */
    protected function canRecordInvestigation(): bool
    {
        if (hms_can('investigations.manage')) {
            return true;
        }

        return auth()->user()?->hasRole('doctor') ?? false;
    }

    /** Units are capped by the rate card so a typo cannot bill 999 slides. */
    protected function reportUnitsForTest(): int
    {
        $test = $this->selectedTest();

        if (! $test) {
            return 1;
        }

        return max(1, min($this->reportUnits ?: 1, (int) $test->max_units));
    }

    protected function selectedTest(): ?InvestigationTest
    {
        if ($this->reportTestId === '') {
            return null;
        }

        return InvestigationTest::with('machine')
            ->whereKey((int) $this->reportTestId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * May this user open the ward report at all?
     *
     * Read access belongs to every clinical role that works in the ward (doctor,
     * nurse, Dean) plus the admin, who reads it (PLAN.md 9d.3). Asking whether
     * the tenant ticks 'doctor' instead would refuse a nurse at a facility that
     * has not ticked that role yet.
     */
    protected function inWard(): bool
    {
        return hms_can('bedreports');
    }

    public function render()
    {
        abort_unless(hms_can('bedreports'), 403);

        $bed = $this->bedId !== '' ? beds::find($this->bedId) : null;
        $room = $this->roomId !== '' ? rooms::find($this->roomId) : null;

        $query = InvestigationReport::query()
            ->with(['test:id,name,code,calc_type', 'machine:id,name,modality', 'bed:id,bed_number', 'room:id,name'])
            ->when($bed, fn ($q) => $q->where('bed_id', $bed->id))
            ->when($room, fn ($q) => $q->where('room_id', $room->id))
            ->when(! $bed && ! $room, fn ($q) => $q->whereRaw('1=0'))
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(findings) LIKE ?', [$term])
                        ->orWhereHas('test', fn ($t) => $t->whereRaw('LOWER(name) LIKE ?', [$term]));
                });
            })
            ->orderByDesc('created_at');

        $reports = $query->get();

        $machines = Machine::query()
            ->with(['room:id,name', 'bed:id,bed_number'])
            ->when($bed, fn ($q) => $q->where('bed_id', $bed->id))
            ->when($room, fn ($q) => $q->where('room_id', $room->id))
            ->when(! $bed && ! $room, fn ($q) => $q->whereRaw('1=0'))
            ->orderBy('name')
            ->get();

        $selectedTest = $this->selectedTest();

        // What the patient will pay for the line being typed, worked out by the
        // same service the report will be saved with.
        $preview = null;

        if ($selectedTest) {
            $options = [
                'units' => $this->reportUnitsForTest(),
                'is_urgent' => $this->reportUrgent,
                'discount' => (float) ($this->reportDiscount ?: 0),
                'tax_percent' => (float) ($this->reportTaxPercent ?: 0),
            ];

            $charge = InvestigationCharge::for($selectedTest, $options);

            $preview = [
                'formula' => $charge->formula(),
                'total' => number_format($charge->total(), 2),
                'max_units' => (int) $selectedTest->max_units,
                'calc_type' => $selectedTest->calc_type,
            ];
        }

        return view('livewire.admins.bed-reports', [
            'bed' => $bed,
            'room' => $room,
            'reports' => $reports,
            'machines' => $machines,
            'beds' => beds::with('room:id,name')->orderBy('bed_number')->get(['id', 'room_id', 'bed_number', 'status', 'patient_id']),
            'rooms' => rooms::orderBy('name')->get(['id', 'name', 'status']),
            'total' => $reports->sum(fn ($r) => (float) $r->charge),
            'canAddMachine' => $this->canAddWardMachine(),
            'canRecordInvestigation' => $this->canRecordInvestigation(),
            'tests' => InvestigationTest::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'machine_id', 'max_units', 'calc_type']),
            'wardMachines' => Machine::query()
                ->where('status', 'working')
                ->orderBy('name')
                ->get(['id', 'name', 'modality']),
            'selectedTest' => $selectedTest,
            'preview' => $preview,
            'patient' => $bed?->patient,
        ]);
    }
}