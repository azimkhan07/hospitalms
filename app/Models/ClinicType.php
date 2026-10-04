<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicType extends Model
{
    protected $fillable = ['slug', 'name', 'applies_to', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * The roles a facility of this type normally buys (PLAN.md section 9c.1).
     *
     * A suggestion only: the Super Admin ticks the final list themselves, since
     * only they know what the facility actually paid for.
     *
     * @return array<int, string>
     */
    public function suggestedRoleSlugs(): array
    {
        $clinic = ['admin', 'receptionist', 'doctor', 'pharmacist'];

        $byType = [
            'general' => ['admin', 'receptionist', 'doctor', 'pharmacist'],
            'dental' => ['admin', 'receptionist', 'doctor'],
            'skin' => ['admin', 'receptionist', 'doctor', 'pharmacist'],
            'eye' => ['admin', 'receptionist', 'doctor'],
            'ent' => ['admin', 'receptionist', 'doctor'],
            'orthopaedic' => ['admin', 'receptionist', 'doctor', 'laboratorist'],
            'paediatric' => ['admin', 'receptionist', 'doctor', 'laboratorist'],
            'gynaecology' => ['admin', 'receptionist', 'doctor'],
            'cardiology' => ['admin', 'receptionist', 'doctor', 'laboratorist'],
            'neurology' => ['admin', 'receptionist', 'doctor', 'laboratorist'],
            'oncology' => ['admin', 'moderator', 'receptionist', 'doctor', 'nurse',
                'laboratorist', 'storekeeper'],
            'nephrology' => ['admin', 'moderator', 'receptionist', 'doctor', 'nurse',
                'laboratorist', 'storekeeper'],
            'dental_hospital' => ['admin', 'moderator', 'receptionist', 'doctor', 'nurse'],
            'multispeciality' => ['admin', 'moderator', 'receptionist', 'doctor', 'nurse',
                'pharmacist', 'laboratorist', 'storekeeper', 'accountant', 'hr'],
        ];

        return $byType[$this->slug] ?? $clinic;
    }

    /**
     * Is this type offered to the given tenant mode?
     */
    public function appliesTo(string $mode): bool
    {
        return in_array($this->applies_to, [$mode, 'both'], true);
    }

    public function scopeForMode($query, string $mode)
    {
        return $query->whereIn('applies_to', [$mode, 'both']);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}