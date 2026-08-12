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
        Schema::table('groups', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'name']); // Remove o índice antigo
            $table->unique(['user_id', 'name']);    // Adiciona o índice único
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
            $table->index(['user_id', 'name']);
        });
    }
};
