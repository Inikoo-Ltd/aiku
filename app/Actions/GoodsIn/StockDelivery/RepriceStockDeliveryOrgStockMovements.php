<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateSkuValue;
use App\Actions\Inventory\OrgStockMovement\CalculateOrgStockMovementRunningValues;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Stock put away from a delivery goes in at the line's goods price; once the delivery is costed
 * its shipping, duties and extras are known, so the put-away movements take the landed cost.
 */
class RepriceStockDeliveryOrgStockMovements
{
    use AsAction;

    public function handle(StockDelivery $stockDelivery): void
    {
        $grpExchange = GetCurrencyExchange::run($stockDelivery->organisation->currency, $stockDelivery->group->currency);
        $orgStockIds = [];

        foreach ($stockDelivery->items()->with('orgStock')->get() as $item) {
            $cost = $item->orgStockMovementCost();
            if (!$cost) {
                continue;
            }

            $movements = OrgStockMovement::whereIn('id', $item->sowings()->select('org_stock_movement_id'))
                ->where('type', OrgStockMovementTypeEnum::PURCHASE)
                ->get();

            foreach ($movements as $movement) {
                $orgAmount = round($cost['cost_per_sku'] * $movement->quantity, 3);
                $movement->update($cost + [
                    'org_amount' => $orgAmount,
                    'grp_amount' => round($orgAmount * $grpExchange, 3),
                ]);

                if ($movement->wasChanged()) {
                    $orgStockIds[$movement->org_stock_id] = $movement->org_stock_id;
                }
            }
        }

        foreach ($orgStockIds as $orgStockId) {
            OrgStockHydrateSkuValue::dispatch(OrgStock::find($orgStockId));
            CalculateOrgStockMovementRunningValues::dispatch($orgStockId);
        }
    }
}
