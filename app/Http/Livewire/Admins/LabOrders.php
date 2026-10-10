<?php

namespace App\Http\Livewire\Admins;

use App\Models\DoctorAlert;
use App\Models\InvestigationReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
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
    use WithFileUploads;
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $statusFilter = 'pending';

    #[Url]
    public string $search = '';

    public ?int $reportId = null;

    public string $reportFindings = '';

    public string $reportResult = '';

    public $resultFile = null;

    public bool $isCritical = false;

    public string $criticalNote = '';

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

    /**
     * Collect the physical sample: stamp the row with a facility-scoped
     * barcode, the collector and the time. Idempotent -- re-collecting an
     * already-barcoded row keeps the original label.
     */
    public function collectSample(int $id): void
    {
        $report = $this->queue()->findOrFail($id);

        if ($report->barcode) {
            session()->flash('message', 'Sample already collected as '.$report->barcode.'.');

            return;
        }

        $barcode = $this->assignBarcode($report);

        session()->flash('message', 'Sample collected: '.$barcode.'.');
    }

    /**
     * Persist a unique barcode on the report. The generator picks the next
     * free sequence of the day and the unique index is the final guard, so a
     * concurrent collector can never collide.
     */
    private function assignBarcode(InvestigationReport $report): string
    {
        $tenantId = (int) (auth()->user()->tenant_id ?? $report->tenant_id);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $barcode = InvestigationReport::generateBarcode($tenantId);

            try {
                $report->forceFill([
                    'barcode' => $barcode,
                    'sample_collected_at' => now(),
                    'sample_collected_by' => auth()->id(),
                ])->save();

                return $barcode;
            } catch (\Illuminate\Database\QueryException $e) {
                // Unique index hit by a racing collector: try the next sequence.
                $report->barcode = null;
            }
        }

        // Last resort: timestamp entropy still yields a well-formed label.
        $barcode = 'LAB-'.$tenantId.'-'.now()->format('Ymd').'-'.now()->format('Hisv');
        $report->forceFill([
            'barcode' => $barcode,
            'sample_collected_at' => now(),
            'sample_collected_by' => auth()->id(),
        ])->save();

        return $barcode;
    }

    public function openReport(int $id): void
    {
        $report = $this->queue()->findOrFail($id);

        $this->reportId = $report->id;
        $this->reportFindings = (string) $report->findings;
        $this->reportResult = is_string($report->result) ? $report->result : '';
        $this->isCritical = (bool) $report->is_critical;
        $this->criticalNote = (string) $report->critical_note;
        $this->resultFile = null;
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
            'resultFile' => 'nullable|file|max:8192|mimes:pdf,jpg,jpeg,png',
            'isCritical' => 'nullable|boolean',
            'criticalNote' => 'nullable|string|max:1000',
        ]);

        if (trim($this->reportFindings) === '' && trim($this->reportResult) === '' && ! $this->resultFile) {
            $this->addError('reportFindings', 'Enter the findings, the result values or attach a file.');

            return;
        }

        $filePath = $report->file_path;

        if ($this->resultFile) {
            $tenantId = (int) (auth()->user()->tenant_id ?? $report->tenant_id);
            $ext = strtolower($this->resultFile->getClientOriginalExtension() ?: $this->resultFile->extension());
            $name = ($report->barcode ?: $report->id).'.'.$ext;
            $filePath = $this->resultFile->storeAs('lab/'.$tenantId, $name, 'public');
        }

        $report->update([
            'findings' => $this->reportFindings ?: null,
            'result' => $this->reportResult ?: null,
            'file_path' => $filePath,
            'is_critical' => $this->isCritical,
            'critical_note' => $this->criticalNote ?: null,
            'status' => 'reported',
            'reported_by' => auth()->id(),
            'reported_at' => now(),
        ]);

        if ($report->is_critical) {
            $alert = DoctorAlert::create([
                'patient_id' => $report->patient_id,
                'raised_by' => auth()->id(),
                'category' => 'lab',
                'message' => 'Critical result: '.($report->test?->name ?: 'Lab test').' — '.$this->criticalNote,
                'is_urgent' => true,
            ]);

            $alert->notifyDoctors();
        }

        $this->reset(['reportId', 'reportFindings', 'reportResult', 'resultFile', 'isCritical', 'criticalNote']);
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
                'critical' => InvestigationReport::where('is_critical', true)->count(),
            ],
        ]);
    }
}
