<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillItem extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    /** The charge categories a line can be filed under. */
    public const CATEGORIES = [
        'room', 'consultation', 'drug', 'lab', 'operation', 'procedure', 'other',
    ];

    protected $fillable = [
        'bill_id',
        'description',
        'category',
        'qty',
        'rate',
        'amount',
        'created_by',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function bill()
    {
        return $this->belongsTo(bill::class, 'bill_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categoryLabel(): string
    {
        return ucfirst($this->category ?: 'other');
    }
}
