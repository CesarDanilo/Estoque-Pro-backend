<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\CanBeMovedToTrash;
class Product extends Model
{
    use HasFactory, HasUuids, CanBeMovedToTrash;

    protected $fillable = [
        'user_id',
        'name',
        'group_id',
        'supplier_id',
        'cost_price',
        'sale_price',
        'stock_quantity',
        'min_stock_quantity',
        'description',
        'active',
    ]; // 🔴 AQUI: removido 'sku'

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'min_stock_quantity' => 'integer',
        'active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'supplier_id');
    }
}