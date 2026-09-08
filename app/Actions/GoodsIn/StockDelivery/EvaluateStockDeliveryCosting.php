<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateCosts;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCost;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class EvaluateStockDeliveryCosting
{
    use AsAction;

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        $stockDelivery->items()
            ->whereNull('cost_items')
            ->update([
                'cost_items' => DB::raw('net_amount'),
                'cost_total' => DB::raw('net_amount + coalesce(cost_extra, 0) + coalesce(cost_shipping, 0) + coalesce(cost_duties, 0) + coalesce(cost_tax, 0)'),
            ]);

        $costs = $stockDelivery->costs()->get();

        foreach ([StockDeliveryCostTypeEnum::SHIPPING, StockDeliveryCostTypeEnum::DUTY] as $type) {
            $row    = $costs->firstWhere('type', $type);
            $amount = $row && !$row->is_na ? (float) $row->amount : 0;
            DistributeStockDeliveryExtraCost::distribute($stockDelivery, $type->itemCostField(), $amount);
        }

        $extraAmount = $costs
            ->where('type', StockDeliveryCostTypeEnum::EXTRA)
            ->filter(fn (StockDeliveryCost $cost) => !$cost->is_na)
            ->sum(fn (StockDeliveryCost $cost) => (float) $cost->amount);
        DistributeStockDeliveryExtraCost::distribute($stockDelivery, 'cost_extra', $extraAmount);

        $stockDelivery->update(['is_costed' => $this->isCosted($costs)]);

        if ($stockDelivery->is_costed) {
            $stockDelivery->items()
                ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
                ->update(['is_costed' => true]);
        }

        StockDeliveriesHydrateCosts::run($stockDelivery);

        return $stockDelivery->refresh();
    }

    private function isCosted($costs): bool
    {
        $agentInvoice = $costs->firstWhere('type', StockDeliveryCostTypeEnum::AGENT_INVOICE);
        if (!$agentInvoice || !$agentInvoice->received_at) {
            return false;
        }

        foreach ([StockDeliveryCostTypeEnum::SHIPPING, StockDeliveryCostTypeEnum::DUTY] as $type) {
            $row = $costs->firstWhere('type', $type);
            if (!$row || (!$row->received_at && !$row->is_na)) {
                return false;
            }
        }

        return $costs
            ->where('type', StockDeliveryCostTypeEnum::EXTRA)
            ->every(fn (StockDeliveryCost $cost) => $cost->received_at || $cost->is_na);
    }
}
