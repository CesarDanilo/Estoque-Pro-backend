<?php

namespace App\Models;

use App\Traits\CanBeMovedToTrash;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Person extends Model
{
    use HasFactory, HasUuids, CanBeMovedToTrash;

    protected $table = 'people';

    protected $fillable = [
        'user_id',
        'category',
        'type',
        'name',
        'trade_name',
        'document',
        'state_registration',
        'gender',
        'birth_date',
        'contact_person',
        'phone',
        'email',
        'zip_code',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'address',
        'active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Compras feitas junto a esta pessoa (quando category = supplier).
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'supplier_id');
    }

    /**
     * Vendas feitas para esta pessoa (quando category = client).
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'person_id');
    }
}