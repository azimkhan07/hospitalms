<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DoctorAlert;
use App\Models\InvestigationReport;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile mirror of the lab sample workflow: hand out the sample barcode and
 * escalate a critical result to the doctors' bell.
 */
class LabWorkflowController extends Controller
{
    public function barcode(Request $request, int $reportId): JsonResponse
    {
        abort_unless(hms_can('lab', $request->user()), 403);

        $report = InvestigationReport::find($reportId);

        if (! $report) {
            return response()->json([
                'success' => false,
                'message' => 'Lab order not found.',
            ], 404);
        }

        if (! $report->barcode) {
            $this->assignBarcode($report, (int) $request->user()->tenant_id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sample barcode ready.',
            'data' => [
                'barcode' => $report->barcode,
                'sample_collected_at' => optional($report->sample_collected_at)->toIso8601String(),
            ],
        ]);
    }

    public function critical(Request $request, int $reportId): JsonResponse
    {
        abort_unless(hms_can('lab', $request->user()), 403);

        $data = $request->validate([
            'critical_note' => ['nullable', 'string', 'max:1000'],
            'is_critical' => ['boolean'],
        ]);

        $report = InvestigationReport::find($reportId);

        if (! $report) {
            return response()->json([
                'success' => false,
                'message' => 'Lab order not found.',
            ], 404);
        }

        $isCritical = (bool) ($data['is_critical'] ?? false);
        $note = $data['critical_note'] ?? null;

        $report->update([
            'is_critical' => $isCritical,
            'critical_note' => $note,
        ]);

        if ($isCritical) {
            $alert = DoctorAlert::create([
                'patient_id' => $report->patient_id,
                'raised_by' => $request->user()->id,
                'category' => 'lab',
                'message' => 'Critical result: '.($report->test?->name ?: 'Lab test').($note ? ' — '.$note : ''),
                'is_urgent' => true,
            ]);

            $alert->notifyDoctors();
        }

        return response()->json([
            'success' => true,
            'message' => $isCritical ? 'Critical result escalated to doctors.' : 'Critical flag cleared.',
            'data' => [
                'id' => $report->id,
                'is_critical' => (bool) $report->is_critical,
                'critical_note' => $report->critical_note,
            ],
        ]);
    }

    /**
     * Persist a facility-unique label, retrying past any racing collector;
     * the unique index on barcode is the final guard.
     */
    private function assignBarcode(InvestigationReport $report, int $tenantId): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $barcode = InvestigationReport::generateBarcode($tenantId);

            try {
                $report->forceFill([
                    'barcode' => $barcode,
                    'sample_collected_at' => now(),
                    'sample_collected_by' => auth()->id(),
                ])->save();

                return;
            } catch (QueryException $e) {
                $report->barcode = null;
            }
        }

        // Last resort: timestamp entropy still yields a well-formed label.
        $report->forceFill([
            'barcode' => 'LAB-'.$tenantId.'-'.now()->format('Ymd').'-'.now()->format('Hisv'),
            'sample_collected_at' => now(),
            'sample_collected_by' => auth()->id(),
        ])->save();
    }
}
