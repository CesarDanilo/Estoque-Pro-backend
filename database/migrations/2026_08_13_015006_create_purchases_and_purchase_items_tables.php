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
        // 1. Tabela Principal de Compras (cabeçalho)
        Schema::create('purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Código legível da compra (ex: C-1001)
            $table->string('code', 20)->unique();

            // Relacionamento com Fornecedores (agora vive em people)
            $table->foreignUuid('supplier_id')
                ->constrained('people')
                ->restrictOnDelete();

            // Relacionamento com Usuário / Responsável pela entrada (UUID)
            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Valores Financeiros
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('discount_value', 10, 2)->default(0.00);
            $table->decimal('discount_percentage', 5, 2)->default(0.00);
            $table->decimal('surcharge_value', 10, 2)->default(0.00);
            $table->decimal('surcharge_percentage', 5, 2)->default(0.00);
            $table->decimal('total', 10, 2)->default(0.00);

            // Status da Compra
            $table->enum('status', ['received', 'pending', 'cancelled'])->default('received');

            // Observações
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Tabela de Itens da Compra
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('purchase_id')
                ->constrained('purchases')
                ->cascadeOnDelete();

            $table->foreignUuid('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            $table->string('product_name');
            $table->string('product_sku')->nullable();

            $table->integer('quantity');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cost', 10, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};