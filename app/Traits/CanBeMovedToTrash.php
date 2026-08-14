<?php

namespace App\Traits;

use App\Models\Trash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait CanBeMovedToTrash
{
    public function moverParaLixeira(): void
    {
        DB::transaction(function () {
            Trash::create([
                'tabela_origem' => $this->getTable(),
                'registro_id'   => (string) $this->getKey(),
                'dados'         => $this->attributesToArray(), // Pega os atributos puros do banco
                'excluido_por'  => Auth::id(),
                'excluido_em'   => now(),
            ]);

            // Se for acionado por um model que possui relações com restrição de FK,
            // o $this->delete() lançará a exceção de banco de dados capturada pela Controller.
            $this->delete();
        });
    }
}