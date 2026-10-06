<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Meeting extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'title', 'agenda', 'location', 'scheduled_at',
        'duration_minutes', 'status', 'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '>=', Carbon::now()->startOfDay())
            ->orderBy('scheduled_at');
    }
}