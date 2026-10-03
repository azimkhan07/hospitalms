<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'work_date',
        'check_in_at', 'check_out_at',
        'check_in_latitude', 'check_in_longitude',
        'check_out_latitude', 'check_out_longitude',
        'check_in_ip', 'check_out_ip',
        'check_in_distance_meters', 'check_out_distance_meters',
        'source', 'note',
    ];

    protected $casts = [
        'work_date' => 'date',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Minutes actually worked, or null while the session is still open.
     */
    public function workedMinutes(): ?int
    {
        if (! $this->check_in_at) {
            return null;
        }

        return (int) $this->check_in_at->diffInMinutes($this->check_out_at ?? now());
    }

    /**
     * present / half_day / absent / pending
     *
     * A session that is still open is "pending": the day is not decided until
     * the person signs out.
     */
    public function status(): string
    {
        if ($this->isOpen()) {
            return 'pending';
        }

        return hms_attendance_status($this->workedMinutes());
    }

    public function isOpen(): bool
    {
        return $this->check_in_at && ! $this->check_out_at;
    }
}
