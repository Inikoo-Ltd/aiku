<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 17:14:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\Hydrators;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Models\SupplyChain\Agent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class AgentHydratePurchaseOrders implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Agent $agent): string
    {
        return $agent->id;
    }

    public function handle(Agent $agent): void
    {
        $stats = [
            'number_purchase_orders' => $agent->purchaseOrders()->count(),
        ];

        $purchaseOrderStateCounts = $agent->purchaseOrders()
            ->selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state')->all();

        foreach (PurchaseOrderStateEnum::cases() as $productState) {
            $stats['number_purchase_orders_state_'.$productState->snake()] = Arr::get($purchaseOrderStateCounts, $productState->value, 0);
        }

        $purchaseOrderStatusCounts = $agent->purchaseOrders()
            ->selectRaw('delivery_state, count(*) as total')
            ->groupBy('delivery_state')
            ->pluck('total', 'delivery_state')->all();

        foreach (PurchaseOrderDeliveryStateEnum::cases() as $purchaseOrderStatusEnum) {
            $stats['number_purchase_orders_delivery_state_'.$purchaseOrderStatusEnum->snake()] = Arr::get($purchaseOrderStatusCounts, $purchaseOrderStatusEnum->value, 0);
        }

        $agent->stats()->update($stats);
    }


}
