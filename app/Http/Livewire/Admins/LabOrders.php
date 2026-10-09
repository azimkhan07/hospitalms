<?php

namespace App\Http\Livewire\Admins;

use App\Models\InvestigationReport;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The laboratory queue (PLAN.md 9d): tests ordered by a doctor arrive here as
 * pending, the laboratorist starts and reports them, and the reported rows
 * feed back into the consultation chart. BedReports keeps doing the ICU
 * charge-recording side on the same table.
 */
#[Layout('admins.layouts.app')]
class LabOrders extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $statusFilter = 'pending';

    #[Url]
    public string $search = '';

    public ?int $reportId = null;

    public string $reportFindings = '';

    public string $reportResult = '';

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function start(int $id): void
    {
        $report = $this->queue()->findOrFail($id);

        if ($report->status !== 'pending') {
            session()->flash('error', 'That order already moved on.');

            return;
        }

        $report->update(['status' => 'in_progress']);
        session()->flash('message', 'Work started on '.$report->test?->name.'.');
    }

    public function openReport(int $id): void
    {
        $report = $this->queue()->findOrFail($id);

        $this->reportId = $report->id;
        $this->reportFindings = (string) $report->findings;
        $this->reportResult = is_string($report->result) ? $report->result : '';
        $this->resetValidation();
    }

    public function closeReport(): void
    {
        $this->reportId = null;
    }

    public function saveResult(): void
    {
        $report = $this->queue()->findOrFail($this->reportId ?? 0);

        $this->validate([
            'reportFindings' => 'nullable|string|max:2000',
            'reportResult' => 'nullable|string|max:2000',
        ]);

        if (trim($this->reportFindings) === '' && trim($this->reportResult) === '') {
            $this->addError('reportFindings', 'Enter the findings or the result values.');

            return;
        }

        $report->update([
            'findings' => $this->reportFindings ?: null,
            'result' => $this->reportResult ?: null,
            'status' => 'reported',
            'reported_by' => auth()->id(),
            'reported_at' => now(),
        ]);

        $this->reportId = null;
        $this->reportFindings = '';
        $this->reportResult = '';
        session()->flash('message', ($report->test?->name ?: 'Test').' reported.');
    }

    public function cancel(int $id): void
    {
        $report = $this->queue()->findOrFail($id);

        if ($report->status === 'reported') {
            session()->flash('error', 'A reported result cannot be cancelled.');

            return;
        }

        $report->update(['status' => 'cancelled']);
        session()->flash('message', 'Order cancelled.');
    }

    private function queue()
    {
        return InvestigationReport::query();
    }

    public function render()
    {
        if (! hms_can('lab')) {
            abort(403);
        }

        $reports = $this->queue()
            ->with([
                'test:id,name,code',
                'patient:id,name,age,gender,bloodgroup',
                'appointment:id,patient_id,intime,status',
                'orderedBy:id,name',
                'reportedBy:id,name',
            ])
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->whereHas('patient', fn ($p) => $p->whereRaw('LOWER(name) LIKE ?', [$term]));
            })
            ->orderByDesc('is_urgent')
            ->orderByDesc('id')
            ->paginate(12);

        return view('livewire.admins.lab-orders', [
            'reports' => $reports,
            'counts' => [
                'pending' => InvestigationReport::where('status', 'pending')->count(),
                'in_progress' => InvestigationReport::where('status', 'in_progress')->count(),
                'reported' => InvestigationReport::where('status', 'reported')->count(),
            ],
        ]);
    }
}
