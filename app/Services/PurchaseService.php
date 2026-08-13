<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class PurchaseService
{
    /**
     * Cria uma nova compra, registra os itens e AUMENTA o estoque dos produtos.
     * É o espelho de SaleService::createSale(), mas incrementando em vez de
     * decrementar — e sem checar disponibilidade, já que aqui é entrada.
     */
    public function createPurchase(array $data, ?string $userId = null): Purchase
    {
        return DB::transaction(function () use ($data, $userId) {
            $code = $this->generatePurchaseCode();

            $subtotal = 0;
            $itemsToCreate = [];

            foreach ($data['items'] as $itemData) {
                $product = Product::lockForUpdate()->findOrFail($itemData['product_id']);

                $unitCost = $itemData['unit_cost'];
                $quantity = $itemData['quantity'];
                $totalCost = $unitCost * $quantity;

                $subtotal += $totalCost;

                $itemsToCreate[] = [
                    'id'           => Str::uuid()->toString(),
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'product_sku'  => $product->sku,
                    'quantity'     => $quantity,
                    'unit_cost'    => $unitCost,
                    'total_cost'   => $totalCost,
                ];

                // Entrada de estoque + atualiza o custo de compra do produto
                // para o valor mais recente pago ao fornecedor
                $product->increment('stock_quantity', $quantity);
                $product->update(['cost_price' => $unitCost]);
            }

            [$discountValue, $surchargeValue, $total] = $this->calcularTotais(
                $subtotal,
                $data['discount_value'] ?? 0,
                $data['discount_percentage'] ?? 0,
                $data['surcharge_value'] ?? 0,
                $data['surcharge_percentage'] ?? 0,
            );

            $purchase = Purchase::create([
                'code'                 => $code,
                'supplier_id'          => $data['supplier_id'] ?? null,
                'user_id'              => $userId,
                'subtotal'             => $subtotal,
                'discount_value'       => $discountValue,
                'discount_percentage'  => $data['discount_percentage'] ?? 0,
                'surcharge_value'      => $surchargeValue,
                'surcharge_percentage' => $data['surcharge_percentage'] ?? 0,
                'total'                => $total,
                'status'               => 'received',
                'notes'                => $data['notes'] ?? null,
            ]);

            $purchase->items()->createMany($itemsToCreate);

            return $purchase->load(['items', 'supplier', 'user']);
        });
    }

    /**
     * Atualiza uma compra existente (fornecedor, pagamento/ajustes e itens).
     * Espelho de SaleService::updateSale(): devolve (aqui, retira) o efeito
     * dos itens antigos no estoque antes de aplicar os novos.
     */
    public function updatePurchase(Purchase $purchase, array $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            $purchase->load('items');

            // 1. Retira do estoque a quantidade que os itens antigos haviam somado
            foreach ($purchase->items as $oldItem) {
                if ($oldItem->product_id) {
                    $product = Product::whereKey($oldItem->product_id)->lockForUpdate()->first();
                    if ($product) {
                        // Nunca deixa o estoque ficar negativo, mesmo que parte
                        // dessas unidades já tenha sido vendida depois da compra
                        $novaQuantidade = max(0, $product->stock_quantity - $oldItem->quantity);
                        $product->update(['stock_quantity' => $novaQuantidade]);
                    }
                }
            }

            // 2. Remove os itens antigos — serão substituídos pelos novos
            $purchase->items()->delete();

            // 3. Processa a nova lista de itens (mesma lógica da criação)
            $subtotal = 0;
            $itemsToCreate = [];

            foreach ($data['items'] as $itemData) {
                $product = Product::lockForUpdate()->findOrFail($itemData['product_id']);

                $unitCost = $itemData['unit_cost'];
                $quantity = $itemData['quantity'];
                $totalCost = $unitCost * $quantity;

                $subtotal += $totalCost;

                $itemsToCreate[] = [
                    'id'           => Str::uuid()->toString(),
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'product_sku'  => $product->sku,
                    'quantity'     => $quantity,
                    'unit_cost'    => $unitCost,
                    'total_cost'   => $totalCost,
                ];

                $product->increment('stock_quantity', $quantity);
                $product->update(['cost_price' => $unitCost]);
            }

            [$discountValue, $surchargeValue, $total] = $this->calcularTotais(
                $subtotal,
                $data['discount_value'] ?? 0,
                $data['discount_percentage'] ?? 0,
                $data['surcharge_value'] ?? 0,
                $data['surcharge_percentage'] ?? 0,
            );

            $purchase->update([
                'supplier_id'          => $data['supplier_id'] ?? null,
                'subtotal'             => $subtotal,
                'discount_value'       => $discountValue,
                'discount_percentage'  => $data['discount_percentage'] ?? 0,
                'surcharge_value'      => $surchargeValue,
                'surcharge_percentage' => $data['surcharge_percentage'] ?? 0,
                'total'                => $total,
                'notes'                => $data['notes'] ?? $purchase->notes,
            ]);

            $purchase->items()->createMany($itemsToCreate);

            return $purchase->fresh(['items', 'supplier', 'user']);
        });
    }

    /**
     * Calcula desconto, acréscimo e total finais a partir do subtotal.
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
     * Gera o próximo código sequencial de compra (ex: C-1001, C-1002...).
     *
     * Nota: o lock é aplicado sobre a linha concreta (ORDER BY ... LIMIT 1),
     * e não sobre um resultado agregado (MAX/SUM), pois o PostgreSQL não
     * permite "FOR UPDATE" combinado com funções de agregação.
     */
    private function generatePurchaseCode(): string
    {
        $prefix = 'C-';

        $lastCode = DB::table('purchases')
            ->where('code', 'like', $prefix . '%')
            ->orderByRaw("CAST(SUBSTRING(code FROM 3) AS INTEGER) DESC")
            ->lockForUpdate()
            ->value('code');

        $nextNumber = $lastCode ? ((int) substr($lastCode, strlen($prefix))) + 1 : 1001;

        return $prefix . $nextNumber;
    }
}