<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'people';

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'document',
        'gender',
        'birth_date',
        'phone',
        'email',
        'zip_code',
        'city',
        'address',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}