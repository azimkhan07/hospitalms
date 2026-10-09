<?php

namespace App\Http\Livewire\Admins;

use App\Models\Handover;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The nursing shift hand-over (PLAN.md Phase 6): the outgoing nurse writes
 * down what the incoming role / ward must know, and the last 30 notes stay
 * readable as a running record.
 */
#[Layout('admins.layouts.app')]
class NurseHandovers extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $ward = '';

    public string $to_role = '';

    public string $notes = '';

    public function createHandover(): void
    {
        $this->validate([
            'notes' => 'required|string|max:4000',
            'ward' => 'nullable|string|max:100',
            'to_role' => 'nullable|string|max:50',
        ], [
            'notes.required' => 'Write what the incoming shift needs to know.',
        ]);

        Handover::create([
            'from_user' => auth()->id(),
            'ward' => $this->ward ?: null,
            'to_role' => $this->to_role ?: null,
            'notes' => $this->notes,
        ]);

        $this->reset(['notes', 'ward', 'to_role']);
        session()->flash('message', 'Handover recorded.');
    }

    public function render()
    {
        if (! hms_can('ward')) {
            abort(403);
        }

        return view('livewire.admins.nurse-handovers', [
            'handovers' => Handover::with('originator:id,name')
                ->latest()
                ->limit(30)
                ->get(),
        ]);
    }
}
