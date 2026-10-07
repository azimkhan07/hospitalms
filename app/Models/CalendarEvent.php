<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    use BelongsToTenant, HasFactory;

    public const BLOOD_CAMP = 'blood_camp';

    public const VISITING = 'visiting';

    public const GENERAL = 'general';

    protected $fillable = [
        'title', 'type', 'starts_at', 'ends_at', 'color', 'description', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::BLOOD_CAMP => 'Blood donation camp',
            self::VISITING => 'Visiting doctor',
            default => 'Hospital event',
        };
    }

    public static function colorFor(string $type): string
    {
        return match ($type) {
            self::BLOOD_CAMP => '#d9534f',
            self::VISITING => '#5cb85c',
            default => '#0f7fd4',
        };
    }
}