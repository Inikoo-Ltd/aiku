<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Nov 2025 22:38:17 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\OrgStockFamily\StoreOrgStockFamily;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateOrgStocksWithoutProducts;
use App\Enums\Inventory\OrgStock\OrgStockQuantityStatusEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncProductOrgStocksFromTradeUnits
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(Product $product): Product
    {
        $orgStocks = [];

        foreach ($product->tradeUnits as $tradeUnit) {
            $hasOrgStock = false;
            foreach ($tradeUnit->stocks as $stock) {
                $candidates = $stock->orgStocks->where('organisation_id', $product->organisation_id);
                $orgStock   = $candidates->firstWhere('state', OrgStockStateEnum::ACTIVE) ?? $candidates->first();

                if (!$orgStock) {
                    if ($stock->stockFamily) {
                        $orgStockFamily = OrgStockFamily::where('stock_family_id', $stock->stock_family_id)->where('organisation_id', $product->organisation_id)->first();
                        if (!$orgStockFamily) {
                            $orgStockFamily = StoreOrgStockFamily::make()->action($product->organisation, $stock->stockFamily, []);
                        }
                        $parent = $orgStockFamily;
                    } else {
                        $parent = $product->organisation;
                    }

                    $orgStock = StoreOrgStock::make()->action($parent, $stock, [
                        'state'           => OrgStockStateEnum::ACTIVE,
                        'quantity_status' => OrgStockQuantityStatusEnum::OUT_OF_STOCK,
                        'packed_in'       => $stock->packed_in ?? 1,
                    ]);
                }

                /** Picks follow the organisation's own packing (OS-TU), falling back to the group stock's. */
                $orgUnitsPerPack = $orgStock->tradeUnits->firstWhere('id', $tradeUnit->id)?->pivot->quantity
                    ?? $stock->pivot->quantity;

                $orgStocks[$orgStock->id] = $this->pivotData($orgStock, $tradeUnit->pivot->quantity, $orgUnitsPerPack);
                $hasOrgStock              = true;
            }

            if (!$hasOrgStock) {
                $candidates = $tradeUnit->orgStocks->where('organisation_id', $product->organisation_id);
                $orgStock   = $candidates->firstWhere('state', OrgStockStateEnum::ACTIVE) ?? $candidates->first();
                if ($orgStock) {
                    $orgStocks[$orgStock->id] = $this->pivotData($orgStock, $tradeUnit->pivot->quantity, $orgStock->pivot->quantity);
                }
            }
        }

        $product->orgStocks()->sync($orgStocks);
        $product->unsetRelation('orgStocks');
        ProductHydrateAvailableQuantity::run($product);

        foreach ($product->organisation->warehouses as $warehouse) {
            WarehouseHydrateOrgStocksWithoutProducts::dispatch($warehouse)->delay(2);
        }

        return $product;
    }

    /**
     * @return array{quantity: float|int, trade_units_per_org_stock: int|null, divisor: int, dividend: int}
     */
    private function pivotData(OrgStock $orgStock, float|int|string $tradeUnitQuantity, float|int|string $orgUnitsPerPack): array
    {
        $packedIn = null;
        if ($orgStock->tradeUnits->count() == 1) {
            $packedIn = $orgStock->tradeUnits->first()->pivot->quantity;
            if ($packedIn !== null) {
                $packedIn = floor((float) $packedIn) == (float) $packedIn ? (int) $packedIn : null;
            }
        }

        list($smallestDividend, $correspondingDivisor) = findSmallestFactors($orgUnitsPerPack);

        return [
            'quantity'                  => $tradeUnitQuantity / $orgUnitsPerPack,
            'trade_units_per_org_stock' => $packedIn,
            'divisor'                   => $correspondingDivisor,
            'dividend'                  => $smallestDividend,
        ];
    }

}
