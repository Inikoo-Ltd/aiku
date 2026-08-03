<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 May 2023 11:47:40 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\Hydrators;

use App\Actions\Procurement\PurchaseOrder\Traits\WithPurchaseOrderWeightAndVolume;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class PurchaseOrderHydrateTransactions implements ShouldBeUnique
{
    use AsAction;
    use WithPurchaseOrderWeightAndVolume;

    public function handle(PurchaseOrder $purchaseOrder): void
    {
        $transactions = $purchaseOrder->purchaseOrderTransactions;

        $stateCounts = $transactions->groupBy(fn ($transaction) => $transaction->state->value)->map->count();
        $deliveryStateCounts = $transactions->groupBy(fn ($transaction) => $transaction->delivery_state->value)->map->count();

        $stats = [];

        $totalCount = $transactions->count();
        $cancelledCount = Arr::get($stateCounts, PurchaseOrderTransactionStateEnum::CANCELLED->value, 0);
        $notReceivedCount = Arr::get($stateCounts, PurchaseOrderTransactionStateEnum::NOT_RECEIVED->value, 0);
        $inProcessCount = Arr::get($stateCounts, PurchaseOrderTransactionStateEnum::IN_PROCESS->value, 0);
        $settledCount = Arr::get($stateCounts, PurchaseOrderTransactionStateEnum::SETTLED->value, 0);

        $stats['number_purchase_order_transactions'] = $totalCount;
        $stats['number_current_purchase_order_transactions'] = $totalCount - $cancelledCount - $notReceivedCount;
        $stats['number_open_purchase_order_transactions'] = $totalCount - $inProcessCount - $settledCount - $cancelledCount - $notReceivedCount;

        foreach (PurchaseOrderTransactionStateEnum::cases() as $state) {
            $stats['number_purchase_order_transactions_state_'.$state->snake()] = Arr::get($stateCounts, $state->value, 0);
        }

        foreach (PurchaseOrderTransactionDeliveryStateEnum::cases() as $deliveryState) {
            $stats['number_purchase_orders_transactions_delivery_state_'.$deliveryState->snake()] = Arr::get($deliveryStateCounts, $deliveryState->value, 0);
        }

        $weightAndVolume = $this->getPurchaseOrderWeightAndVolume($purchaseOrder);

        $stats['cost_items'] = $purchaseOrder->purchaseOrderTransactions()->sum('net_amount');
        $stats['gross_weight'] = Arr::get($weightAndVolume, 'gross_weight');
        $stats['net_weight'] = Arr::get($weightAndVolume, 'net_weight');

        $purchaseOrder->update($stats);
    }
}
