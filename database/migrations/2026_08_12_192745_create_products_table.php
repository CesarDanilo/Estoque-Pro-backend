<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Relacionamentos ajustados para UUID
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            // Dados do Produto
            $table->string('name', 120);
            $table->string('sku', 30);

            // Preços e Estoque
            $table->decimal('cost_price', 10, 2)->default(0.00);
            $table->decimal('sale_price', 10, 2)->default(0.00);
            $table->integer('stock_quantity')->default(0);
            $table->integer('min_stock_quantity')->default(0);

            // Descrição e Status
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);

            $table->timestamps();

            // Garantia de SKU único por usuário
            $table->unique(['user_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};