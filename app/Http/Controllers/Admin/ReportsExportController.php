<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportBuilder;
use Illuminate\Http\Request;

class ReportsExportController extends Controller
{
    public function csv(Request $request)
    {
        if (! hms_can('reports')) {
            abort(403);
        }

        $r = ReportBuilder::aggregate($request->string('from')->toString(), $request->string('to')->toString());

        $rows = [];
        $section = fn ($title) => [[], [$title], []];

        [$rows[], $rows[], $rows[]] = $section('HMS Reports - '.($request->string('from')->toString() ?: 'all time'));

        $rows[] = [];
        $rows[] = ['KPI', $r['range']['from'], $r['range']['to']];
        foreach ($r['kpIs'] as $k => $v) {
            $rows[] = [$k, is_numeric($v) ? number_format($v, 2) : $v, ''];
        }

        $rows[] = [];
        $rows[] = ['Patients', 'count'];
        foreach ($r['patients'] as $k => $v) {
            $rows[] = [$k, $v];
        }

        $rows[] = [];
        $rows[] = ['Bed occupancy', 'total', 'alloted', 'available', 'cleaning', 'reserved', 'maintenance'];
        $rows[] = [
            'beds',
            $r['beds']['total'], $r['beds']['alloted'], $r['beds']['available'],
            $r['beds']['cleaning'], $r['beds']['reserved'], $r['beds']['maintenance'],
        ];

        $rows[] = [];
        $rows[] = ['Doctor performance', 'visits'];
        foreach ($r['doctor_performance'] as $d) {
            $rows[] = [$d['doctor'], $d['visits']];
        }

        $rows[] = [];
        $rows[] = ['Due list', 'patient', 'phone', 'due'];
        foreach ($r['due_list'] as $d) {
            $rows[] = [$d['invoice'], $d['patient'], $d['phone'] ?? '', $d['due']];
        }

        $content = collect($rows)
            ->map(fn ($row) => implode(',', array_map(fn ($cell) => '"'.str_replace('"', '""', (string) $cell).'"', $row)))
            ->implode("\n");

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="hms-report-'.now()->format('Ymd-His').'.csv"',
        ]);
    }
}