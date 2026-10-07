<?php

namespace App\Http\Livewire\Admins;

use App\Services\NotificationDigest;
use App\Services\ReportBuilder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('admins.layouts.app')]
class Reports extends Component
{
    public string $from = '';

    public string $to = '';

    public string $preset = 'month';

    public function mount(): void
    {
        abort_unless(hms_can('reports'), 403);

        $this->setRange('month');

        NotificationDigest::run();
    }

    public function setRange(string $preset): void
    {
        $this->preset = $preset;

        if ($preset === 'all') {
            $this->from = 'all';
            $this->to = '';

            return;
        }

        $start = Carbon::now()->startOfDay();
        $end = Carbon::now()->endOfDay();

        if ($preset === 'week') {
            $start = Carbon::now()->startOfWeek();
        } elseif ($preset === 'month') {
            $start = Carbon::now()->startOfMonth();
        }

        $this->from = $start->toDateString();
        $this->to = $end->toDateString();
    }

    public function render()
    {
        abort_unless(hms_can('reports'), 403);

        $data = ReportBuilder::aggregate($this->from, $this->to);

        return view('livewire.admins.reports', [
            'r' => $data,
            'maxMonthly' => max(
                1,
                collect($data['revenue']['monthly'])->max(fn ($m) => max($m['billed'], $m['collected']))
            ),
            'rangeLabel' => $this->rangeLabel(),
        ]);
    }

    private function rangeLabel(): string
    {
        if ($this->from === 'all' || $this->from === '') {
            return 'All time';
        }

        if ($this->from === $this->to) {
            return 'On '.Carbon::parse($this->from)->format('d M Y');
        }

        return Carbon::parse($this->from)->format('d M Y').' - '.Carbon::parse($this->to)->format('d M Y');
    }
}