<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarEventsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $month = $request->string('month', now()->format('Y-m'))->toString();

        [$year, $mon] = array_map('intval', explode('-', substr($month, 0, 7)));

        $from = (new \DateTimeImmutable())->setDate($year, $mon, 1)->setTime(0, 0);
        $to = $from->modify('+1 month');

        $events = CalendarEvent::whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (CalendarEvent $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'type' => $e->type,
                'type_label' => $e->typeLabel(),
                'color' => $e->color,
                'starts_at' => $e->starts_at->toIso8601String(),
                'ends_at' => $e->ends_at?->toIso8601String(),
                'description' => $e->description,
            ]);

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }
}