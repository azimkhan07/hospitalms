<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A dashboard announcement (PLAN.md section 12 / 18g).
 *
 * Created whenever a meeting or a meeting-bearing calendar event is scheduled,
 * scoped to the roles the organiser picked. Only those roles see it in their
 * newsletter; everyone else never sees the row at all.
 */
class Newsletter extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'title', 'body', 'type', 'target_roles', 'important',
        'meeting_id', 'calendar_event_id', 'created_by', 'tenant_id',
    ];

    protected $casts = [
        'target_roles' => 'array',
        'important' => 'boolean',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function calendarEvent()
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->whereNull('target_roles')
                ->orWhereJsonContains('target_roles', $user->roleSlug());
        });
    }

    public function icon(): string
    {
        return match ($this->type) {
            'meeting' => 'fa-video',
            'event' => 'fa-calendar-day',
            default => 'fa-bullhorn',
        };
    }
}
