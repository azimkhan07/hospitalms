<?php

namespace App\Http\Livewire\Admins;

use App\Models\appointment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reception queue monitor (PLAN.md 18i): a big-screen board of today's OPD
 * token queue in the waiting -> called -> in-consult flow. The query mirrors
 * the Appiontment component's waitingQueue (today's intake, token order).
 */
#[Layout('admins.layouts.app')]
class QueueMonitor extends Component
{
    public function mount(): void
    {
        abort_unless(hms_can('appointments'), 403);
    }

    /** Today's live board, ordered waiting -> called -> in-consult by token. */
    #[Computed]
    public function board()
    {
        return appointment::with(['patient:id,name', 'doctor.employ:id,name'])
            ->whereDate('intime', today())
            ->whereIn('status', ['waiting', 'called', 'in_consult'])
            ->orderByRaw("CASE status WHEN 'waiting' THEN 0 WHEN 'called' THEN 1 WHEN 'in_consult' THEN 2 ELSE 3 END")
            ->orderBy('token')
            ->get();
    }

    /** The poll already re-renders every 5s; this is the manual front-desk button. */
    public function refresh(): void
    {
    }

    public function render()
    {
        if (! hms_can('appointments')) {
            abort(403);
        }

        $board = $this->board;
        $waiting = $board->where('status', 'waiting')->values();
        $called = $board->where('status', 'called')->values();
        $inConsult = $board->where('status', 'in_consult')->values();

        return view('livewire.admins.queue-monitor', [
            'waiting' => $waiting,
            'called' => $called,
            'inConsult' => $inConsult,
            'waitingCount' => $waiting->count(),
            'calledCount' => $called->count(),
            'inConsultCount' => $inConsult->count(),
            'upNext' => $waiting->take(3),
            'nextToken' => $waiting->first(),
        ]);
    }
}
