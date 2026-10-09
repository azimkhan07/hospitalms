<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Meeting extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'title', 'agenda', 'location', 'scheduled_at',
        'duration_minutes', 'status', 'created_by', 'tenant_id',
        'room_id', 'provider', 'link_url', 'host_id',
        'target_roles', 'started_at', 'recording_enabled', 'calendar_event_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'target_roles' => 'array',
        'recording_enabled' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function calendarEvent()
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    public function newsletters()
    {
        return $this->hasMany(Newsletter::class);
    }

    public function participants()
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'meeting_participants')
            ->withPivot('response')
            ->withTimestamps();
    }

    public function scopeUpcoming($query)
    {
        return $query->whereIn('status', ['scheduled', 'live'])
            ->where('scheduled_at', '>=', Carbon::now()->startOfDay())
            ->orderBy('scheduled_at');
    }

    /**
     * Every meeting a user is allowed to see: they created it, they are a
     * participant, or it targets their role.
     */
    public function scopeVisibleTo($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere('host_id', $user->id)
                ->orWhereHas('participants', fn ($p) => $p->where('user_id', $user->id))
                ->orWhereJsonContains('target_roles', $user->roleSlug());
        });
    }

    /** Stable Jitsi room name; generated lazily so old rows still work. */
    public function roomName(): string
    {
        return $this->room_id ?: 'hms-meeting-'.$this->id;
    }

    /** The in-app page that embeds the call. */
    public function roomPath(): string
    {
        return route('admin_meeting_room', $this->id);
    }

    /** External provider link (fallback for a pasted Zoom/Meet URL). */
    public function externalUrl(): ?string
    {
        if ($this->link_url) {
            return $this->link_url;
        }

        if (($this->provider ?: 'jitsi') === 'jitsi') {
            return 'https://meet.jit.si/'.$this->roomName();
        }

        return null;
    }

    public function hostId(): ?int
    {
        return (int) ($this->host_id ?: $this->created_by);
    }

    public function isHost(?User $user): bool
    {
        return $user && ($this->created_by === $user->id || $this->host_id === $user->id);
    }

    public function hasStarted(): bool
    {
        return $this->status === 'live' || $this->started_at !== null;
    }

    /** Join is allowed from a few minutes early until well past the slot. */
    public function isJoinable(?Carbon $now = null): bool
    {
        if (in_array($this->status, ['cancelled', 'completed'], true)) {
            return false;
        }

        $now ??= Carbon::now();

        if ($this->started_at && $now->gte($this->started_at->copy()->subMinute())) {
            return $now->lte($this->started_at->copy()->addMinutes($this->duration_minutes + 30));
        }

        $opens = $this->scheduled_at->copy()->subMinutes(10);
        $closes = $this->scheduled_at->copy()->addMinutes($this->duration_minutes + 30);

        return $now->between($opens, $closes) && $now->gte($opens);
    }

    public function targetRoleSlugs(): array
    {
        return array_values(array_filter((array) $this->target_roles));
    }

    /**
     * Can this user actually walk into the call right now? Non-hosts only get
     * in once the organiser has started it, even during the pre-open window.
     */
    public function canJoin(?User $user): bool
    {
        if (! $this->isJoinable()) {
            return false;
        }

        return $this->hasStarted() || $this->isHost($user);
    }

    public function start(?User $host = null): void
    {
        if ($this->hasStarted()) {
            return;
        }

        $this->forceFill([
            'started_at' => Carbon::now(),
            'status' => 'live',
            'host_id' => $this->hostId() ?: $host?->id,
        ])->save();
    }

    public static function makeRoomId(string $title): string
    {
        $slug = Str::slug($title) ?: 'meeting';

        return 'hms-'.$slug.'-'.Str::lower(Str::random(6));
    }
}
