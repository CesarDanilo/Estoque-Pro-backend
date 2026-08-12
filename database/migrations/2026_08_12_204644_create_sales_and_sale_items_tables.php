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
        // 1. Tabela Principal de Vendas
        Schema::create('sales', function (Blueprint $table) {
            // ID da Venda em UUID
            $table->uuid('id')->primary();

            // Sequencial ou código legível da venda (ex: V-1051)
            $table->string('code', 20)->unique();

            // Relacionamento com Pessoas / Clientes (UUID)
            $table->foreignUuid('person_id')
                ->nullable()
                ->constrained('people')
                ->nullOnDelete();

            // Relacionamento com Usuário / Vendedor (UUID)
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

            // Forma de pagamento
            $table->string('payment_method', 50);

            // Status da Venda
            $table->enum('status', ['completed', 'cancelled'])->default('completed');

            // Observações
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Tabela de Itens da Venda
        Schema::create('sale_items', function (Blueprint $table) {
            // ID do Item da Venda em UUID
            $table->uuid('id')->primary();

            // Relacionamento com a Venda (UUID)
            $table->foreignUuid('sale_id')
                ->constrained('sales')
                ->cascadeOnDelete();

            // Relacionamento com Produtos (UUID)
            $table->foreignUuid('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            // Snapshot do produto no momento da venda
            $table->string('product_name');
            $table->string('product_sku')->nullable();

            $table->integer('quantity');
            $table->decimal('unit_cost_price', 10, 2)->default(0.00);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};