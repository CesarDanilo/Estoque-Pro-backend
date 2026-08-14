<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Trash extends Model
{   
    use HasUuids;
    protected $table = 'trash';

    protected $fillable = ['tabela_origem', 'registro_id', 'dados', 'excluido_por', 'excluido_em'];

    protected $casts = [
        'dados' => 'array',
        'excluido_em' => 'datetime',
    ];

    public function expirado(): bool
    {
        return $this->excluido_em->addDays(7)->isPast();
    }

    public function diasRestantes(): int
    {
        return max(0, 7 - $this->excluido_em->diffInDays(now()));
    }
}