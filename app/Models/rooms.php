<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class rooms extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * Accommodation kinds, per PLAN.md section 9b. A ward and an ICU hold many
     * numbered beds; a private room is always capacity 1 and is listed apart
     * from the general ward.
     */
    public const TYPES = ['general', 'ward', 'icu', 'private', 'semi-private'];

    protected $fillable = [
        'name',
        'floor',
        'department_id',
        'type',
        'capacity',
        'daily_rate',
        'status',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'daily_rate' => 'decimal:2',
    ];

    /**
     * Is this room billed as a private one?
     */
    public function isPrivate(): bool
    {
        return $this->type === 'private';
    }

    /**
     * Number of beds that can actually be occupied.
     */
    public function freeBedsCount(): int
    {
        return $this->beds()->where('status', 'available')->count();
    }

    public function department()
    {
        return $this->belongsTo(department::class);
    }

    public function beds()
    {
        // The column is room_id, but Laravel would infer rooms_id from the
        // parent model name, so the key has to be spelled out.
        return $this->hasMany(beds::class, 'room_id');
    }

    public function stays()
    {
        return $this->hasMany(stay::class, 'room_id');
    }
}
