<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class SaleService
{
    /**
     * Cria uma nova venda, registra os itens e baixa o estoque dos produtos.
     */
    public function createSale(array $data, ?string $userId = null): Sale
    {
        return DB::transaction(function () use ($data, $userId) {
            // 1. Gera o código legível sequencial da venda (Ex: V-1001)
            $code = $this->generateSaleCode();

            $subtotal = 0;
            $itemsToCreate = [];

            // 2. Processa os itens, verifica estoque e calcula subtotal
            foreach ($data['items'] as $itemData) {
                $product = Product::lockForUpdate()->findOrFail($itemData['product_id']);

                // Verifica se há estoque suficiente
                if ($product->stock_quantity < $itemData['quantity']) {
                    throw new Exception("Estoque insuficiente para o produto: {$product->name}. Disponível: {$product->stock_quantity}");
                }

                $unitPrice = $itemData['unit_price'];
                $quantity = $itemData['quantity'];
                $totalPrice = $unitPrice * $quantity;

                $subtotal += $totalPrice;

                // Prepara o snapshot do item
                $itemsToCreate[] = [
                    'id'               => Str::uuid()->toString(),
                    'product_id'       => $product->id,
                    'product_name'     => $product->name,
                    // 🔴 AQUI: removido 'product_sku' — coluna permanece no banco (nullable) por histórico, mas não é mais preenchida
                    'quantity'         => $quantity,
                    'unit_cost_price'  => $product->cost_price ?? 0.00,
                    'unit_price'       => $unitPrice,
                    'total_price'      => $totalPrice,
                ];

                $product->decrement('stock_quantity', $quantity);
            }

            [$discountValue, $surchargeValue, $total] = $this->calcularTotais(
                $subtotal,
                $data['discount_value'] ?? 0,
                $data['discount_percentage'] ?? 0,
                $data['surcharge_value'] ?? 0,
                $data['surcharge_percentage'] ?? 0,
            );

            // 4. Cria o registro da Venda
            $sale = Sale::create([
                'code'                 => $code,
                'person_id'            => $data['person_id'] ?? null,
                'user_id'              => $userId,
                'subtotal'             => $subtotal,
                'discount_value'       => $discountValue,
                'discount_percentage'  => $data['discount_percentage'] ?? 0,
                'surcharge_value'      => $surchargeValue,
                'surcharge_percentage' => $data['surcharge_percentage'] ?? 0,
                'total'                => $total,
                'payment_method'       => $data['payment_method'],
                'status'               => 'completed',
                'notes'                => $data['notes'] ?? null,
            ]);

            // 5. Associa os itens salvos à venda
            $sale->items()->createMany($itemsToCreate);

            return $sale->load(['items', 'customer', 'user']);
        });
    }

    /**
     * Atualiza uma venda existente (cliente, pagamento, ajustes e itens).
     *
     * Como os itens antigos já haviam baixado o estoque quando a venda foi
     * criada, aqui devolvemos esse estoque primeiro, apagamos os itens
     * antigos e então processamos a nova lista de itens exatamente como na
     * criação (validando disponibilidade e baixando o estoque de novo).
     * Tudo dentro de uma transação para nunca deixar o estoque inconsistente
     * em caso de erro no meio do caminho.
     */
    public function updateSale(Sale $sale, array $data): Sale
    {
        return DB::transaction(function () use ($sale, $data) {
            $sale->load('items');

            // 1. Devolve ao estoque a quantidade dos itens antigos desta venda
            foreach ($sale->items as $oldItem) {
                if ($oldItem->product_id) {
                    Product::whereKey($oldItem->product_id)
                        ->lockForUpdate()
                        ->increment('stock_quantity', $oldItem->quantity);
                }
            }

            // 2. Remove os itens antigos — serão substituídos pelos novos
            $sale->items()->delete();

            // 3. Processa a nova lista de itens (mesma lógica da criação)
            $subtotal = 0;
            $itemsToCreate = [];

            foreach ($data['items'] as $itemData) {
                $product = Product::lockForUpdate()->findOrFail($itemData['product_id']);

                if ($product->stock_quantity < $itemData['quantity']) {
                    throw new Exception("Estoque insuficiente para o produto: {$product->name}. Disponível: {$product->stock_quantity}");
                }

                $unitPrice = $itemData['unit_price'];
                $quantity = $itemData['quantity'];
                $totalPrice = $unitPrice * $quantity;

                $subtotal += $totalPrice;

                $itemsToCreate[] = [
                    'id'               => Str::uuid()->toString(),
                    'product_id'       => $product->id,
                    'product_name'     => $product->name,
                    'product_sku'      => $product->sku,
                    'quantity'         => $quantity,
                    'unit_cost_price'  => $product->cost_price ?? 0.00,
                    'unit_price'       => $unitPrice,
                    'total_price'      => $totalPrice,
                ];

                $product->decrement('stock_quantity', $quantity);
            }

            [$discountValue, $surchargeValue, $total] = $this->calcularTotais(
                $subtotal,
                $data['discount_value'] ?? 0,
                $data['discount_percentage'] ?? 0,
                $data['surcharge_value'] ?? 0,
                $data['surcharge_percentage'] ?? 0,
            );

            // 4. Atualiza os dados principais da venda
            $sale->update([
                'person_id'            => $data['person_id'] ?? null,
                'subtotal'             => $subtotal,
                'discount_value'       => $discountValue,
                'discount_percentage'  => $data['discount_percentage'] ?? 0,
                'surcharge_value'      => $surchargeValue,
                'surcharge_percentage' => $data['surcharge_percentage'] ?? 0,
                'total'                => $total,
                'payment_method'       => $data['payment_method'] ?? $sale->payment_method,
                'notes'                => $data['notes'] ?? $sale->notes,
            ]);

            // 5. Cria os novos itens já vinculados à venda
            $sale->items()->createMany($itemsToCreate);

            return $sale->fresh(['items', 'customer', 'user']);
        });
    }

    /**
     * Calcula desconto, acréscimo e total finais a partir do subtotal.
     * Se um percentual for informado sem valor fixo equivalente, o valor é
     * derivado do percentual sobre o subtotal.
     *
     * @return array{0: float, 1: float, 2: float} [discountValue, surchargeValue, total]
     */
    private function calcularTotais(
        float $subtotal,
        float $discountValue,
        float $discountPercentage,
        float $surchargeValue,
        float $surchargePercentage,
    ): array {
        if ($discountPercentage > 0 && $discountValue == 0) {
            $discountValue = ($subtotal * $discountPercentage) / 100;
        }

        if ($surchargePercentage > 0 && $surchargeValue == 0) {
            $surchargeValue = ($subtotal * $surchargePercentage) / 100;
        }

        $total = max(0, $subtotal - $discountValue + $surchargeValue);

        return [$discountValue, $surchargeValue, $total];
    }

    /**
     * Gera o próximo código sequencial de venda (ex: V-1001, V-1002...).
     *
     * Nota: o lock é aplicado sobre a linha concreta (ORDER BY ... LIMIT 1),
     * e não sobre um resultado agregado (MAX/SUM), pois o PostgreSQL não
     * permite "FOR UPDATE" combinado com funções de agregação.
     */
    private function generateSaleCode(): string
    {
        $prefix = 'V-';

        $lastCode = DB::table('sales')
            ->where('code', 'like', $prefix . '%')
            ->orderByRaw("CAST(SUBSTRING(code FROM 3) AS INTEGER) DESC")
            ->lockForUpdate()
            ->value('code');

        $nextNumber = $lastCode ? ((int) substr($lastCode, strlen($prefix))) + 1 : 1001;

        return $prefix . $nextNumber;
    }
}