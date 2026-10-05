<?php

namespace App\Http\Livewire\Admins;

use App\Models\Concerns\BelongsToTenant;
use App\Models\InvestigationReport;
use App\Models\InvestigationTest;
use App\Models\Machine;
use App\Services\InvestigationCharge;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]

/**
 * The rate card: every test the facility sells, and what it costs.
 *
 * The whole point of this screen is that the charge is shown as arithmetic, not
 * as a number that appeared (PLAN.md 9d.2). A test that would cost 900 for two
 * urgent units has to say so here, before a patient is told.
 */
class Investigations extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterCalc = '';

    public bool $showForm = false;

    public bool $showCalculator = false;

    public ?int $editingId = null;

    // test fields
    public string $name = '';

    public string $code = '';

    public ?int $machine_id = null;

    public string $department = '';

    public string $sample_type = '';

    public string $turnaround_hours = '24';

    public string $base_rate = '0';

    public string $per_unit_rate = '0';

    public string $calc_type = 'flat';

    public string $urgent_factor = '1.50';

    public string $max_units = '1';

    public bool $is_active = true;

    // live calculator
    public string $calcUnits = '1';

    public bool $calcUrgent = false;

    public string $calcDiscount = '0';

    public string $calcTax = '0';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function createTest(): void
    {
        $this->guardManage();
        $this->resetFormFields();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function editTest(int $id): void
    {
        $this->guardManage();
        $test = InvestigationTest::findOrFail($id);

        $this->editingId = $test->id;
        $this->name = (string) $test->name;
        $this->code = (string) $test->code;
        $this->machine_id = $test->machine_id;
        $this->department = (string) $test->department;
        $this->sample_type = (string) $test->sample_type;
        $this->turnaround_hours = (string) $test->turnaround_hours;
        $this->base_rate = (string) $test->base_rate;
        $this->per_unit_rate = (string) $test->per_unit_rate;
        $this->calc_type = (string) $test->calc_type;
        $this->urgent_factor = (string) $test->urgent_factor;
        $this->max_units = (string) $test->max_units;
        $this->is_active = (bool) $test->is_active;
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetFormFields();
        $this->editingId = null;
        $this->showForm = false;
    }

    public function saveTest(): void
    {
        $this->guardManage();

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:40'],
            'machine_id' => ['nullable', 'integer'],
            'department' => ['nullable', 'string', 'max:150'],
            'sample_type' => ['nullable', 'string', 'max:80'],
            'turnaround_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'base_rate' => ['required', 'numeric', 'min:0'],
            'per_unit_rate' => ['required', 'numeric', 'min:0'],
            'calc_type' => ['required', Rule::in(array_keys(InvestigationTest::CALC_TYPES))],
            'urgent_factor' => ['required', 'numeric', 'min:1', 'max:10'],
            'max_units' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $data = [
            'name' => $this->name,
            'code' => $this->code ?: null,
            'machine_id' => $this->machine_id ?: null,
            'department' => $this->department ?: null,
            'sample_type' => $this->sample_type ?: null,
            'turnaround_hours' => (int) $this->turnaround_hours,
            'base_rate' => (float) $this->base_rate,
            'per_unit_rate' => (float) $this->per_unit_rate,
            'calc_type' => $this->calc_type,
            'urgent_factor' => (float) $this->urgent_factor,
            'max_units' => (int) $this->max_units,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId === null) {
            $data['tenant_id'] = auth()->user()->tenant_id;

            $test = InvestigationTest::create($data);
            session()->flash('message', 'Test "'.$test->name.'" added to the rate card.');
        } else {
            $test = InvestigationTest::findOrFail($this->editingId);
            $test->update($data);
            session()->flash('message', 'Test "'.$test->name.'" updated.');
        }

        $this->cancelForm();
    }

    public function deleteTest(int $id): void
    {
        $this->guardManage();
        $test = InvestigationTest::findOrFail($id);

        if ($test->reports()->exists()) {
            // Deleting would rewrite history: an old report would lose its name.
            $test->update(['is_active' => false]);
            session()->flash('message', 'That test has been used, so it was retired instead of deleted.');

            return;
        }

        $test->delete();
        session()->flash('message', 'Test removed.');
    }

    /**
     * What a test costs for the numbers currently typed in the calculator.
     *
     * Used for an unsaved test while the Dean is still filling it in, and for a
     * saved one from the rate card itself.
     */
    public function quote(int $id): array
    {
        $test = InvestigationTest::findOrFail($id);

        return $this->quoteFor($test);
    }

    public function quoteFor(InvestigationTest $test): array
    {
        $calc = InvestigationCharge::for($test, [
            'units' => (int) $this->calcUnits,
            'is_urgent' => $this->calcUrgent,
            'discount' => (float) $this->calcDiscount,
            'tax_percent' => (float) $this->calcTax,
        ]);

        return [
            'formula' => $calc->formula(),
            'total' => number_format($calc->total(), 2),
        ];
    }

    protected function resetFormFields(): void
    {
        $this->name = '';
        $this->code = '';
        $this->machine_id = null;
        $this->department = '';
        $this->sample_type = '';
        $this->turnaround_hours = '24';
        $this->base_rate = '0';
        $this->per_unit_rate = '0';
        $this->calc_type = 'flat';
        $this->urgent_factor = '1.50';
        $this->max_units = '1';
        $this->is_active = true;
    }

    /** Prices are the Dean's job; the admin reads the card but cannot reprice. */
    protected function guardManage(): void
    {
        abort_unless(hms_can('investigations.manage'), 403);
    }

    public function render()
    {
        abort_unless(hms_can('investigations'), 403);

        $tests = InvestigationTest::query()
            ->with(['machine:id,name,rate,status'])
            ->withCount('reports')
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(department) LIKE ?', [$term]);
                });
            })
            ->when($this->filterCalc !== '', fn ($q) => $q->where('calc_type', $this->filterCalc))
            ->orderBy('name')
            ->paginate(15);

        // The calculator box, when open, shows the selected test worked out live.
        $preview = null;

        if ($this->showCalculator && $this->editingId) {
            $preview = $this->quoteFor(InvestigationTest::find($this->editingId));
        }

        return view('livewire.admins.investigations', [
            'tests' => $tests,
            'canManage' => hms_can('investigations.manage'),
            'calcTypes' => InvestigationTest::CALC_TYPES,
            'machines' => Machine::usable()->orderBy('name')->get(['id', 'name', 'rate', 'status']),
            'preview' => $preview,
        ]);
    }
}