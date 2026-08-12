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
                    'product_sku'      => $product->sku,
                    'quantity'         => $quantity,
                    'unit_cost_price'  => $product->cost_price ?? 0.00,
                    'unit_price'       => $unitPrice,
                    'total_price'      => $totalPrice,
                ];

                // Baixa automática no estoque do produto
                $product->decrement('stock_quantity', $quantity);
            }

            // 3. Cálculos de Desconto / Acréscimo e Total
            $discountValue = $data['discount_value'] ?? 0;
            $discountPercentage = $data['discount_percentage'] ?? 0;
            if ($discountPercentage > 0 && $discountValue == 0) {
                $discountValue = ($subtotal * $discountPercentage) / 100;
            }

            $surchargeValue = $data['surcharge_value'] ?? 0;
            $surchargePercentage = $data['surcharge_percentage'] ?? 0;
            if ($surchargePercentage > 0 && $surchargeValue == 0) {
                $surchargeValue = ($subtotal * $surchargePercentage) / 100;
            }

            $total = max(0, $subtotal - $discountValue + $surchargeValue);

            // 4. Cria o registro da Venda
            $sale = Sale::create([
                'code'                 => $code,
                'person_id'            => $data['person_id'] ?? null,
                'user_id'              => $userId,
                'subtotal'             => $subtotal,
                'discount_value'       => $discountValue,
                'discount_percentage'  => $discountPercentage,
                'surcharge_value'      => $surchargeValue,
                'surcharge_percentage' => $surchargePercentage,
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
     * Gera o código identificador da venda (Ex: V-1001, V-1002...)
     */
    private function generateSaleCode(): string
    {
        $lastSale = Sale::withTrashed()->latest('created_at')->first();
        $nextNumber = 1001;

        if ($lastSale && preg_match('/V-(\d+)/', $lastSale->code, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return 'V-' . $nextNumber;
    }
}