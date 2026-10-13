<?php

namespace App\Notifications;

use App\Models\InvestigationReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A laboratory result was reported: the ordering doctor's bell lights up so
 * they can review the values and close the loop with the patient. Critical
 * values additionally raise a red DoctorAlert; this fires for every report.
 */
class LabReportReady extends Notification
{
    use Queueable;

    public function __construct(public InvestigationReport $report)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $test = $this->report->test?->name ?: 'Lab test';

        return [
            'kind' => 'lab-report-ready',
            'report_id' => $this->report->id,
            'title' => 'Report ready — '.($this->report->patient?->name ?? 'Patient'),
            'message' => substr($test.' result is available for review.', 0, 140),
            'url' => route('admin_consultations'),
            'icon' => 'fa-flask',
        ];
    }
}
