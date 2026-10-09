<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use Illuminate\Http\Request;

/**
 * The embedded Jitsi call page. Only the organiser (host) can start a call
 * and toggle recording; everyone invited/targeted can join once it is live
 * (PLAN.md 18g).
 */
class MeetingRoomController extends Controller
{
    public function show(Request $request, Meeting $meeting)
    {
        abort_unless(hms_can('meetings'), 403);

        $user = $request->user();

        abort_unless(Meeting::visibleTo($user)->whereKey($meeting->id)->exists(), 403);

        if (in_array($meeting->status, ['cancelled', 'completed'], true)) {
            return redirect()->route('admin_meetings')->with('error', 'That meeting is closed.');
        }

        $isHost = $meeting->isHost($user);

        if ($isHost) {
            $meeting->start($user);
        } elseif (! $meeting->canJoin($user)) {
            return redirect()->route('admin_meetings')->with('error', 'The meeting has not started yet.');
        }

        return view('admins.meeting-room', [
            'meeting' => $meeting,
            'isHost' => $isHost,
            'displayName' => $user->name,
        ]);
    }

    public function recording(Request $request, Meeting $meeting)
    {
        abort_unless($meeting->isHost($request->user()), 403);

        $enabled = $request->boolean('enabled');
        $meeting->forceFill(['recording_enabled' => $enabled])->save();

        return response()->json(['success' => true, 'recording' => $enabled]);
    }
}
