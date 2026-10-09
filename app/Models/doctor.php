<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class doctor extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;
    protected $fillable = [
        'employee_id',
        'user_id',
        'on_duty',
    ];

    /**
     * Get the employ that owns the doctor
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function employ(): BelongsTo
    {
        return $this->belongsTo(employee::class,'employee_id','id');
    }

    /** The staff login that acts as this doctor (for scoping "my patients"). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Whether the linked login has checked in today (any session, open or not).
     * A doctor profile with no login link is never on duty from attendance.
     */
    public function onAttendanceToday(): bool
    {
        if (! $this->user_id) {
            return false;
        }

        return Attendance::where('user_id', $this->user_id)
            ->whereDate('work_date', Carbon::today())
            ->whereNotNull('check_in_at')
            ->exists();
    }
}
