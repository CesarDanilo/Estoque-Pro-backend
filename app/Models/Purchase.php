<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\CanBeMovedToTrash;

class Purchase extends Model
{
    use HasFactory, HasUuids, SoftDeletes, CanBeMovedToTrash;

    protected $fillable = [
        'code',
        'supplier_id',
        'user_id',
        'subtotal',
        'discount_value',
        'discount_percentage',
        'surcharge_value',
        'surcharge_percentage',
        'total',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'surcharge_value' => 'decimal:2',
        'surcharge_percentage' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'supplier_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}