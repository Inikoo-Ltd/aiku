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
 * rewrite the rounding of every other line. Deliveries fetched from Aurora have no sowings: their
 * purchase movements carry the Aurora delivery key in the note instead (INI-211).
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
        $auroraDeliveryKey = $stockDelivery->source_id ? explode(':', $stockDelivery->source_id)[1] ?? null : null;

        foreach ($stockDelivery->items()->when($onlyItem, fn ($query) => $query->where('id', $onlyItem->id))->with('orgStock')->get() as $item) {
            $cost = $item->orgStockMovementCost();
            if (!$cost) {
                continue;
            }

            $movements = OrgStockMovement::where('type', OrgStockMovementTypeEnum::PURCHASE)
                ->where(function ($query) use ($item, $auroraDeliveryKey) {
                    $query->whereIn('id', $item->sowings()->select('org_stock_movement_id'));
                    if ($auroraDeliveryKey && $this->isFirstLineOfOrgStock($item)) {
                        $query->orWhere(fn ($query) => $query->where('org_stock_id', $item->org_stock_id)
                            ->whereRaw("substring(note from 'delivery/([0-9]+)') = ?", [$auroraDeliveryKey]));
                    }
                })
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

    /**
     * Aurora puts its delivery away without sowings, so its purchase movements are found by the
     * delivery key in their note, and the fetch prices them from the first line of the SKO.
     */
    private function isFirstLineOfOrgStock(StockDeliveryItem $item): bool
    {
        return !StockDeliveryItem::where('stock_delivery_id', $item->stock_delivery_id)
            ->where('org_stock_id', $item->org_stock_id)
            ->where('org_net_amount', '>', 0)
            ->where('unit_quantity', '>', 0)
            ->where('id', '<', $item->id)
            ->exists();
    }
}
