<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->foreignUuid('user_id')
                ->after('id')
                ->constrained('users')
                ->cascadeOnDelete();
        });

        // 🔴 AQUI: documento único por usuário (não mais global no sistema),
        // mesmo padrão usado em groups e products.
        Schema::table('people', function (Blueprint $table) {
            $table->unique(['user_id', 'document']);
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'document']);
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};