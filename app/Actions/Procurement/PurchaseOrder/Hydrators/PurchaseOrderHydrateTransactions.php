<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 May 2023 11:47:40 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\Hydrators;

use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class PurchaseOrderHydrateTransactions implements ShouldBeUnique
{
    use AsAction;


    public function handle(PurchaseOrder $purchaseOrder): void
    {
        $transactionsQuery = $purchaseOrder->purchaseOrderTransactions();

        $stateCounts = $transactionsQuery->get()
            ->groupBy(fn($transaction) => $transaction->state->value)
            ->map->count();

        $stats = [];

        $stats['number_purchase_order_transactions'] = $transactionsQuery->count();

        $stats['number_current_purchase_order_transactions'] = $transactionsQuery->count() -
            Arr::get($stateCounts, PurchaseOrderTransactionStateEnum::CANCELLED->value, 0) -
            Arr::get($stateCounts, PurchaseOrderTransactionStateEnum::NOT_RECEIVED->value, 0);

        $stats['number_open_purchase_order_transactions'] = $transactionsQuery->count() -
            Arr::get($stateCounts, PurchaseOrderTransactionStateEnum::IN_PROCESS->value, 0) -
            Arr::get($stateCounts, PurchaseOrderTransactionStateEnum::SETTLED->value, 0) -
            Arr::get($stateCounts, PurchaseOrderTransactionStateEnum::CANCELLED->value, 0) -
            Arr::get($stateCounts, PurchaseOrderTransactionStateEnum::NOT_RECEIVED->value, 0);

        foreach (PurchaseOrderTransactionStateEnum::cases() as $state) {
            $stats['number_purchase_order_transactions_state_' . $state->snake()] =
                Arr::get($stateCounts, $state->value, 0);
        }

        $stats['cost_items'] = $this->getTotalCostItem($purchaseOrder);
        $stats['gross_weight'] = $this->getGrossWeight($purchaseOrder);
        $stats['net_weight'] = $this->getNetWeight($purchaseOrder);

        $purchaseOrder->update($stats);
    }

    public function getGrossWeight(PurchaseOrder $purchaseOrder): float
    {
        $grossWeight = 0;

        foreach ($purchaseOrder->purchaseOrderTransactions as $item) {
            foreach ($item->supplierProduct['tradeUnits'] as $tradeUnit) {
                $grossWeight += $item->supplierProduct['grossWeight'] * $tradeUnit->pivot->package_quantity;
            }
        }

        return $grossWeight;
    }

    public function getNetWeight(PurchaseOrder $purchaseOrder): float
    {
        $netWeight = 0;

        foreach ($purchaseOrder->purchaseOrderTransactions as $item) {
            foreach ($item->supplierProduct['tradeUnits'] as $tradeUnit) {
                $netWeight += $item->supplierProduct['netWeight'] * $tradeUnit->pivot->package_quantity;
            }
        }

        return $netWeight;
    }

    public function getTotalCostItem(PurchaseOrder $purchaseOrder): float
    {
        $costItems = 0;

        foreach ($purchaseOrder->purchaseOrderTransactions as $item) {
            $costItems += $item->unit_price * $item->supplierProduct['cost'];
        }

        return $costItems;
    }
}
