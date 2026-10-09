<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Newsletter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile mirror of the in-app video meetings + newsletter (PLAN.md 18g).
 */
class MeetingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $meetings = Meeting::with(['host:id,name', 'creator:id,name'])
            ->visibleTo($user)
            ->whereIn('status', ['scheduled', 'live'])
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get()
            ->map(fn (Meeting $m) => $this->transform($m, $user));

        return response()->json([
            'success' => true,
            'data' => $meetings,
        ]);
    }

    public function join(Request $request, int $meetingId): JsonResponse
    {
        $user = $request->user();

        $meeting = Meeting::visibleTo($user)->find($meetingId);

        if (! $meeting) {
            return response()->json(['success' => false, 'message' => 'Meeting not found.'], 404);
        }

        if ($meeting->isHost($user)) {
            $meeting->start($user);
        } elseif (! $meeting->canJoin($user)) {
            return response()->json([
                'success' => false,
                'message' => 'The meeting has not started yet.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'You can join the meeting now.',
            'data' => $this->transform($meeting->fresh(), $user),
        ]);
    }

    public function newsletters(Request $request): JsonResponse
    {
        $user = $request->user();

        $newsletters = Newsletter::with('meeting')
            ->visibleTo($user)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Newsletter $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'type' => $n->type,
                'important' => $n->important,
                'meeting_id' => $n->meeting_id,
                'created_at' => $n->created_at->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $newsletters,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function transform(Meeting $m, $user): array
    {
        return [
            'id' => $m->id,
            'title' => $m->title,
            'agenda' => $m->agenda,
            'location' => $m->location,
            'scheduled_at' => $m->scheduled_at->toIso8601String(),
            'duration_minutes' => $m->duration_minutes,
            'status' => $m->status,
            'provider' => $m->provider,
            'host' => $m->host?->name,
            'roles' => $m->targetRoleSlugs(),
            'is_host' => $m->isHost($user),
            'has_started' => $m->hasStarted(),
            'joinable' => $m->canJoin($user),
            'join_url' => $m->canJoin($user) ? $m->roomPath() : null,
            'external_url' => $m->externalUrl(),
            'recording' => (bool) $m->recording_enabled,
            'recording_control' => $m->isHost($user),
        ];
    }
}
