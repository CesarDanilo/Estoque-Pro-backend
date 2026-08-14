<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('trash', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tabela_origem');          // 'proprietarios', 'carros', 'revisoes'
            $table->uuid('registro_id');               // id original do registro apagado
            $table->json('dados');                      // snapshot completo do registro
            $table->foreignUuid('excluido_por')->nullable()->constrained('users');
            $table->timestamp('excluido_em');
            $table->timestamps();

            $table->index(['tabela_origem', 'excluido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trash');
    }
};