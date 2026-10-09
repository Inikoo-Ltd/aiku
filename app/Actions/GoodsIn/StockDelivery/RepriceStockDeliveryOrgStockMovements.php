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
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Stock put away from a delivery goes in at the line's goods price; once the delivery is costed
 * its shipping, duties and extras are known, so the put-away movements take the landed cost.
 * With one item, only that line's movements are repriced, so a correction on one line does not
 * rewrite the rounding of every other line.
 */
class RepriceStockDeliveryOrgStockMovements
{
    use AsAction;

    /**
     * @return array<int, string> first repriced movement date per org stock id
     */
    public function handle(StockDelivery $stockDelivery, ?StockDeliveryItem $onlyItem = null): array
    {
        $grpExchange    = GetCurrencyExchange::run($stockDelivery->organisation->currency, $stockDelivery->group->currency);
        $firstChangedOn = [];

        foreach ($stockDelivery->items()->when($onlyItem, fn ($query) => $query->where('id', $onlyItem->id))->with('orgStock')->get() as $item) {
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
                    $date = Carbon::parse($movement->date)->toDateString();
                    $firstChangedOn[$movement->org_stock_id] = min($firstChangedOn[$movement->org_stock_id] ?? $date, $date);
                }
            }
        }

        foreach (array_keys($firstChangedOn) as $orgStockId) {
            OrgStockHydrateSkuValue::dispatch(OrgStock::find($orgStockId));
            CalculateOrgStockMovementRunningValues::dispatch($orgStockId);
        }

        return $firstChangedOn;
    }
}
