<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Vínculo com o usuário dono do registro
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Dados do grupo
            $table->string('name');
            $table->text('description')->nullable();
            
            // Status
            $table->boolean('active')->default(true);

            $table->timestamps();

            // Índice para otimizar a busca combinada por usuário e nome do grupo
            $table->index(['user_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};