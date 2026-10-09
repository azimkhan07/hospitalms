<?php

namespace App\Http\Livewire\Admins;

use App\Models\DoctorAlert;
use App\Models\DrugChart;
use App\Models\DrugChartAdministration;
use App\Models\stay;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The nurse's drug chart (PLAN.md Phase 6): one line per ordered medicine on
 * an admitted stay, every dose logged as given / skipped / refused at the
 * bedside, and a one-tap escalation when the doctor needs to know.
 */
#[Layout('admins.layouts.app')]
class NurseDrugCharts extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $stay_id = '';

    public string $medicine = '';

    public string $dosage = '';

    public string $frequency = '';

    public string $route = '';

    public $duration_days = '';

    public string $start_date = '';

    public string $notes = '';

    public string $note = '';

    public string $alert_category = 'nursing';

    public string $alert_message = '';

    public bool $alert_urgent = false;

    public function mount(): void
    {
        $this->start_date = today()->toDateString();
    }

    /** A new chart line for the selected stay, ordered under this login. */
    public function addChart(): void
    {
        $this->validate([
            'stay_id' => 'required|integer|exists:stays,id',
            'medicine' => 'required|string|max:200',
            'dosage' => 'nullable|string|max:100',
            'frequency' => 'nullable|string|max:100',
            'route' => 'nullable|string|max:100',
            'duration_days' => 'nullable|integer|min:1|max:365',
            'start_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ], [
            'medicine.required' => 'Enter the medicine name.',
            'stay_id.required' => 'Pick the stay first.',
        ]);

        $stay = stay::findOrFail((int) $this->stay_id);

        DrugChart::create([
            'stay_id' => $stay->id,
            'patient_id' => $stay->patient_id,
            'medicine' => $this->medicine,
            'dosage' => $this->dosage ?: null,
            'frequency' => $this->frequency ?: null,
            'route' => $this->route ?: null,
            'duration_days' => $this->duration_days !== '' ? (int) $this->duration_days : null,
            'start_date' => $this->start_date ?: today(),
            'notes' => $this->notes ?: null,
            'ordered_by' => auth()->id(),
            'status' => 'active',
        ]);

        $this->reset(['medicine', 'dosage', 'frequency', 'route', 'duration_days', 'notes']);
        $this->start_date = today()->toDateString();
        session()->flash('message', 'Drug chart line added.');
    }

    public function completeChart(int $id): void
    {
        DrugChart::findOrFail($id)->update(['status' => 'completed']);
        session()->flash('message', 'Chart line marked completed.');
    }

    public function cancelChart(int $id): void
    {
        DrugChart::findOrFail($id)->update(['status' => 'cancelled']);
        session()->flash('message', 'Chart line cancelled.');
    }

    /**
     * Record one administration against a chart line. The bedside note in
     * $this->note travels with the row (e.g. "given after food").
     */
    public function administer($chartId, $state): void
    {
        if (! in_array($state, ['given', 'skipped', 'refused'], true)) {
            session()->flash('error', 'Unknown administration state.');

            return;
        }

        $chart = DrugChart::findOrFail((int) $chartId);

        DrugChartAdministration::create([
            'drug_chart_id' => $chart->id,
            'scheduled_time' => now(),
            'given_at' => now(),
            'given_by' => auth()->id(),
            'state' => $state,
            'note' => $this->note ?: null,
        ]);

        $this->note = '';
        session()->flash('message', 'Administration recorded: '.$state.'.');
    }

    /** Escalate the selected stay's patient to every doctor on the facility. */
    public function raiseAlert(): void
    {
        $this->validate([
            'alert_category' => 'required|in:'.implode(',', DoctorAlert::CATEGORIES),
            'alert_message' => 'required|string|max:2000',
            'alert_urgent' => 'boolean',
        ], [
            'alert_message.required' => 'Write what the doctor needs to know.',
        ]);

        $stay = $this->stay_id ? stay::find((int) $this->stay_id) : null;

        $alert = DoctorAlert::create([
            'patient_id' => $stay?->patient_id,
            'stay_id' => $stay?->id,
            'raised_by' => auth()->id(),
            'category' => $this->alert_category,
            'message' => $this->alert_message,
            'is_urgent' => $this->alert_urgent,
        ]);

        $alert->notifyDoctors();

        $this->reset(['alert_message', 'alert_urgent']);
        $this->alert_category = 'nursing';
        session()->flash('message', 'Doctor alerted.');
    }

    public function render()
    {
        if (! hms_can('ward')) {
            abort(403);
        }

        // Same "still admitted" set as the Ward screen's active tab.
        $stays = stay::with(['patient:id,name', 'room:id,name', 'bed:id,bed_number,room_id'])
            ->where('status', 'active')
            ->orderBy('start_time')
            ->get();

        $charts = collect();

        if ($this->stay_id !== '') {
            $stay = stay::find((int) $this->stay_id);

            $charts = $stay
                ? DrugChart::with(['administrations.giver:id,name', 'orderer:id,name'])
                    ->where('stay_id', $stay->id)
                    ->orderByDesc('id')
                    ->get()
                : collect();
        }

        return view('livewire.admins.nurse-drug-charts', [
            'stays' => $stays,
            'charts' => $charts,
            'categories' => DoctorAlert::CATEGORIES,
            'recentAlerts' => DoctorAlert::unresolved()
                ->with('patient:id,name')
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
